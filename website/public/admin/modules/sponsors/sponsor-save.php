<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$input = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'badge_text' => trim((string) ($_POST['badge_text'] ?? '')) ?: null,
    'group_id' => (int) ($_POST['group_id'] ?? 0),
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$backTarget = $isEdit ? 'sponsor-form.php?id=' . $id : 'sponsor-form.php?group_id=' . $input['group_id'];

$errors = [];

if ($input['name'] === '' || mb_strlen($input['name']) > 255) {
    $errors[] = 'Sponsor name is required (max 255 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if ($input['group_id'] <= 0) {
    $errors[] = 'Please choose a sponsor group.';
} else {
    $groupCheck = getDb()->prepare('SELECT id FROM sponsor_groups WHERE id = :id LIMIT 1');
    $groupCheck->execute(['id' => $input['group_id']]);
    if (!$groupCheck->fetch()) {
        $errors[] = 'That sponsor group no longer exists.';
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE sponsors SET name = :name, badge_text = :badge_text, group_id = :group_id,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'sponsors', $input['name']);
    flashSet('success', 'Sponsor updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO sponsors (name, badge_text, group_id, display_order, status)
         VALUES (:name, :badge_text, :group_id, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'sponsors', $input['name']);
    flashSet('success', 'Sponsor added.');
}

redirect('sponsors.php?group_id=' . $input['group_id']);
