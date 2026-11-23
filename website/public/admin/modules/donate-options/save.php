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
    'label' => trim((string) ($_POST['label'] ?? '')) ?: null,
    'slug' => strtolower(trim((string) ($_POST['slug'] ?? ''))),
    'program_id' => ($_POST['program_id'] ?? '') !== '' ? (int) $_POST['program_id'] : null,
    'summary' => trim((string) ($_POST['summary'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

$program = null;
if ($input['program_id'] !== null) {
    $stmt = getDb()->prepare('SELECT id, name, slug FROM programs WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $input['program_id']]);
    $program = $stmt->fetch() ?: null;
    if ($program === null) {
        $errors[] = 'Please choose a project from the list.';
    }
}

if ($input['label'] === null && $input['program_id'] === null) {
    $errors[] = 'Give the option a label, or link it to a project.';
}
if ($input['label'] !== null && mb_strlen($input['label']) > 200) {
    $errors[] = 'The label can be at most 200 characters.';
}
if ($input['summary'] !== null && mb_strlen($input['summary']) > 1000) {
    $errors[] = 'The description can be at most 1000 characters.';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

// The name in the address: given, else the project's own slug (the project
// pages' Donate buttons link with it), else made from the label.
if ($input['slug'] === '') {
    $base = $program !== null ? (string) $program['slug'] : (string) $input['label'];
    $input['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-');
}
if ($input['slug'] === '' || strlen($input['slug']) > 100 || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $input['slug'])) {
    $errors[] = 'The name in the address may use only lowercase letters, numbers and single dashes.';
} else {
    $stmt = getDb()->prepare('SELECT id, deleted_at FROM donate_options WHERE slug = :slug AND id <> :id LIMIT 1');
    $stmt->execute(['slug' => $input['slug'], 'id' => $id ?? 0]);
    $clash = $stmt->fetch();
    if ($clash) {
        $errors[] = $clash['deleted_at'] !== null
            ? "The name \"{$input['slug']}\" belongs to an option in Trash. Restore that one, or choose another name."
            : "Another option already uses the name \"{$input['slug']}\" in the address.";
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldImage = null;
if ($isEdit) {
    $stmt = getDb()->prepare('SELECT image_path FROM donate_options WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $oldImage = $stmt->fetchColumn() ?: null;
}

$upload = handleImageUpload('image', 'donate', $oldImage, !empty($_POST['remove_image']));
if (!$upload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $upload['error']);
    redirect($backTarget);
}
$input['image_path'] = $upload['path'];

$name = $input['label'] ?? (string) $program['name'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE donate_options SET label = :label, slug = :slug, program_id = :program_id, summary = :summary,
         image_path = :image_path, display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'donate-options', $name);
    flashSet('success', 'Option updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO donate_options (label, slug, program_id, summary, image_path, display_order, status)
         VALUES (:label, :slug, :program_id, :summary, :image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'donate-options', $name);
    flashSet('success', 'Option added.');
}

redirect('index.php');
