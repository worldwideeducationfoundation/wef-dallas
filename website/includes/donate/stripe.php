<?php
/**
 * Stripe, without an SDK. The site has no Composer and no third-party code
 * (see includes/autoload.php for why), so this talks to Stripe's REST API
 * with cURL, the same way includes/constant-contact.php talks to Constant
 * Contact. It covers exactly what the donate page needs:
 *
 *   - create a Checkout Session            (api/checkout.php)
 *   - read a Checkout Session back         (donate-thank-you.php)
 *   - verify a webhook signature           (api/stripe-webhook.php)
 *   - end a monthly plan after N payments  (donateLimitToInstallments)
 *
 * Port of the tested Next.js lib/donate/stripe.ts and fixed-term.ts.
 *
 * The secret key is read from config/config.php (STRIPE_SECRET_KEY) or the
 * environment, here and nowhere else. It is never logged and never sent to
 * the browser.
 */

require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/../assets.php'; // siteBasePath()

/**
 * The API version the tested Next.js code ran on (the stripe-node SDK default
 * it was built with). Pinned so a change to the account's default version
 * cannot change behaviour under us. Override with STRIPE_API_VERSION in
 * config.php if Stripe ever retires it.
 */
const DONATE_STRIPE_API_VERSION = '2026-11-18.endive';

/** Thrown for any failed Stripe call. The message holds no card or donor data. */
final class DonateStripeError extends RuntimeException
{
}

function donateStripeConfigured(): bool
{
    return donateSetting('STRIPE_SECRET_KEY') !== '';
}

/**
 * Stripe's form encoding: nested arrays become a[b][0][c]=..., booleans are
 * "true"/"false", and null values are left out (as the SDK does with
 * undefined).
 */
function donateStripeEncode(array $params, string $prefix = ''): array
{
    $pairs = [];
    foreach ($params as $key => $value) {
        $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
        if ($value === null) {
            continue;
        }
        if (is_array($value)) {
            $pairs = array_merge($pairs, donateStripeEncode($value, $name));
        } elseif (is_bool($value)) {
            $pairs[] = [$name, $value ? 'true' : 'false'];
        } else {
            $pairs[] = [$name, (string) $value];
        }
    }
    return $pairs;
}

/**
 * One call to the Stripe API. Returns the decoded object or throws.
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function donateStripeRequest(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
{
    $key = donateSetting('STRIPE_SECRET_KEY');
    if ($key === '') {
        throw new DonateStripeError('STRIPE_SECRET_KEY is not set.');
    }
    if (!function_exists('curl_init')) {
        throw new DonateStripeError('The PHP cURL extension is not installed on this server.');
    }

    $query = implode('&', array_map(
        static fn(array $p): string => rawurlencode($p[0]) . '=' . rawurlencode($p[1]),
        donateStripeEncode($params)
    ));

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    if ($method === 'GET' && $query !== '') {
        $url .= '?' . $query;
    }

    $version = donateSetting('STRIPE_API_VERSION') ?: DONATE_STRIPE_API_VERSION;
    $headers = [
        'Authorization: Bearer ' . $key,
        'Stripe-Version: ' . $version,
        'Accept: application/json',
        'User-Agent: wef-donate-php/1.0',
    ];
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($method !== 'GET') {
        $opts[CURLOPT_POSTFIELDS] = $query;
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    unset($ch);

    if ($raw === false) {
        throw new DonateStripeError('Could not reach Stripe: ' . ($curlError ?: 'unknown network error'));
    }

    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        throw new DonateStripeError("Stripe answered HTTP {$status} with an unreadable body.");
    }
    if ($status < 200 || $status >= 300) {
        $err = is_array($body['error'] ?? null) ? $body['error'] : [];
        throw new DonateStripeError(sprintf(
            'Stripe HTTP %d %s: %s',
            $status,
            (string) ($err['type'] ?? 'error'),
            (string) ($err['message'] ?? 'no message')
        ));
    }

    return $body;
}

/**
 * Verifies a webhook's Stripe-Signature header against the RAW body, as the
 * SDK's constructEvent() does: HMAC-SHA256 of "timestamp.body" with the
 * endpoint secret, compared in constant time, within a 5 minute tolerance.
 *
 * @return array<string, mixed> The event.
 */
function donateStripeConstructEvent(string $payload, string $header, string $secret, int $tolerance = 300): array
{
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = ctype_digit($kv[1]) ? (int) $kv[1] : null;
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }
    if ($timestamp === null || $signatures === []) {
        throw new DonateStripeError('Malformed signature header.');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    $match = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $match = true;
        }
    }
    if (!$match) {
        throw new DonateStripeError('No matching signature.');
    }
    if (abs(time() - $timestamp) > $tolerance) {
        throw new DonateStripeError('Timestamp outside the tolerance zone.');
    }

    $event = json_decode($payload, true);
    if (!is_array($event)) {
        throw new DonateStripeError('Unreadable event body.');
    }
    return $event;
}

/**
 * Makes a monthly Checkout subscription stop by itself after $totalPayments.
 *
 * Why a subscription schedule: Checkout cannot set an end date, and Stripe's
 * documented way to end a subscription after a set number of billing periods
 * is a schedule whose end_behavior is "cancel". (cancel_at set from the
 * webhook lands exactly on a renewal and can race the 13th invoice.)
 *
 * The first payment is already made when Checkout completes, so: phase 1 =
 * the month just paid, phase 2 = the remaining months. Stripe then cancels
 * the subscription at the end of phase 2.
 *
 * Safe to run more than once for the same subscription (webhooks retry): an
 * existing schedule is left alone, and the create call is idempotent.
 *
 * @return string "created" or "already-limited"
 */
function donateLimitToInstallments(string $subscriptionId, int $totalPayments): string
{
    $subscription = donateStripeRequest('GET', 'subscriptions/' . rawurlencode($subscriptionId));
    if (!empty($subscription['schedule'])) {
        return 'already-limited';
    }

    $schedule = donateStripeRequest(
        'POST',
        'subscription_schedules',
        ['from_subscription' => $subscriptionId],
        'wef-fixed-term-' . $subscriptionId
    );

    $current = $schedule['phases'][0] ?? null;
    if (!is_array($current)) {
        throw new DonateStripeError('Subscription schedule has no current phase.');
    }

    $idOf = static fn(mixed $v): ?string => is_array($v) ? ($v['id'] ?? null) : (is_string($v) ? $v : null);

    // Carry over everything a phase would otherwise reset when we update it.
    $items = [];
    foreach ($current['items'] ?? [] as $item) {
        $items[] = ['price' => $idOf($item['price'] ?? null), 'quantity' => $item['quantity'] ?? 1];
    }
    $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
    $shared = [
        'items' => $items,
        'metadata' => $metadata ?: null,
        'currency' => $current['currency'] ?? null,
        'description' => $current['description'] ?? null,
        'collection_method' => $current['collection_method'] ?? null,
        'default_payment_method' => $idOf($current['default_payment_method'] ?? null),
        'invoice_settings' => [
            'description' => $subscription['description'] ?? null,
            'footer' => donateInvoiceFooter(),
        ],
    ];

    donateStripeRequest('POST', 'subscription_schedules/' . rawurlencode((string) $schedule['id']), [
        'end_behavior' => 'cancel',
        'metadata' => ['source' => 'wef-donate', 'total_payments' => (string) $totalPayments],
        'phases' => [
            // Phase 1 keeps its original dates.
            $shared + ['start_date' => $current['start_date'], 'end_date' => $current['end_date']],
            // Phase 2: the payments still to come.
            $shared + ['duration' => ['interval' => 'month', 'interval_count' => $totalPayments - 1]],
        ],
    ]);

    return 'created';
}

/**
 * The site's public base URL with a trailing slash, for Stripe's return
 * links. DONATE_BASE_URL in config.php wins (set it in production); otherwise
 * the address the donor is on.
 */
function donateBaseUrl(): string
{
    $configured = donateSetting('DONATE_BASE_URL');
    if ($configured !== '') {
        return rtrim($configured, '/') . '/';
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
        $host = 'localhost';
    }

    // siteBasePath() from an api/ script ends in api/; the pages sit one up.
    $base = siteBasePath();
    if (str_ends_with($base, '/api/')) {
        $base = substr($base, 0, -4);
    }

    return ($https ? 'https' : 'http') . '://' . $host . $base;
}

/**
 * Appends a line to storage/logs/donate.log (storage/ is never served).
 * Callers pass only a safe summary: no names, emails or card details.
 *
 * @param array<string, mixed> $context
 */
function donateLog(string $message, array $context = []): void
{
    $dir = __DIR__ . '/../../storage/logs';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message
        . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '') . "\n";
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('WEF donate: ' . $line);
        return;
    }
    if (@file_put_contents($dir . '/donate.log', $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('WEF donate: ' . $line);
    }
}
