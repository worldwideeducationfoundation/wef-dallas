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
    'title_lead' => trim((string) ($_POST['title_lead'] ?? '')),
    'title_highlight' => trim((string) ($_POST['title_highlight'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
    'image_alt' => trim((string) ($_POST['image_alt'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title_lead'] === '' || mb_strlen($input['title_lead']) > 120) {
    $errors[] = 'The first part of the headline is required (max 120 characters).';
}
if ($input['title_highlight'] === '' || mb_strlen($input['title_highlight']) > 120) {
    $errors[] = 'The second part of the headline is required (max 120 characters).';
}
if ($input['description'] !== null && mb_strlen($input['description']) > 400) {
    $errors[] = 'Description must be 400 characters or fewer.';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldImagePath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT image_path FROM hero_slides WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldImagePath = $existingRow['image_path'] ?? null;
}

$upload = handleImageUpload('image', 'hero', $oldImagePath, !empty($_POST['remove_image']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['image_path'] = $upload['path'];

// A slide with no photo would show the dark overlay over nothing; allowed
// as a draft, not as a live slide.
if ($input['status'] === 'active' && $input['image_path'] === null) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', 'An active slide needs a background photo. Upload one, or save it as a draft.');
    redirect($backTarget);
}

$label = $input['title_lead'] . ' ' . $input['title_highlight'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE hero_slides SET title_lead = :title_lead, title_highlight = :title_highlight,
         description = :description, image_path = :image_path, image_alt = :image_alt,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'hero-slides', $label);
    flashSet('success', 'Slide updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO hero_slides (title_lead, title_highlight, description, image_path, image_alt, display_order, status)
         VALUES (:title_lead, :title_highlight, :description, :image_path, :image_alt, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'hero-slides', $label);
    flashSet('success', 'Slide created.');
}

redirect('index.php');
