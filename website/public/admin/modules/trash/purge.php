<?php
/**
 * The only place in the admin that actually destroys content. Everything
 * else soft-deletes. Only ever acts on rows already sitting in Trash, and
 * requires the purge capability the registry names for that specific type —
 * content.delete for site content, submissions.manage for a form submission.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

// Login only until the type is known; the capability check is below, once
// $type has been resolved against TRASH_TYPES.
requireLogin();

requirePostWithCsrf();

$user = currentUser();
$type = (string) ($_POST['type'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!isset(TRASH_TYPES[$type]) || $id <= 0) {
    flashSet('error', 'That item could not be deleted.');
    redirect('index.php');
}

$config = TRASH_TYPES[$type];

requireCapability($config['purgeCap']);

$stmt = getDb()->prepare(
    "SELECT {$config['labelColumn']} AS label FROM {$config['table']}
     WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1"
);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flashSet('error', 'That item is no longer in Trash.');
    redirect('index.php');
}

$label = $row['label'];
$purged = purgeItems($type, [$id]);

if ($purged > 0) {
    logActivity($user['id'], 'purge', $config['module'], $label);
    flashSet('success', sprintf('"%s" was permanently deleted.', $label));
} else {
    flashSet('error', 'That item could not be deleted.');
}

redirect('index.php');
