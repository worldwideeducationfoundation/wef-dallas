<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/media-specs.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'role' => trim((string) ($_POST['role'] ?? '')),
    'bio' => trim((string) ($_POST['bio'] ?? '')) ?: null,
    'section' => (string) ($_POST['section'] ?? TEAM_SECTIONS[0]),
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['name'] === '' || mb_strlen($input['name']) > 160) {
    $errors[] = 'Name is required (max 160 characters).';
}
if ($input['role'] === '' || mb_strlen($input['role']) > 160) {
    $errors[] = 'Role is required (max 160 characters).';
}
if (!in_array($input['section'], TEAM_SECTIONS, true)) {
    $errors[] = 'Please choose a valid section.';
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
    $existingStmt = getDb()->prepare('SELECT image_path FROM team_members WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldImagePath = $existingRow['image_path'] ?? null;
}

$upload = handleImageUpload('image', 'team', $oldImagePath, !empty($_POST['remove_image']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['image_path'] = $upload['path'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE team_members SET name = :name, role = :role, section = :section, bio = :bio, image_path = :image_path,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'team', $input['name']);
    flashSet('success', 'Team member updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO team_members (name, role, section, bio, image_path, display_order, status)
         VALUES (:name, :role, :section, :bio, :image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'team', $input['name']);
    flashSet('success', 'Team member added.');
}

redirect('index.php');
