<?php
/**
 * Sends one newsletter signup to the Constant Contact list by hand.
 *
 * The case this exists for: Constant Contact was unreachable for ten minutes,
 * or the connection had lapsed, and a handful of addresses were stored without
 * ever reaching the list. Without this the only fix is copying them across in
 * the Constant Contact UI, which is precisely the manual step the integration
 * was built to remove.
 *
 * Also covers signups collected before the integration existed — those rows
 * have neither a synced timestamp nor an error, and the detail page offers the
 * same button for them.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('submissions.manage', '../../dashboard.php');

requirePostWithCsrf();

$user = currentUser();

$id = postId();
if ($id === null) {
    redirect('index.php');
}

$submission = dbFetchOne(
    'SELECT id, form_type, email, newsletter_synced_at
       FROM form_submissions
      WHERE id = :id AND deleted_at IS NULL
      LIMIT 1',
    ['id' => $id]
);

if ($submission === null) {
    flashSet('error', 'That submission could not be found.');
    redirect('index.php');
}

$back = 'view.php?id=' . $id;

// Only newsletter signups carry consent to be put on a mailing list. Somebody
// who used the contact form asked a question; adding them to a newsletter
// because an admin clicked a button on the wrong page would be exactly the
// unsolicited subscription anti-spam law exists to prevent. Guarded here and
// not only in the UI, because the UI is not what arrives at this endpoint.
if ($submission['form_type'] !== 'newsletter') {
    flashSet('error', 'Only newsletter signups can be added to the mailing list.');
    redirect($back);
}

if (!empty($submission['newsletter_synced_at'])) {
    flashSet('success', 'That address is already on the mailing list.');
    redirect($back);
}

if (!ccIsActive()) {
    flashSet('error', 'Constant Contact is not connected, or no mailing list has been chosen yet.');
    redirect($back);
}

$result = ccSyncSubmission($id, (string) $submission['email']);

if ($result['ok']) {
    logActivity((int) $user['id'], 'update', 'submissions', 'Added submission #' . $id . ' to the Constant Contact list');
    flashSet('success', 'Added ' . $submission['email'] . ' to the mailing list.');
} else {
    flashSet('error', 'Constant Contact did not accept that address: ' . ($result['error'] ?? 'unknown error'));
}

redirect($back);
