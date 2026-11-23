<?php
/**
 * POST api/checkout.php: turns a donor's choices into a Stripe Checkout
 * Session and returns its hosted-page URL as { "url": "..." }.
 *
 * Port of the tested Next.js app/api/checkout/route.ts. Money rules:
 *  - The browser sends choices (ids, counts, and for "any amount" / "other
 *    amount" the amount itself). The price is worked out HERE from
 *    includes/donate/config.php.
 *  - Everything is validated first; a custom amount must be $1 to $10,000.
 *  - Card details never touch this server: the donor pays on Stripe's page.
 *
 * Responses: 200 { url }, 405 not POST, 415 not JSON, 400 unreadable,
 * 422 { error, fieldErrors }, 502 Stripe failed (nothing charged).
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/content.php'; // siteSettings() for the contact email
require_once __DIR__ . '/../../includes/donate/stripe.php';

/** @param array<string, mixed> $body */
function donateRespond(int $status, array $body): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    donateRespond(405, ['error' => 'Unsupported request.']);
}

// A plain HTML form on another site cannot send JSON, so this blocks
// cross-site form posts without needing a token.
if (!str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    donateRespond(415, ['error' => 'Unsupported request.']);
}

$raw = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($raw)) {
    donateRespond(400, ['error' => 'Unreadable request.']);
}

$result = donateValidateDonation($raw);
if (!$result['ok']) {
    donateRespond(422, ['error' => 'Please check the highlighted fields.', 'fieldErrors' => (object) $result['errors']]);
}

$cfg = donateConfig();
$donation = $result['donation'];
$quote = donateBuildQuote($donation['selection']);
$metadata = donateBuildMetadata($donation, $quote);
$baseUrl = donateBaseUrl();
$recurring = $quote['mode'] === 'subscription';
$receipt = donateReceiptDescription($quote);
$footer = donateInvoiceFooter();

$lineItems = [];
foreach ($quote['lines'] as $line) {
    $priceData = [
        'currency' => $cfg['currency'],
        'unit_amount' => $line['unitAmountCents'],
        'product_data' => ['name' => $line['name'], 'description' => $line['description']],
    ];
    if ($recurring) {
        $priceData['recurring'] = ['interval' => 'month'];
    }
    $lineItems[] = ['quantity' => $line['quantity'], 'price_data' => $priceData];
}

// Shown on Checkout above the pay button.
$message = implode(' ', array_filter([
    "{$cfg['org']['legalName']} is a 501(c)(3) nonprofit (EIN {$cfg['org']['ein']}). {$cfg['legal']['noGoodsOrServices']}",
    $recurring && $quote['installments']
        ? 'You will be charged ' . donateMoney($quote['perPaymentCents']) . " today and every month after that, {$quote['installments']} payments in all. It then stops by itself."
        : '',
    $recurring && !$quote['installments']
        ? 'You will be charged ' . donateMoney($quote['perPaymentCents']) . ' today and every month until you ask us to stop.'
        : '',
]));

$params = [
    'mode' => $quote['mode'],
    'line_items' => $lineItems,
    'customer_email' => $donation['details']['donorEmail'],
    'metadata' => $metadata,
    // Stripe can offer donors their local currency. Gifts and tax receipts
    // must stay in US dollars, so switch that off.
    'adaptive_pricing' => ['enabled' => false],
    'custom_text' => ['submit' => ['message' => $message]],
    'success_url' => $baseUrl . 'donate-thank-you?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $baseUrl . 'donate?canceled=1',
];

if ($recurring) {
    // Fixed-term plans are cut short by the webhook once the first payment
    // succeeds (see donateLimitToInstallments()).
    $params['subscription_data'] = [
        'metadata' => $metadata,
        'description' => $receipt,
        'invoice_settings' => ['description' => $receipt, 'footer' => $footer],
    ];
} else {
    $params['submit_type'] = 'donate';
    $params['customer_creation'] = 'always';
    $params['payment_intent_data'] = ['description' => $receipt, 'metadata' => $metadata];
    if ($cfg['invoiceForOneTimeGifts']) {
        $params['invoice_creation'] = [
            'enabled' => true,
            'invoice_data' => ['description' => $receipt, 'footer' => $footer, 'metadata' => $metadata],
        ];
    }
}

try {
    $session = donateStripeRequest('POST', 'checkout/sessions', $params);
    $url = (string) ($session['url'] ?? '');
    if ($url === '') {
        throw new DonateStripeError('Stripe did not return a checkout URL.');
    }
    donateRespond(200, ['url' => $url]);
} catch (Throwable $e) {
    // Log what went wrong (Stripe's message holds no card or donor data),
    // but tell the donor nothing technical.
    donateLog('could not create checkout session: ' . $e->getMessage());
    donateRespond(502, ['error' => 'We could not start the payment. Nothing was charged. Please try again in a moment.']);
}
