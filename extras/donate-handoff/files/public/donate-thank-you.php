<?php
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/assets.php';
require_once __DIR__ . '/../includes/donate/stripe.php';

/*
 * Where Stripe Checkout sends the donor after paying:
 *   donate-thank-you?session_id={CHECKOUT_SESSION_ID}
 * Port of the tested Next.js app/donate/thank-you/page.tsx.
 *
 * Reads the finished payment back from Stripe so the page can say what was
 * given. Only a payment made through our form is shown, and only the first
 * name, amount and dedication: never the email address. An unknown or
 * expired id just gets the general thank-you.
 */

/** @return array{firstName: string, line: string, honor: string, processing: bool}|null */
function donateLoadGift(?string $sessionId): ?array
{
    if ($sessionId === null || !preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId) || !donateStripeConfigured()) {
        return null;
    }
    try {
        $session = donateStripeRequest('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    } catch (Throwable $e) {
        return null;
    }
    $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    if (($meta['source'] ?? '') !== 'wef-donate' || ($session['status'] ?? '') !== 'complete') {
        return null;
    }

    $amount = donateMoney((int) ($session['amount_total'] ?? 0));
    $installments = (int) ($meta['installments'] ?? 0);
    $centers = (int) ($meta['centers'] ?? 0);
    $forCenters = $centers > 0 ? " for {$centers} " . ($centers === 1 ? 'center' : 'centers') : '';

    $line = "{$amount}{$forCenters}, one time";
    if (($session['mode'] ?? '') === 'subscription') {
        $line = $installments > 1
            ? "{$amount} a month{$forCenters}, for {$installments} months"
            : "{$amount} a month{$forCenters}";
    }

    $honor = '';
    if (($meta['honor_name'] ?? '') !== '') {
        $honor = (($meta['honor_type'] ?? '') === 'memory' ? 'In memory of' : 'In honor of') . ' ' . $meta['honor_name'];
    }

    return [
        'firstName' => explode(' ', (string) ($meta['donor_name'] ?? ''))[0],
        'line' => $line,
        'honor' => $honor,
        'processing' => ($session['payment_status'] ?? '') === 'unpaid',
    ];
}

$sessionId = isset($_GET['session_id']) && is_string($_GET['session_id']) ? $_GET['session_id'] : null;
$gift = donateLoadGift($sessionId);
$cfg = donateConfig();
$org = $cfg['org'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  $seoTitle = 'Thank you — ' . $org['legalName'];
  $seoDescription = 'Thank you for your gift to ' . $org['legalName'] . '.';
  $seoNoIndex = true;
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
  <link rel="stylesheet" href="<?= e(asset('css/donate.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content" class="wef wef-thanks">
    <div class="wef-wrap">
      <div class="wef-card wef-thanks-card" role="status">
        <p class="wef-eyebrow wef-muted">Thank you</p>
        <h1 class="wef-thanks-title"><?= ($gift && $gift['firstName'] !== '') ? 'Thank you, ' . e($gift['firstName']) . '.' : 'Thank you for your gift.' ?></h1>

        <?php if ($gift): ?>
        <p class="wef-thanks-gift">
          <strong><?= e($gift['line']) ?></strong>
          <?php if ($gift['honor'] !== ''): ?><br><?= e($gift['honor']) ?><?php endif; ?>
        </p>
        <?php endif; ?>

        <p class="wef-muted wef-para">
          <?= ($gift && $gift['processing'])
              ? 'Your payment is still being processed. You will get a receipt by email once it clears.'
              : 'A receipt is on its way to the email address you gave us.' ?>
          <?php /* TODO (WEF): final thank-you copy (what happens next, who will be in touch). */ ?>
          Because your gift is tax-deductible, please keep the receipt for your records.
        </p>

        <div class="wef-thanks-actions">
          <a href="./" class="wef-btn wef-btn--quiet">Back to the home page</a>
          <a href="donate" class="wef-btn wef-btn--quiet">Make another gift</a>
        </div>

        <p class="wef-muted wef-thanks-legal"><?= e($org['legalName']) ?> is a 501(c)(3) nonprofit, EIN <?= e($org['ein']) ?>. <?= e($cfg['legal']['noGoodsOrServices']) ?></p>
      </div>
    </div>
  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>
