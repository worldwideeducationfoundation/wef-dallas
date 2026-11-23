<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'group-form.php?id=' . $id : 'group-form.php';

$input = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title'] === '' || mb_strlen($input['title']) > 255) {
    $errors[] = 'Group title is required (max 255 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE sponsor_groups SET title = :title, description = :description,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'sponsors', $input['title']);
    flashSet('success', 'Sponsor group updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO sponsor_groups (title, description, display_order, status)
         VALUES (:title, :description, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'sponsors', $input['title']);
    flashSet('success', 'Sponsor group created.');
}

redirect('index.php');
