<?php
/**
 * POST api/stripe-webhook.php: Stripe calls this when something happens.
 * Port of the tested Next.js app/api/stripe/webhook/route.ts.
 *
 * Set it up in the Stripe Dashboard -> Developers -> Webhooks:
 *   https://<site>/api/stripe-webhook.php, event checkout.session.completed
 * and put its signing secret in STRIPE_WEBHOOK_SECRET (config/config.php).
 * WITHOUT THIS WEBHOOK THE 12- AND 24-MONTH CENTER PLANS NEVER STOP.
 *
 * The signature is checked against the RAW body before anything else, so
 * only Stripe can make this do anything. Only a safe summary is logged: no
 * names, emails or card details. A 500 makes Stripe retry (for up to 3 days).
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/content.php'; // siteSettings() for the invoice footer
require_once __DIR__ . '/../../includes/donate/stripe.php';

/** @param array<string, mixed> $body */
function webhookRespond(int $status, array $body): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    webhookRespond(405, ['error' => 'Method not allowed.']);
}

$secret = donateSetting('STRIPE_WEBHOOK_SECRET');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if ($secret === '' || $signature === '') {
    webhookRespond(400, ['error' => 'Webhook is not configured.']);
}

// The signature covers the exact bytes Stripe sent, so read the raw body.
$payload = (string) file_get_contents('php://input');

try {
    $event = donateStripeConstructEvent($payload, $signature, $secret);
} catch (Throwable $e) {
    webhookRespond(400, ['error' => 'Invalid signature.']);
}

$type = (string) ($event['type'] ?? '');
$eventId = (string) ($event['id'] ?? '');

try {
    if ($type === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];
        $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];

        // Some other checkout on this Stripe account: not ours.
        if (($meta['source'] ?? '') === 'wef-donate') {
            donateLog('checkout completed', [
                'session' => $session['id'] ?? null,
                'mode' => $session['mode'] ?? null,
                'paymentStatus' => $session['payment_status'] ?? null,
                'amountTotal' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
                'donationType' => $meta['donation_type'] ?? null,
                'centers' => $meta['centers'] ?? null,
                'parts' => $meta['parts'] ?? null,
                'nameMyCenter' => $meta['name_my_center'] ?? null,
                'hasHonor' => !empty($meta['honor_type']),
                'updatesOptIn' => $meta['updates_opt_in'] ?? null,
            ]);

            // Fixed-term monthly plans: stop after the right number of payments.
            $installments = ctype_digit((string) ($meta['installments'] ?? '')) ? (int) $meta['installments'] : 0;
            $sub = $session['subscription'] ?? null;
            $subscriptionId = is_array($sub) ? ($sub['id'] ?? null) : $sub;
            if (($session['mode'] ?? '') === 'subscription' && is_string($subscriptionId) && $subscriptionId !== '' && $installments > 1) {
                $result = donateLimitToInstallments($subscriptionId, $installments);
                donateLog('fixed-term plan', ['subscription' => $subscriptionId, 'payments' => $installments, 'result' => $result]);
            }
        }
    }
} catch (Throwable $e) {
    donateLog("webhook {$type} {$eventId} failed: " . $e->getMessage());
    webhookRespond(500, ['error' => 'Webhook handler failed.']);
}

webhookRespond(200, ['received' => true]);
