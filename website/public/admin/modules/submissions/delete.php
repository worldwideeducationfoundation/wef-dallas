<?php
/**
 * Moves one submission to Trash.
 *
 * Not processDelete() from includes/trash.php, which the content modules use:
 * that helper hardcodes requireCapability('content.delete'), and the whole
 * point of this module's separate capability is that being allowed to delete a
 * programme is not the same as being allowed to delete a member of the
 * public's enquiry. Everything after the guard is the same shape as that
 * helper, and trashItems() does the actual work.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

requireCapability('submissions.manage', 'index.php');
requirePostWithCsrf('index.php');

$id = postId();
if ($id === null) {
    redirect('index.php');
}

$submission = dbFetchOne(
    'SELECT name FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1',
    ['id' => $id]
);

if ($submission === null) {
    flashSet('error', 'That submission could not be found.');
    redirect('index.php');
}

trashItems('submissions', [$id]);

logActivity(currentUser()['id'], 'delete', 'submissions', 'Submission from ' . $submission['name']);
flashSet('success', 'Submission moved to Trash. You can restore it from Trash.');

// Back to the list, not to view.php — the row it was showing is gone.
redirect('index.php');
