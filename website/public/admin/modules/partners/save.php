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
    'name' => trim((string) ($_POST['name'] ?? '')),
    'link_url' => trim((string) ($_POST['link_url'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['name'] === '' || mb_strlen($input['name']) > 200) {
    $errors[] = 'Partner name is required (max 200 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if ($input['link_url'] !== null
    && (!filter_var($input['link_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $input['link_url']))) {
    $errors[] = 'Website link must be a full http(s):// address.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldLogoPath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT logo_path FROM partners WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldLogoPath = $existingRow['logo_path'] ?? null;
}

$upload = handleImageUpload('logo', 'partners', $oldLogoPath, !empty($_POST['remove_logo']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['logo_path'] = $upload['path'];

if ($input['status'] === 'active' && $input['logo_path'] === null) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', 'An active partner needs a logo. Upload one, or save it as a draft.');
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE partners SET name = :name, link_url = :link_url, logo_path = :logo_path,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'partners', $input['name']);
    flashSet('success', 'Partner updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO partners (name, link_url, logo_path, display_order, status)
         VALUES (:name, :link_url, :logo_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'partners', $input['name']);
    flashSet('success', 'Partner created.');
}

redirect('index.php');
