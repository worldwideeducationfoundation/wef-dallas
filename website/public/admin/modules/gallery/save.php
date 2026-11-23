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
    'caption' => trim((string) ($_POST['caption'] ?? '')),
    'program_id' => ($_POST['program_id'] ?? '') === '' ? null : (int) $_POST['program_id'],
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['caption'] === '' || mb_strlen($input['caption']) > 160) {
    $errors[] = 'Caption is required (max 160 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if ($input['program_id'] !== null) {
    $projectStmt = getDb()->prepare('SELECT COUNT(*) FROM programs WHERE id = :id AND deleted_at IS NULL');
    $projectStmt->execute(['id' => $input['program_id']]);
    if ((int) $projectStmt->fetchColumn() === 0) {
        $errors[] = 'Please choose a valid project.';
    }
}
// A gallery entry with no photo at all isn't useful — required on create;
// on edit, the existing photo can be kept without re-uploading.
if (!$isEdit && (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
    $errors[] = 'A photo is required.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldImagePath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT image_path FROM gallery_items WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldImagePath = $existingRow['image_path'] ?? null;
}

$upload = handleImageUpload('image', 'gallery', $oldImagePath, !empty($_POST['remove_image']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['image_path'] = $upload['path'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE gallery_items SET caption = :caption, program_id = :program_id, image_path = :image_path,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'gallery', $input['caption']);
    flashSet('success', 'Photo updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO gallery_items (caption, program_id, image_path, display_order, status)
         VALUES (:caption, :program_id, :image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'gallery', $input['caption']);
    flashSet('success', 'Photo added.');
}

redirect('index.php');
