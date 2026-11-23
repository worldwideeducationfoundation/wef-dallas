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

$title = trim((string) ($_POST['title'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
if ($slug === '' && $title !== '') {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
}

$input = [
    'title' => $title,
    'slug' => $slug,
    'content' => trim((string) ($_POST['content'] ?? '')),
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title'] === '' || mb_strlen($input['title']) > 160) {
    $errors[] = 'Title is required (max 160 characters).';
}
if ($input['slug'] === '' || mb_strlen($input['slug']) > 160) {
    $errors[] = 'Slug is required (max 160 characters).';
}
if ($input['content'] === '') {
    $errors[] = 'Content is required.';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

// Check slug uniqueness
$slugCheckStmt = getDb()->prepare('SELECT id FROM project_updates WHERE slug = :slug AND id != :id');
$slugCheckStmt->execute(['slug' => $input['slug'], 'id' => $id ?? 0]);
if ($slugCheckStmt->fetch()) {
    $errors[] = 'That slug is already in use by another update.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldImagePath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT image_path FROM project_updates WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldImagePath = $existingRow['image_path'] ?? null;
}

// Subfolder is 'updates', not 'programs'. It was 'programs' — a copy-paste
// from the module this one was cloned from — which filed every project-update
// image under admin/uploads/programs/ alongside a different content type.
$imageUpload = handleImageUpload('image', 'updates', $oldImagePath, !empty($_POST['remove_image']));
if (!$imageUpload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $imageUpload['error']);
    redirect($backTarget);
}

$input['image_path'] = $imageUpload['path'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE project_updates SET title = :title, slug = :slug, content = :content,
         image_path = :image_path, display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'updates', $input['title']);
    flashSet('success', 'Project Update updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO project_updates (title, slug, content, image_path, display_order, status)
         VALUES (:title, :slug, :content, :image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'updates', $input['title']);
    flashSet('success', 'Project Update created.');
}

redirect('index.php');
