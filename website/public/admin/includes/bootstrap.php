<?php
/**
 * Single entry point for every admin script.
 *
 * Before this, 55 of the 56 module files opened with the same three or four
 * require_once lines followed by initSession(). That is not just noise: adding
 * a new shared include meant editing 55 files, and a module that forgot one
 * (csrf.php was missing from 5 of them, working only because some other
 * include happened to pull it in) failed in a way nothing would catch until
 * the missing function was actually called.
 *
 * What this file does NOT do, deliberately: it does not check a capability and
 * it does not call requireLogin(). That mirrors the rule already stated in
 * layout-header.php — auth must stay visible in each page's own first lines,
 * so it can never be the case that adding a page silently makes it protected,
 * or silently leaves it open, because of something happening out of sight.
 * The mechanical setup is shared; the access decision stays where you can read
 * it.
 *
 * Usage — every admin script starts with exactly this:
 *
 *     require_once __DIR__ . '/../../../includes/bootstrap.php';   // or fewer ../
 *     requireCapability('content.edit', '../../dashboard.php');
 *
 * and then requires only the extra pieces it actually uses (trash.php,
 * listing.php, upload.php, ...).
 */

// auth.php already pulls in database.php, errors.php and functions.php, so
// this is the whole dependency chain for the common case.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../../../includes/db.php';

initSession();

/**
 * The POST + CSRF gate that every write handler opens with.
 *
 * This exact block — method check, csrfVerify, the same "Your session
 * expired — please try again." string, the same redirect — was copied into 21
 * files. Copies drift: one of them is the reason the wording differs by a
 * character in two places today. It also meant a new handler could be written
 * with the method check and without the CSRF check, and look right at a glance.
 *
 * Call it as the first statement of a write handler:
 *
 *     requirePostWithCsrf();                 // redirects to index.php
 *     requirePostWithCsrf('../../dashboard.php');
 *
 * Never returns when the request is not a valid, same-origin POST.
 */
function requirePostWithCsrf(string $redirectTo = 'index.php'): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect($redirectTo);
    }

    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        // Logged, because a genuine expiry and a forged cross-site POST are
        // indistinguishable from the user's side but not from ours — and until
        // now a rejected CSRF left no trace at all.
        adminLog('WARN', 'CSRF rejection on ' . ($_SERVER['REQUEST_URI'] ?? '-'));
        flashSet('error', 'Your session expired — please try again.');
        redirect($redirectTo);
    }
}

/**
 * Reads a positive integer id from POST (the id every write handler acts on),
 * or null when it is absent or not a positive number.
 *
 * Replaces `isset($_POST['id']) ? (int) $_POST['id'] : null` repeated in every
 * delete and save handler, which quietly treats "0" and "abc" as the integer 0
 * — falsy, so the handlers happened to behave, but by accident rather than by
 * a decision anyone wrote down.
 */
function postId(string $field = 'id'): ?int
{
    $raw = $_POST[$field] ?? null;

    if ($raw === null || $raw === '') {
        return null;
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT);

    return ($id === false || $id < 1) ? null : $id;
}

/**
 * Same, for a GET id — the form pages read ?id= to decide edit vs create.
 */
function queryId(string $field = 'id'): ?int
{
    $raw = $_GET[$field] ?? null;

    if ($raw === null || $raw === '') {
        return null;
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT);

    return ($id === false || $id < 1) ? null : $id;
}
