<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

// Login only at this point — the capability depends on WHICH type is being
// restored, and that is not known until the POST body has been read. The real
// check is requireCapability() a few lines down, once $type is resolved
// against the registry.
requireLogin();

requirePostWithCsrf();

$user = currentUser();
$type = (string) ($_POST['type'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!isset(TRASH_TYPES[$type]) || $id <= 0) {
    flashSet('error', 'That item could not be restored.');
    redirect('index.php');
}

$config = TRASH_TYPES[$type];

// Restoring a trashed enquiry needs submissions.manage; restoring a programme
// needs content.edit. Enforced from the registry so this handler can never
// grant more than the module the item came from would.
requireCapability($config['restoreCap']);

// Read the label before restoring, for the audit entry.
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

$restored = restoreItems($type, [$id]);

if ($restored > 0) {
    logActivity($user['id'], 'restore', $config['module'], $row['label']);
    // Restored content keeps whatever status it had. If it was Active it
    // is public again immediately, so say so rather than letting that be
    // a surprise. Form submissions are never published, so that warning
    // would be false for them — they get the plain confirmation instead.
    flashSet('success', $type === 'submissions'
        ? sprintf('Submission from "%s" restored.', $row['label'])
        : sprintf('"%s" restored. Check its status if it should not be public yet.', $row['label']));
} else {
    flashSet('error', 'That item could not be restored.');
}

redirect('index.php');
