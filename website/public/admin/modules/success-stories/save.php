<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'tag' => trim((string) ($_POST['tag'] ?? '')) ?: null,
    'story_date' => trim((string) ($_POST['story_date'] ?? '')) ?: null,
    'link_url' => trim((string) ($_POST['link_url'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title'] === '' || mb_strlen($input['title']) > 200) {
    $errors[] = 'Title is required (max 200 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if ($input['link_url'] !== null && !filter_var($input['link_url'], FILTER_VALIDATE_URL)) {
    $errors[] = 'Read More link must be a valid URL.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldImagePath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT image_path FROM success_stories WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldImagePath = $existingRow['image_path'] ?? null;
}

$upload = handleImageUpload('image', 'success-stories', $oldImagePath, !empty($_POST['remove_image']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['image_path'] = $upload['path'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE success_stories SET title = :title, tag = :tag, story_date = :story_date,
         link_url = :link_url, image_path = :image_path, display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'success-stories', $input['title']);
    flashSet('success', 'Success story updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO success_stories (title, tag, story_date, link_url, image_path, display_order, status)
         VALUES (:title, :tag, :story_date, :link_url, :image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'success-stories', $input['title']);
    flashSet('success', 'Success story created.');
}

redirect('index.php');
