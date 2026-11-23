<?php
/**
 * Receives the public contact and volunteer forms.
 *
 * Until now both forms were decoration: js/main.js caught the submit event,
 * called preventDefault(), showed "✓ Message Sent!" after a setTimeout and
 * reset the fields. Nothing was posted, stored or emailed — every enquiry a
 * visitor sent since the site launched was discarded, and they were told it
 * had arrived. This is the endpoint that makes the promise true.
 *
 * Serves two callers with one code path:
 *
 *   - The fetch() in js/main.js, which sends Accept: application/json and gets
 *     a JSON body back, including per-field errors so the form can mark the
 *     specific input that needs fixing.
 *   - A plain browser form POST, for anyone with JavaScript off or blocked.
 *     That path ends in a redirect back to the form's own page carrying a
 *     result code, because the public site has no session and nothing to hold
 *     a flash message in.
 *
 * The redirect target is looked up from SUBMISSION_FORMS by form_type, never
 * read from the request. A `return_to` field would be the obvious way to do
 * it and is exactly how open redirects get built.
 */

// Error handling first: an uncaught PDOException here would otherwise print a
// trace containing the database credentials to whoever posted the form.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/form-submissions.php';

/** Where a no-JS visitor is sent back to. Both files sit one level down. */
const FORM_PAGE_BASE = '../';

/**
 * The submitter's address, for the flood check and the stored row.
 *
 * Named differently from the admin app's identical clientIp() rather than
 * shared with it: admin/includes/functions.php is loaded on every CMS request
 * and this file on every public one, and the admin pages that read submissions
 * load both — a second declaration of the same name would be a fatal redeclare
 * the moment those two paths met.
 *
 * REMOTE_ADDR only. X-Forwarded-For is attacker-controlled unless the proxy in
 * front is known and trusted, and trusting it here would let one client fake a
 * new address per request and walk straight past the per-IP limit below.
 */
function clientIpAddress(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * True when the caller wants JSON rather than a redirect. Driven by the
 * Accept header the fetch() sets explicitly, so a normal browser form post —
 * which asks for text/html — always takes the redirect path.
 */
function wantsJson(): bool
{
    return str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/**
 * Ends the request one way or the other.
 *
 * $code is a short machine-readable result ('ok', 'invalid', 'throttled', …).
 * The redirect path carries only that code — not the message and not the
 * field errors — because the receiving page renders its own copy for each
 * code, and a message passed through the query string is a message an
 * attacker can choose to have shown on our page.
 *
 * @param array<string, string> $fieldErrors
 */
function respond(int $status, string $code, string $message, string $formType = 'contact', array $fieldErrors = []): never
{
    // The buffer bootstrap.php opened may hold stray output (a warning from an
    // include, a stray newline). Dropping it keeps the JSON body parseable and
    // the redirect header sendable.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (wantsJson()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        // Nothing here is cacheable and a cached "already sent" would be
        // actively confusing on a shared machine.
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => $code === 'ok',
            'code' => $code,
            'message' => $message,
            'errors' => (object) $fieldErrors,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $page = submissionForm($formType)['page'] ?? SUBMISSION_FORMS['contact']['page'];

    // 303 rather than 302: this was a POST, and 303 is the status that tells
    // the browser to follow with GET, so a refresh on the destination cannot
    // resubmit the message.
    http_response_code(303);
    header('Location: ' . FORM_PAGE_BASE . $page . '?form=' . urlencode($code) . '#contact-form');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'method', 'This endpoint only accepts form submissions.');
}

$formType = (string) ($_POST['form_type'] ?? 'contact');

/**
 * Honeypot. `website` is hidden from sight and from screen readers on the
 * form; a human never fills it in and most bots fill every field they find.
 *
 * Answered with a plain success on purpose. Telling a bot it was detected is
 * free feedback for tuning the next attempt, and the cost of being wrong — a
 * password manager that auto-filled the decoy — is a message silently lost.
 * That is the trade this makes, and it is why the honeypot is the only
 * silent rejection here: everything else fails loudly with a reason.
 */
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    publicLog('INFO', 'form honeypot triggered from ip=' . clientIpAddress());
    respond(200, 'ok', 'Thank you — your message has been received.', $formType);
}

if (submissionFloodLimitReached(clientIpAddress())) {
    publicLog('WARN', 'form flood limit hit from ip=' . clientIpAddress());
    respond(429, 'throttled', 'You have sent several messages already. Please wait a few minutes before sending another.', $formType);
}

$check = submissionValidate($_POST);

if ($check['errors'] !== []) {
    respond(
        422,
        'invalid',
        'Please check the highlighted fields and try again.',
        $formType,
        $check['errors']
    );
}

$id = submissionInsert(
    $check['values'],
    clientIpAddress(),
    $_SERVER['HTTP_USER_AGENT'] ?? null
);

publicLog('INFO', sprintf(
    'form submission #%d stored (%s) from %s',
    $id,
    $check['values']['form_type'],
    $check['values']['email']
));

/**
 * Newsletter signups are also pushed to the Constant Contact mailing list.
 *
 * ORDER MATTERS: this happens AFTER the insert above, never instead of it. The
 * database row is the record of what the visitor asked for; the list membership
 * is a consequence of it. If Constant Contact is down, misconfigured or slow,
 * the address is still safely stored and the CMS shows the failure with a retry
 * button — which is the whole reason ccSyncSubmission() records the outcome on
 * the row rather than only logging it.
 *
 * ccSyncSubmission() is documented as never throwing, and the require is
 * conditional, so an integration that is switched off costs one cheap row read
 * and nothing else. It is loaded here rather than at the top of the file
 * because the contact and volunteer forms have no use for it at all.
 *
 * This is a blocking call inside the visitor's own POST, capped by
 * CC_CONNECT_TIMEOUT + CC_TIMEOUT. That is the deliberate simple choice: a
 * background queue would need cron, a retry table and a way to notice when the
 * worker stops, and this site has none of those. If signup volume ever makes
 * the wait noticeable, moving this call to a queue drained by cron is the
 * upgrade, and nothing else here has to change.
 */
if ($check['values']['form_type'] === 'newsletter') {
    require_once __DIR__ . '/../../includes/constant-contact.php';
    ccSyncSubmission($id, $check['values']['email']);
}

respond(
    201,
    'ok',
    submissionSuccessMessage($check['values']['form_type']),
    $check['values']['form_type']
);
