<?php
/**
 * Saves a status change and the internal note from view.php.
 *
 * One handler for both fields because they are one form: an admin moving a
 * submission to "Replied" and recording who replied is a single act, and
 * splitting it into two buttons would mean two audit entries describing half
 * of it each.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('submissions.manage', 'index.php');
requirePostWithCsrf('index.php');

$id = postId();
if ($id === null) {
    redirect('index.php');
}

$submission = dbFetchOne(
    'SELECT name, status FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1',
    ['id' => $id]
);

if ($submission === null) {
    flashSet('error', 'That submission could not be found.');
    redirect('index.php');
}

$status = (string) ($_POST['status'] ?? '');
if (!isset(SUBMISSION_STATUSES[$status])) {
    flashSet('error', 'Please choose one of the listed statuses.');
    redirect('view.php?id=' . $id);
}

// Trimmed to null rather than stored as an empty string, so "has a note" is a
// single IS NOT NULL check everywhere else rather than two conditions that
// disagree the first time someone clears the box.
$note = trim((string) ($_POST['admin_note'] ?? ''));
$note = $note === '' ? null : mb_substr($note, 0, 1000);

$userId = (int) currentUser()['id'];

// Both writes in one transaction: a saved note against an unchanged status
// (or the reverse) is a half-applied edit that the page would then report as
// a success.
dbTransaction(static function () use ($id, $status, $note, $userId): void {
    submissionSetStatus([$id], $status, $userId);
    dbExecute(
        'UPDATE form_submissions SET admin_note = :note WHERE id = :id AND deleted_at IS NULL',
        ['note' => $note, 'id' => $id]
    );
});

// The audit entry names the person who wrote in and where the submission got
// to, because "update, submissions" on its own tells a later reader nothing
// about what changed.
logActivity(
    currentUser()['id'],
    'update',
    'submissions',
    sprintf('%s → %s', $submission['name'], SUBMISSION_STATUSES[$status])
);

flashSet('success', 'Submission updated.');
redirect('view.php?id=' . $id);
