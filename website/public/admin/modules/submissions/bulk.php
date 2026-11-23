<?php
/**
 * Bulk triage: apply one action to everything ticked on the list page.
 *
 * The content modules delegate to processBulkAction(), which knows how to
 * publish, unpublish and trash. None of those verbs apply here — an enquiry is
 * never published — so this handler drives SUBMISSION_BULK_ACTIONS instead.
 * The validation shape is deliberately identical to the shared helper's:
 * capability, method, CSRF, then ids cast to positive integers before any of
 * them reach SQL.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('submissions.manage', 'index.php');
requirePostWithCsrf('index.php');

$action = (string) ($_POST['bulk_action'] ?? '');
if (!isset(SUBMISSION_BULK_ACTIONS[$action])) {
    flashSet('error', 'Please choose an action to apply.');
    redirect('index.php');
}

// dbIdPlaceholders() does the casting and the de-duplication, so what comes
// back is only ever positive integers.
$ids = dbIdPlaceholders((array) ($_POST['ids'] ?? []))['ids'];
if ($ids === []) {
    flashSet('error', 'No submissions were selected.');
    redirect('index.php');
}

$config = SUBMISSION_BULK_ACTIONS[$action];
$userId = (int) currentUser()['id'];

if ($config['status'] === null) {
    $count = trashItems('submissions', $ids);
} else {
    $count = submissionSetStatus($ids, $config['status'], $userId);
}

logActivity(
    $userId,
    $action === 'trash' ? 'delete' : 'update',
    'submissions',
    sprintf('Bulk %s %d submission(s)', $config['done'], $count)
);

flashSet('success', sprintf(
    '%d submission%s %s.',
    $count,
    $count === 1 ? '' : 's',
    $config['done']
));

redirect('index.php');
