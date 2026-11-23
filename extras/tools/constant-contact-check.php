<?php
/**
 * Diagnostic for the Constant Contact newsletter integration.
 *
 * Answers, without touching anybody's mailing list, the questions that come up
 * when signups are not arriving:
 *
 *   - Are the credentials in config/config.php present, and does Constant
 *     Contact actually accept them?
 *   - Is the site connected, and to which list?
 *   - Is the server able to reach Constant Contact at all? (A blocked outbound
 *     HTTPS connection or a missing CA bundle looks exactly like "the
 *     integration is broken" from the admin panel.)
 *   - How many stored newsletter signups never made it to the list?
 *
 * Usage, from the project root:
 *   php tools/constant-contact-check.php
 *
 * CLI only. It prints configuration state, so it must never be reachable over
 * HTTP — the guard below is the same one admin/database/migrate.php uses.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../website/includes/constant-contact.php';

$problems = 0;

function line(string $label, string $value): void
{
    printf("  %-26s %s\n", $label . ':', $value);
}

echo "\nConstant Contact integration\n";
echo str_repeat('=', 60) . "\n\n";

/* --- 1. Configuration ------------------------------------------------- */

echo "Configuration (config/config.php)\n";
line('CC_CLIENT_ID', ccClientId() !== '' ? 'set (' . substr(ccClientId(), 0, 8) . '…)' : 'MISSING');
line('CC_CLIENT_SECRET', ccClientSecret() !== '' ? 'set (' . strlen(ccClientSecret()) . ' characters)' : 'MISSING');
line('CC_REDIRECT_URI', ccRedirectUri() !== '' ? ccRedirectUri() : 'MISSING');

if (!ccIsConfigured()) {
    echo "\n  ✗ Not configured. See the Constant Contact section of config/config.sample.php.\n";
    echo "    Newsletter signups are still being saved — they are just not being\n";
    echo "    forwarded to a mailing list.\n\n";
    exit(1);
}

echo "\n";

/* --- 2. Stored connection --------------------------------------------- */

$state = ccState();
$connected = !empty($state['refresh_token']);

echo "Connection\n";
line('Status', $connected ? 'connected' : 'NOT CONNECTED');
line('Connected at', (string) ($state['connected_at'] ?? '—'));
line('Access token expires', (string) ($state['expires_at'] ?? '—'));
line('Granted scopes', (string) ($state['scope'] ?? '—'));
line('Target list', $state['list_id'] ? (string) $state['list_name'] . ' (' . $state['list_id'] . ')' : 'NONE CHOSEN');

if (!empty($state['last_error'])) {
    line('Last error', (string) $state['last_error']);
    line('Last error at', (string) ($state['last_error_at'] ?? '—'));
}

if (!$connected) {
    $problems++;
    echo "\n  ✗ Nobody has authorised this site yet.\n";
    echo "    Sign in to the CMS -> Newsletter -> Connect to Constant Contact.\n";
} elseif (empty($state['list_id'])) {
    $problems++;
    echo "\n  ✗ Connected, but no mailing list has been chosen, so nothing is being added.\n";
    echo "    CMS -> Newsletter -> Mailing list.\n";
}

echo "\n";

/* --- 3. Reachability + credentials ------------------------------------ */

echo "Reachability\n";

/**
 * A refresh_token grant using a deliberately invalid token.
 *
 * Nothing is created, changed or consumed — but the SHAPE of the refusal is
 * exactly what is needed here. invalid_client means Constant Contact rejected
 * the API key or secret; invalid_grant means it accepted both and refused only
 * the fake token, which is the healthy answer.
 */
$probe = ccHttp('POST', CC_TOKEN_URL, ccBasicAuthHeader(), http_build_query([
    'refresh_token' => 'diagnostic-probe-not-a-real-token',
    'grant_type' => 'refresh_token',
]));

if ($probe['status'] === 0) {
    $problems++;
    line('Outbound HTTPS', 'FAILED');
    line('cURL said', (string) $probe['error']);
    echo "\n  ✗ This server could not reach " . parse_url(CC_TOKEN_URL, PHP_URL_HOST) . ".\n";
    echo "    Usual causes: outbound HTTPS blocked by the host's firewall, no DNS,\n";
    echo "    or PHP having no CA bundle (set curl.cainfo in php.ini).\n";
} else {
    line('Outbound HTTPS', 'ok (HTTP ' . $probe['status'] . ')');
    $error = (string) ($probe['body']['error'] ?? '');

    if ($error === 'invalid_client' || $probe['status'] === 401) {
        $problems++;
        line('Credentials', 'REJECTED');
        echo "\n  ✗ Constant Contact did not accept CC_CLIENT_ID / CC_CLIENT_SECRET.\n";
        echo "    Re-copy the API key, and regenerate the app secret if unsure —\n";
        echo "    the secret is only displayed once when it is created.\n";
    } elseif ($error === 'invalid_grant') {
        line('Credentials', 'accepted (rejected only the probe token, as expected)');
    } else {
        line('Credentials', 'inconclusive — error=' . ($error !== '' ? $error : '(none)'));
    }
}

echo "\n";

/* --- 4. Live call ------------------------------------------------------ */

if ($connected) {
    echo "Live API call (GET /contact_lists)\n";
    $lists = ccFetchLists();

    if (!$lists['ok']) {
        $problems++;
        line('Result', 'FAILED');
        line('Reason', (string) $lists['error']);
    } else {
        line('Result', 'ok — ' . count($lists['lists']) . ' list(s) on the account');
        foreach ($lists['lists'] as $list) {
            printf("      %-40s %8s contacts  %s\n", $list['name'], number_format($list['count']), $list['id']);
        }

        $selected = (string) ($state['list_id'] ?? '');
        if ($selected !== '' && !in_array($selected, array_column($lists['lists'], 'id'), true)) {
            $problems++;
            echo "\n  ✗ The configured list id is not on this account any more. Choose another\n";
            echo "    in CMS -> Newsletter, or signups will keep failing.\n";
        }
    }
    echo "\n";
}

/* --- 5. Backlog -------------------------------------------------------- */

echo "Stored newsletter signups\n";

$total = (int) dbFetchColumn("SELECT COUNT(*) FROM form_submissions WHERE form_type = 'newsletter' AND deleted_at IS NULL", [], 0);
$synced = (int) dbFetchColumn("SELECT COUNT(*) FROM form_submissions WHERE form_type = 'newsletter' AND deleted_at IS NULL AND newsletter_synced_at IS NOT NULL", [], 0);
$failed = (int) dbFetchColumn("SELECT COUNT(*) FROM form_submissions WHERE form_type = 'newsletter' AND deleted_at IS NULL AND newsletter_synced_at IS NULL AND newsletter_sync_error IS NOT NULL", [], 0);

line('Total', (string) $total);
line('On the mailing list', (string) $synced);
line('Failed', (string) $failed);
line('Never attempted', (string) ($total - $synced - $failed));

if ($total - $synced > 0) {
    echo "\n  " . ($total - $synced) . " signup(s) are not on the list. Each can be sent from\n";
    echo "  CMS -> Form Submissions -> open the row -> \"Add this address to the mailing list\".\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo $problems === 0
    ? "No problems found.\n\n"
    : $problems . " problem(s) found — see the ✗ marks above.\n\n";

exit($problems === 0 ? 0 : 1);
