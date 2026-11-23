<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

requireCapability('content.delete');

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

if ($id) {
    $stmt = getDb()->prepare('SELECT title FROM sponsor_groups WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        // Soft delete — the row keeps its data and its uploaded files and
        // moves to Trash, where it can be restored or purged deliberately.
        trashItems('sponsor_groups', [$id]);

        logActivity($user['id'], 'delete', 'sponsor_groups', $row['title']);
        flashSet('success', 'Sponsor group moved to Trash. You can restore it from Trash.');
    } else {
        flashSet('error', 'That Sponsor group could not be found.');
    }
}

redirect('index.php');