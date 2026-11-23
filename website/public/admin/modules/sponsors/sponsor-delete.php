<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

requireCapability('content.delete');

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : null;

if ($id) {
    $stmt = getDb()->prepare('SELECT name FROM sponsors WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        // Soft delete — the row keeps its data and its uploaded files and
        // moves to Trash, where it can be restored or purged deliberately.
        trashItems('sponsors', [$id]);

        logActivity($user['id'], 'delete', 'sponsors', $row['name']);
        flashSet('success', 'Sponsor moved to Trash. You can restore it from Trash.');
    } else {
        flashSet('error', 'That Sponsor could not be found.');
    }
}

redirect($groupId ? 'sponsors.php?group_id=' . $groupId : 'index.php');