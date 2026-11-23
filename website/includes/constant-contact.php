<?php
/**
 * Constant Contact v3 API — the newsletter list integration.
 *
 * What this is for: the footer newsletter form already stores an address in
 * form_submissions, where somebody has to notice it and copy it into Constant
 * Contact by hand. This pushes the address straight onto the mailing list at
 * the moment it is entered, so the CMS inbox becomes the audit trail rather
 * than the to-do list.
 *
 * WHY THIS FILE IS AS LARGE AS IT IS — Constant Contact has no API-key auth and
 * no client-credentials/machine-to-machine flow. Every one of its OAuth2 flows
 * requires a human to authorise in a browser once. So the integration needs:
 *
 *   1. A one-time "Connect" from the admin panel (modules/newsletter/), which
 *      is the only place a browser and a logged-in Constant Contact user meet.
 *   2. A stored refresh token, because the access token dies after 24 hours and
 *      nobody is going to reconnect daily.
 *   3. A lock around the refresh, because Constant Contact ROTATES refresh
 *      tokens: each refresh invalidates the one used. Two concurrent refreshes
 *      means one of them stores a token that is already dead, and the
 *      connection breaks permanently until somebody reconnects by hand. See
 *      ccAccessToken().
 *
 * Split of secrets, deliberately:
 *
 *   - CC_CLIENT_ID / CC_CLIENT_SECRET live in config/config.php. They identify
 *     the application, never change, and are set at deploy time.
 *   - The access and refresh tokens live in the `constant_contact` table,
 *     because they rotate at runtime and config.php is not writable.
 *
 * That split is worth having rather than an accident: admin/database/backup.php
 * produces a downloadable dump, so the refresh token ends up in backups — but a
 * refresh token is useless without the client secret to Basic-auth the token
 * request with, and the client secret is not in the database.
 *
 * Loaded by BOTH tiers (public/api/contact.php and the admin module), so like
 * includes/form-submissions.php it depends on nothing but db.php: no session,
 * no auth, no e(). See ccLog() for how it reaches whichever logger the calling
 * tier happens to have.
 */

require_once __DIR__ . '/db.php';

/** OAuth2 endpoints. Note the authz host differs from the API host. */
const CC_AUTHORIZE_URL = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';
const CC_TOKEN_URL = 'https://authz.constantcontact.com/oauth2/default/v1/token';
const CC_API_BASE = 'https://api.cc.email/v3';

/**
 * Scopes requested at authorisation time.
 *
 * contact_data   — create contacts and read contact lists. Both halves are
 *                  needed: the list picker in the admin panel reads
 *                  /contact_lists, the signup writes /contacts/sign_up_form.
 * offline_access — the only way to be issued a refresh token. Without it the
 *                  integration stops working 24 hours after connecting, which
 *                  is the kind of failure nobody notices until a month of
 *                  signups have quietly gone nowhere.
 *
 * Nothing else is asked for. campaign_data and account_read would let this app
 * read WEF's mailings and account details, and it has no reason to.
 */
const CC_SCOPES = 'contact_data offline_access';

/**
 * Network budget for a call to Constant Contact.
 *
 * These are small on purpose. The signup push happens inside the visitor's own
 * POST, so every second here is a second somebody spends looking at a spinner
 * after typing their email address. The submission is already committed to the
 * database before this runs — a timeout costs a list membership that can be
 * retried from the CMS, never the address itself.
 */
const CC_CONNECT_TIMEOUT = 4;
const CC_TIMEOUT = 8;

/**
 * Refresh the access token when it has less than this long to live.
 *
 * Not zero: a token that passes the "still valid" check with two seconds left
 * expires mid-flight and the request fails for no good reason. Ten minutes is
 * far more than any single call needs and still means one refresh a day.
 */
const CC_REFRESH_MARGIN_SECONDS = 600;

/** Column width of constant_contact.last_error, mirrored from schema.sql. */
const CC_MAX_ERROR_LENGTH = 500;

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

function ccClientId(): string
{
    return defined('CC_CLIENT_ID') ? trim((string) CC_CLIENT_ID) : '';
}

function ccClientSecret(): string
{
    return defined('CC_CLIENT_SECRET') ? trim((string) CC_CLIENT_SECRET) : '';
}

/**
 * The redirect URI, which must match what is registered on the Constant
 * Contact app EXACTLY — scheme, host, path, trailing slash and all. A mismatch
 * is the single most common reason the Connect button ends at an error page,
 * so it is configured explicitly rather than guessed from $_SERVER: guessing
 * would silently produce http:// behind a TLS-terminating proxy, or the wrong
 * host on a domain with a www alias.
 */
function ccRedirectUri(): string
{
    return defined('CC_REDIRECT_URI') ? trim((string) CC_REDIRECT_URI) : '';
}

/**
 * True when config.php carries everything needed to attempt a connection.
 * Distinct from ccIsConnected() — this is "can we even try", that is "has
 * somebody actually authorised us".
 */
function ccIsConfigured(): bool
{
    return ccClientId() !== '' && ccClientSecret() !== '' && ccRedirectUri() !== '';
}

/* -------------------------------------------------------------------------
 * Logging
 * ---------------------------------------------------------------------- */

/**
 * Writes to whichever log the calling tier owns.
 *
 * This file is required from the public endpoint (where publicLog() exists) and
 * from the admin app (where adminLog() does), and neither function is available
 * in the other tier. Rather than pick one and lose half the records, it uses
 * whatever is defined and falls back to PHP's own error_log.
 *
 * Never given a token value to write. See ccRedact().
 */
function ccLog(string $level, string $message): void
{
    $message = 'constant contact: ' . $message;

    if (function_exists('adminLog')) {
        adminLog($level, $message);
        return;
    }

    if (function_exists('publicLog')) {
        publicLog($level, $message);
        return;
    }

    error_log('WEF ' . $level . ': ' . $message);
}

/**
 * Strips anything token-shaped out of text before it is logged or stored in
 * last_error.
 *
 * Constant Contact's error bodies are normally harmless, but the token endpoint
 * echoes request context on some failures, and last_error is rendered on the
 * admin screen. A leaked refresh token in a log file that survives longer than
 * the token does is not a trade worth making for a slightly better error
 * message.
 */
function ccRedact(string $text): string
{
    // JWTs (three base64url segments) and any "...token":"..." JSON pair.
    $text = preg_replace('/\b[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', '[redacted]', $text) ?? $text;
    $text = preg_replace('/("(?:access|refresh|id)_token"\s*:\s*")[^"]*/i', '$1[redacted]', $text) ?? $text;

    return $text;
}

/* -------------------------------------------------------------------------
 * Stored connection state
 * ---------------------------------------------------------------------- */

/**
 * The singleton connection row, created on first read so every caller after
 * this can be a plain UPDATE. Mirrors how settings/index.php seeds
 * site_settings.
 *
 * @return array<string, mixed>
 */
function ccState(): array
{
    $row = dbFetchOne('SELECT * FROM constant_contact WHERE id = 1');

    if ($row === null) {
        dbExecute('INSERT INTO constant_contact (id) VALUES (1)');
        $row = dbFetchOne('SELECT * FROM constant_contact WHERE id = 1') ?? [];
    }

    return $row;
}

/**
 * True when somebody has authorised the app and we still hold a refresh token.
 *
 * Deliberately keyed on the refresh token rather than the access token: an
 * expired access token is a normal Tuesday and ccAccessToken() will replace it,
 * whereas no refresh token means the only fix is a human clicking Connect.
 */
function ccIsConnected(): bool
{
    $state = ccState();

    return !empty($state['refresh_token']);
}

/**
 * True when the integration is fully armed: configured, authorised, and
 * pointed at a list. The signup path checks this before doing any work, so an
 * unconfigured site costs one already-cached row read per newsletter signup and
 * nothing else.
 */
function ccIsActive(): bool
{
    if (!ccIsConfigured()) {
        return false;
    }

    $state = ccState();

    return !empty($state['refresh_token']) && !empty($state['list_id']);
}

/**
 * Records a failure for the admin screen to show. Kept separate from ccLog()
 * because a log file nobody opens is not a status indicator — this is the bit
 * that turns "signups stopped arriving" into a visible red panel.
 */
function ccRecordError(?string $error): void
{
    dbExecute(
        'UPDATE constant_contact
            SET last_error = :error, last_error_at = :at
          WHERE id = 1',
        [
            'error' => $error === null ? null : mb_substr(ccRedact($error), 0, CC_MAX_ERROR_LENGTH),
            'at' => $error === null ? null : date('Y-m-d H:i:s'),
        ]
    );
}

/**
 * Writes a token response from Constant Contact into the singleton row.
 *
 * refresh_token is only overwritten when the response actually carries one.
 * The refresh grant always returns a new one (rotation), but writing an unset
 * key straight through would be the exact bug that empties the column and
 * disconnects the integration on the first quirky response.
 *
 * @param array<string, mixed> $token decoded token endpoint response
 */
function ccStoreTokens(array $token): void
{
    $expiresIn = (int) ($token['expires_in'] ?? 0);
    // Fall back to an hour rather than to "already expired": a missing
    // expires_in should mean one extra refresh, not a token treated as dead the
    // moment it arrives.
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiresIn > 0 ? $expiresIn : 3600));

    $params = [
        'access_token' => (string) ($token['access_token'] ?? ''),
        'expires_at' => $expiresAt,
        'scope' => isset($token['scope']) ? mb_substr((string) $token['scope'], 0, 255) : null,
    ];

    $refreshSql = '';
    if (!empty($token['refresh_token'])) {
        $refreshSql = ', refresh_token = :refresh_token';
        $params['refresh_token'] = (string) $token['refresh_token'];
    }

    dbExecute(
        'UPDATE constant_contact
            SET access_token = :access_token,
                expires_at = :expires_at,
                scope = :scope' . $refreshSql . ',
                last_error = NULL,
                last_error_at = NULL
          WHERE id = 1',
        $params
    );
}

/**
 * Forgets the connection entirely — used by Disconnect, and by a refresh that
 * comes back invalid_grant.
 *
 * list_id survives on purpose. Reconnecting after a token expiry should not
 * also mean re-picking the list, and a list id is not a secret.
 */
function ccClearTokens(string $reason): void
{
    dbExecute(
        'UPDATE constant_contact
            SET access_token = NULL, refresh_token = NULL, expires_at = NULL,
                scope = NULL, connected_at = NULL, connected_by = NULL,
                last_error = :reason, last_error_at = :at
          WHERE id = 1',
        [
            'reason' => mb_substr(ccRedact($reason), 0, CC_MAX_ERROR_LENGTH),
            'at' => date('Y-m-d H:i:s'),
        ]
    );
}

/* -------------------------------------------------------------------------
 * HTTP
 * ---------------------------------------------------------------------- */

/**
 * One HTTP call, with the timeouts and TLS settings this integration wants.
 *
 * Returns a shape rather than throwing, because every caller here has a
 * meaningful "it didn't work" path and none of them should take down the page
 * they are running inside.
 *
 * @param array<string, string> $headers
 * @param string|array<string, mixed>|null $body string = form-encoded, array = JSON
 * @return array{status: int, body: array<string, mixed>, raw: string, error: ?string}
 */
function ccHttp(string $method, string $url, array $headers = [], string|array|null $body = null): array
{
    $fail = static fn(string $message): array => [
        'status' => 0,
        'body' => [],
        'raw' => '',
        'error' => $message,
    ];

    if (!function_exists('curl_init')) {
        return $fail('The PHP cURL extension is not installed on this server.');
    }

    $payload = null;
    if (is_array($body)) {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers['Content-Type'] = 'application/json';
    } elseif (is_string($body)) {
        $payload = $body;
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }

    $headers['Accept'] = 'application/json';

    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => CC_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => CC_TIMEOUT,
        CURLOPT_HTTPHEADER => $headerLines,
        // Certificate verification is left ON explicitly. Turning it off is the
        // usual "fix" for a local cURL with no CA bundle, and it would mean
        // handing WEF's client secret to whoever answers on that hostname.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // A redirect from an OAuth endpoint is not something to follow blindly;
        // it would replay the Authorization header at the new host.
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'WEF-Website/1.0 (+https://wefdallas.org)',
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    // No curl_close(): since PHP 8.0 the handle is an object freed when it goes
    // out of scope, and the call is deprecated.
    unset($ch);

    if ($raw === false) {
        // The most common causes here are a timeout and a missing CA bundle,
        // and both read as "Constant Contact is down" unless the real message
        // survives. It does.
        return $fail($curlError !== '' ? $curlError : 'The request to Constant Contact failed.');
    }

    $decoded = json_decode((string) $raw, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => (string) $raw,
        'error' => null,
    ];
}

/**
 * Turns a Constant Contact error response into one line worth showing an
 * administrator.
 *
 * Their errors come back in three different shapes depending on which service
 * answered — a top-level {error, error_description} from the authorisation
 * server, a list of {error_key, error_message} from the API, or nothing at all
 * behind a bare status code. All three end up as a sentence here rather than as
 * "Error 400", which tells nobody anything.
 *
 * @param array{status: int, body: array<string, mixed>, raw: string, error: ?string} $response
 */
function ccErrorMessage(array $response): string
{
    if ($response['error'] !== null) {
        return ccRedact($response['error']);
    }

    $body = $response['body'];

    if (isset($body['error_description']) && is_string($body['error_description'])) {
        return ccRedact($body['error_description']);
    }

    // The API returns a LIST of errors; report all of them, since "which field"
    // is usually the entire useful content.
    $messages = [];
    foreach ($body as $item) {
        if (is_array($item) && isset($item['error_message']) && is_string($item['error_message'])) {
            $messages[] = $item['error_message'];
        }
    }
    if ($messages !== []) {
        return ccRedact(implode(' ', $messages));
    }

    if (isset($body['error']) && is_string($body['error'])) {
        return ccRedact($body['error']);
    }

    return 'Constant Contact returned HTTP ' . $response['status'] . '.';
}

/* -------------------------------------------------------------------------
 * OAuth2 — authorisation code flow
 * ---------------------------------------------------------------------- */

/**
 * Where the Connect button sends the administrator.
 *
 * $state is generated by the caller and stashed in the session; callback.php
 * refuses to proceed unless the value comes back unchanged. Without it, anyone
 * could hand a logged-in WEF admin a link that attaches THEIR Constant Contact
 * account to WEF's website.
 */
function ccAuthorizeUrl(string $state): string
{
    return CC_AUTHORIZE_URL . '?' . http_build_query([
        'client_id' => ccClientId(),
        'redirect_uri' => ccRedirectUri(),
        'response_type' => 'code',
        'scope' => CC_SCOPES,
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * HTTP Basic credentials for the token endpoint. Constant Contact requires the
 * client secret here rather than as a form field.
 *
 * @return array<string, string>
 */
function ccBasicAuthHeader(): array
{
    return ['Authorization' => 'Basic ' . base64_encode(ccClientId() . ':' . ccClientSecret())];
}

/**
 * Trades the ?code= from the callback for a token pair and stores it.
 *
 * @return array{ok: bool, error: ?string}
 */
function ccExchangeCode(string $code): array
{
    $response = ccHttp('POST', CC_TOKEN_URL, ccBasicAuthHeader(), http_build_query([
        'code' => $code,
        'redirect_uri' => ccRedirectUri(),
        'grant_type' => 'authorization_code',
    ]));

    if ($response['status'] !== 200 || empty($response['body']['access_token'])) {
        $message = ccErrorMessage($response);
        ccLog('ERROR', 'authorisation code exchange failed: ' . $message);

        return ['ok' => false, 'error' => $message];
    }

    if (empty($response['body']['refresh_token'])) {
        // Without offline_access the connection would work for 24 hours and
        // then die silently. Better to refuse the connection now, while
        // somebody is looking at the screen, than to appear to succeed.
        ccLog('ERROR', 'token response carried no refresh token — offline_access was not granted');

        return [
            'ok' => false,
            'error' => 'Constant Contact did not grant offline access, so the connection could not be kept alive. '
                . 'Check that the app has the offline_access scope enabled, then try again.',
        ];
    }

    ccStoreTokens($response['body']);
    ccLog('INFO', 'connected — access token stored');

    return ['ok' => true, 'error' => null];
}

/**
 * A usable access token, refreshing first if the stored one is spent.
 *
 * The FOR UPDATE row lock is the important part of this function. Constant
 * Contact rotates refresh tokens: the moment one is used it stops working and
 * a replacement comes back in the response. Two requests refreshing at once —
 * two visitors subscribing in the same second, a day after the last refresh —
 * would both send the same refresh token, one would win, and the loser would
 * overwrite the good token with a dead one. The integration would then be
 * broken until somebody noticed and reconnected.
 *
 * Holding a transaction open across an HTTP call is not something to do
 * casually. It is right here because the lock is on a single row that nothing
 * else contends for, the call is capped at CC_TIMEOUT seconds, and the failure
 * it prevents is unrecoverable without human intervention.
 *
 * @return array{token: ?string, error: ?string}
 */
function ccAccessToken(): array
{
    return dbTransaction(static function (): array {
        // Re-read INSIDE the lock. A state read before the transaction opened
        // may already be the stale copy another request has just replaced.
        $state = dbFetchOne('SELECT * FROM constant_contact WHERE id = 1 FOR UPDATE');

        if ($state === null || empty($state['refresh_token'])) {
            return ['token' => null, 'error' => 'Constant Contact is not connected.'];
        }

        $expiresAt = $state['expires_at'] !== null ? strtotime((string) $state['expires_at']) : 0;
        $stillGood = !empty($state['access_token'])
            && $expiresAt > time() + CC_REFRESH_MARGIN_SECONDS;

        if ($stillGood) {
            return ['token' => (string) $state['access_token'], 'error' => null];
        }

        $response = ccHttp('POST', CC_TOKEN_URL, ccBasicAuthHeader(), http_build_query([
            'refresh_token' => (string) $state['refresh_token'],
            'grant_type' => 'refresh_token',
        ]));

        if ($response['status'] === 200 && !empty($response['body']['access_token'])) {
            ccStoreTokens($response['body']);
            ccLog('INFO', 'access token refreshed');

            return ['token' => (string) $response['body']['access_token'], 'error' => null];
        }

        $message = ccErrorMessage($response);

        // invalid_grant means the refresh token itself is gone — revoked, past
        // its 180 days, or already rotated away. No amount of retrying fixes
        // that, so the tokens are cleared and the admin screen flips to
        // "reconnect required" instead of failing identically forever.
        $body = $response['body'];
        if ($response['status'] === 400 && ($body['error'] ?? '') === 'invalid_grant') {
            ccClearTokens('The Constant Contact authorisation expired or was revoked. Reconnect to resume syncing.');
            ccLog('ERROR', 'refresh token rejected (invalid_grant) — connection cleared');

            return [
                'token' => null,
                'error' => 'The Constant Contact authorisation expired or was revoked. Reconnect to resume syncing.',
            ];
        }

        // Anything else — a 500 from their side, a timeout — is probably
        // temporary, so the refresh token is kept and the next signup tries
        // again.
        ccRecordError($message);
        ccLog('WARN', 'token refresh failed: ' . $message);

        return ['token' => null, 'error' => $message];
    });
}

/* -------------------------------------------------------------------------
 * API calls
 * ---------------------------------------------------------------------- */

/**
 * An authenticated call against the v3 API.
 *
 * @param array<string, mixed>|null $body
 * @return array{ok: bool, status: int, body: array<string, mixed>, error: ?string}
 */
function ccApi(string $method, string $path, ?array $body = null): array
{
    $auth = ccAccessToken();

    if ($auth['token'] === null) {
        return ['ok' => false, 'status' => 0, 'body' => [], 'error' => $auth['error']];
    }

    $response = ccHttp(
        $method,
        CC_API_BASE . $path,
        ['Authorization' => 'Bearer ' . $auth['token']],
        $body
    );

    $ok = $response['status'] >= 200 && $response['status'] < 300;

    return [
        'ok' => $ok,
        'status' => $response['status'],
        'body' => $response['body'],
        'error' => $ok ? null : ccErrorMessage($response),
    ];
}

/**
 * The mailing lists on the connected account, for the admin list picker.
 *
 * include_count gives each list its subscriber total, which is what lets
 * somebody tell "Newsletter" from "Newsletter (old)" without leaving the page.
 *
 * @return array{ok: bool, lists: list<array{id: string, name: string, count: int}>, error: ?string}
 */
function ccFetchLists(): array
{
    $response = ccApi('GET', '/contact_lists?include_count=true&limit=100');

    if (!$response['ok']) {
        return ['ok' => false, 'lists' => [], 'error' => $response['error']];
    }

    $lists = [];
    foreach ($response['body']['lists'] ?? [] as $list) {
        if (!is_array($list) || empty($list['list_id'])) {
            continue;
        }

        $lists[] = [
            'id' => (string) $list['list_id'],
            'name' => (string) ($list['name'] ?? 'Untitled list'),
            'count' => (int) ($list['membership_count'] ?? 0),
        ];
    }

    return ['ok' => true, 'lists' => $lists, 'error' => null];
}

/**
 * Adds one address to the configured list.
 *
 * Uses /contacts/sign_up_form rather than /contacts for two reasons. The plain
 * create endpoint answers 409 for an address already on file, which for a
 * newsletter box is the normal case — somebody subscribing twice, or an
 * existing donor signing up for updates — and would turn a success into an
 * error. And sign_up_form records the contact as having opted in through a
 * sign-up form, which is what actually happened and is the permission basis
 * Constant Contact's terms require.
 *
 * @return array{ok: bool, error: ?string}
 */
function ccSubscribe(string $email): array
{
    $state = ccState();
    $listId = (string) ($state['list_id'] ?? '');

    if ($listId === '') {
        return ['ok' => false, 'error' => 'No Constant Contact list has been selected.'];
    }

    $response = ccApi('POST', '/contacts/sign_up_form', [
        'email_address' => $email,
        'list_memberships' => [$listId],
    ]);

    if ($response['ok']) {
        // Clears any previous failure, so the admin panel stops showing a
        // problem that has since resolved itself.
        ccRecordError(null);

        return ['ok' => true, 'error' => null];
    }

    ccRecordError($response['error']);

    return ['ok' => false, 'error' => $response['error']];
}

/** Column width of form_submissions.newsletter_sync_error, from schema.sql. */
const CC_MAX_ROW_ERROR_LENGTH = 255;

/**
 * Pushes one stored newsletter signup to the list and records what happened on
 * the row itself.
 *
 * This is the function both callers use — the live signup in
 * public/api/contact.php and the Retry button in the Submissions module — so
 * the two can never drift into treating the same failure differently.
 *
 * IT NEVER THROWS. By the time this runs the address is already committed to
 * form_submissions and the visitor is owed a success message. Constant Contact
 * being slow, unreachable, or in the middle of an outage is not a reason to
 * show somebody an error for something that did work, so every failure path
 * ends in a recorded reason rather than an exception. That is also why the
 * outcome is written to the row: a failure that is only logged is a failure
 * nobody acts on, and the Submissions list can show a retry button for a
 * failure it can see.
 *
 * @return array{ok: bool, error: ?string}
 */
function ccSyncSubmission(int $submissionId, string $email): array
{
    try {
        if (!ccIsActive()) {
            // Not an error worth recording on the row. Nothing is wrong with
            // this signup — the integration simply is not switched on, and
            // stamping every address with "not configured" would bury a real
            // failure among hundreds of these.
            return ['ok' => false, 'error' => null];
        }

        $result = ccSubscribe($email);

        dbExecute(
            'UPDATE form_submissions
                SET newsletter_synced_at = :synced, newsletter_sync_error = :error
              WHERE id = :id',
            [
                'synced' => $result['ok'] ? date('Y-m-d H:i:s') : null,
                'error' => $result['ok'] ? null : mb_substr((string) $result['error'], 0, CC_MAX_ROW_ERROR_LENGTH),
                'id' => $submissionId,
            ]
        );

        if ($result['ok']) {
            ccLog('INFO', sprintf('submission #%d added to the mailing list', $submissionId));
        } else {
            ccLog('WARN', sprintf('submission #%d not added: %s', $submissionId, (string) $result['error']));
        }

        return $result;
    } catch (Throwable $e) {
        // Catching Throwable rather than Exception is deliberate: a TypeError
        // or a database Error here would otherwise escape into the public
        // endpoint and turn a successful signup into a 503 page.
        $message = ccRedact($e->getMessage());
        ccLog('ERROR', sprintf('submission #%d sync crashed: %s', $submissionId, $message));

        // Best-effort — if the database is what failed, this will fail too, and
        // that must not become a second uncaught error.
        try {
            dbExecute(
                'UPDATE form_submissions SET newsletter_sync_error = :error WHERE id = :id',
                ['error' => mb_substr($message, 0, CC_MAX_ROW_ERROR_LENGTH), 'id' => $submissionId]
            );
        } catch (Throwable) {
            // Nothing further to try; the log line above is the record.
        }

        return ['ok' => false, 'error' => $message];
    }
}
