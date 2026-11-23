<?php
/**
 * Redeems the authorisation code that callback.php handed over.
 *
 * Reached only by the auto-submitted form on callback.php — see the long note
 * at the top of that file for why the OAuth callback cannot do this itself
 * (SameSite=Strict means the return trip from constantcontact.com arrives with
 * no session at all). Because this POST originates from a page on our own
 * origin, it is same-site, so the session cookie is present here and the normal
 * capability check applies.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('newsletter.manage', '../../dashboard.php');

/**
 * Deliberately NOT requirePostWithCsrf().
 *
 * The CSRF token is a session value, and the form that posts here was rendered
 * by callback.php — which, having no session, could not have embedded one. The
 * OAuth `state` check below is what stands in for it, and is the stronger check
 * for this particular request: it is a random per-flow value that was written
 * into this session by connect.php, so it proves both that the flow started
 * here and that it is the same browser session finishing it. A generic CSRF
 * token would prove only the second.
 *
 * The method check still applies — nothing here should be reachable by GET.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('index.php');
}

$user = currentUser();

$expectedState = (string) ($_SESSION['cc_oauth_state'] ?? '');
$startedAt = (int) ($_SESSION['cc_oauth_started_at'] ?? 0);

// One flow per state value. Cleared before any early return below, so a
// failure cannot leave a live state sitting in the session for a later replay.
unset($_SESSION['cc_oauth_state'], $_SESSION['cc_oauth_started_at']);

$submittedState = (string) ($_POST['state'] ?? '');
$code = trim((string) ($_POST['code'] ?? ''));
$error = trim((string) ($_POST['error'] ?? ''));
$errorDescription = trim((string) ($_POST['error_description'] ?? ''));

// hash_equals rather than ===: same reasoning as csrfVerify().
if ($expectedState === '' || !hash_equals($expectedState, $submittedState)) {
    adminLog('WARN', 'constant contact: callback state mismatch — authorisation rejected');
    flashSet('error', 'That connection attempt could not be verified. Please click Connect and try again.');
    redirect('index.php');
}

// Fifteen minutes is far longer than signing in to Constant Contact takes and
// short enough that a state left behind in an abandoned session is not a
// standing invitation.
if ($startedAt === 0 || time() - $startedAt > 900) {
    flashSet('error', 'That connection attempt took too long and has expired. Please click Connect and try again.');
    redirect('index.php');
}

// Constant Contact reports a refusal by redirecting back with ?error=, not by
// failing the request — most commonly access_denied, when somebody clicks
// "Deny" or closes the approval screen.
if ($error !== '') {
    $detail = $error === 'access_denied'
        ? 'The request to access your Constant Contact account was declined.'
        : ($errorDescription !== '' ? $errorDescription : $error);

    adminLog('WARN', 'constant contact: authorisation refused — ' . $error);
    flashSet('error', 'Constant Contact did not complete the connection. ' . $detail);
    redirect('index.php');
}

if ($code === '') {
    flashSet('error', 'Constant Contact did not send an authorisation code back. Please try connecting again.');
    redirect('index.php');
}

$result = ccExchangeCode($code);

if (!$result['ok']) {
    flashSet('error', 'Could not complete the connection: ' . $result['error']);
    redirect('index.php');
}

dbExecute(
    'UPDATE constant_contact SET connected_at = :at, connected_by = :user WHERE id = 1',
    ['at' => date('Y-m-d H:i:s'), 'user' => (int) $user['id']]
);

logActivity((int) $user['id'], 'update', 'settings', 'Connected Constant Contact');

// The list is the one thing still missing after a first connection, and
// signups go nowhere without it — so the success message says so rather than
// leaving somebody to notice on their own.
$listChosen = !empty(ccState()['list_id']);
flashSet(
    'success',
    $listChosen
        ? 'Connected to Constant Contact.'
        : 'Connected to Constant Contact. Now choose which mailing list newsletter signups should join.'
);

redirect('index.php');
