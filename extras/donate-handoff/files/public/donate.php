<?php
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/assets.php';
require_once __DIR__ . '/../includes/donate/pricing.php';

/*
 * Donation page: Bright Beginnings, paid on Stripe Checkout.
 *
 * Ported from the tested Next.js donate page (see extras/DONATE-STRIPE.md) and dressed in
 * this site's header, footer, colours and fonts. Three ways to give:
 *   1. Sponsor a center: $2,400 once, $200/month x 12 or $100/month x 24.
 *   2. Fund part of a center: Montessori, furniture, technology, teachers,
 *      transport, times a number of centers, plus an optional other amount.
 *   3. Give any amount: once or monthly, $1 to $10,000.
 *
 * The form is rendered here; js/donate.js runs it (panels, running total,
 * inline errors) and posts the choices to api/checkout.php, which prices them
 * on the server from includes/donate/config.php and sends the donor to
 * Stripe. Stripe returns them to donate-thank-you, or to ?canceled=1 here.
 *
 * Prices, plans and wording: includes/donate/config.php.
 * Styles: css/donate.css (on top of css/styles.css).
 */

$cfg = donateConfig();
$org = $cfg['org'];
$center = $cfg['center'];
$contact = donateContact();
$centerPrice = donateMoney($center['priceCents']);
$canceled = ($_GET['canceled'] ?? '') === '1';
$tel = 'tel:' . preg_replace('/[^+\d]/', '', $contact['phone']);
$smallestPart = min(array_column($cfg['centerParts'], 'priceCents'));

// The three big choices in step 1. To add Scholarships later, add an entry
// here (and a panel below); the grid lays out any number of tiles.
$options = [
    'center' => [
        'title' => 'Sponsor a center',
        'price' => $centerPrice . ' per center',
        'panelTitle' => 'Sponsor a Bright Beginnings center',
        'blurb' => 'Start up a complete early childhood learning center. Sponsors are named on the center plaque.',
    ],
    'parts' => [
        'title' => 'Fund part of a center',
        'price' => 'From ' . donateMoney($smallestPart),
        'panelTitle' => 'Fund part of a center',
        'blurb' => 'Choose one or more items. Prices are per center.',
    ],
    'custom' => [
        'title' => 'Give any amount',
        'price' => 'One-time or monthly',
        'panelTitle' => 'Give any amount',
        'blurb' => 'A one-time or monthly gift of whatever size suits you.',
    ],
];

// What js/donate.js needs to show prices and run the same checks as the
// server. Prices here are for display only: the server prices every gift.
$clientConfig = [
    'custom' => $cfg['custom'],
    'maxCenters' => $cfg['maxCenters'],
    'plans' => $center['plans'],
    'parts' => array_map(static fn(array $p): array => ['id' => $p['id'], 'label' => $p['label'], 'priceCents' => $p['priceCents']], $cfg['centerParts']),
    'options' => array_map(static fn(array $o): array => ['panelTitle' => $o['panelTitle'], 'blurb' => $o['blurb']], $options),
    'checkoutUrl' => 'api/checkout.php',
];

/** A "Step n of 3" heading. */
function donateStepHeading(int $n, string $id, string $title, string $sub = '', string $titleId = '', string $subId = ''): string
{
    return '<div class="wef-stephead"><span class="wef-stepnum" aria-hidden="true">' . $n . '</span><div>'
        . '<h2 id="' . e($id) . '" class="wef-h3"><span class="sr-only-wef">Step ' . $n . ' of 3: </span>'
        . '<span' . ($titleId !== '' ? ' id="' . e($titleId) . '"' : '') . '>' . e($title) . '</span></h2>'
        . ($sub !== '' || $subId !== '' ? '<p class="wef-muted wef-small"' . ($subId !== '' ? ' id="' . e($subId) . '"' : '') . '>' . e($sub) . '</p>' : '')
        . '</div></div>';
}

/** The "- 1 +" number-of-centers control. */
function donateStepper(string $id, int $max): string
{
    return '<div data-field="centers">'
        . '<label for="' . e($id) . '" class="wef-label">Number of centers</label>'
        . '<div class="wef-stepper" data-stepper data-max="' . $max . '">'
        . '<button type="button" data-step="-1" aria-label="One fewer center" disabled>&minus;</button>'
        . '<input id="' . e($id) . '" inputmode="numeric" autocomplete="off" value="1" data-centers aria-describedby="' . e($id) . '-error">'
        . '<button type="button" data-step="1" aria-label="One more center">+</button>'
        . '</div>'
        . '<p id="' . e($id) . '-error" class="wef-error" data-error="centers" hidden></p>'
        . '</div>';
}

/** A dollar input with its hint and error line. */
function donateMoneyField(string $id, string $key, string $label, string $hint): string
{
    return '<div>'
        . '<label for="' . e($id) . '" class="wef-label">' . e($label) . '</label>'
        . '<div class="wef-money"><span aria-hidden="true">$</span>'
        . '<input id="' . e($id) . '" class="wef-input" inputmode="decimal" autocomplete="off" placeholder="0.00" data-field="' . e($key) . '" aria-describedby="' . e($id) . '-hint ' . e($id) . '-error"></div>'
        . '<p id="' . e($id) . '-hint" class="wef-hint">' . e($hint) . '</p>'
        . '<p id="' . e($id) . '-error" class="wef-error" data-error="' . e($key) . '" hidden></p>'
        . '</div>';
}

$faq = [
    ['Is my donation tax-deductible?',
        '<p>Yes. ' . e($org['legalName']) . ' is a registered 501(c)(3) nonprofit, EIN ' . e($org['ein']) . '. Your gift is tax-deductible to the extent allowed by law.</p>'
        . '<p>You will get a thank-you email with your receipt as soon as your payment goes through. Please keep it for your tax records.</p>'],
    ['How is my donation used?',
        '<p>Your gift goes to Bright Beginnings early childhood learning centers in Chitral: learning materials, furniture, technology, teacher training and transport. ' . e($org['overheadNote']) . '</p>'],
    ['How do monthly gifts work?',
        '<p>If you sponsor a center monthly, you pay ' . e(donateMoney($center['plans'][1]['perPaymentCents'])) . ' a month for 12 months or ' . e(donateMoney($center['plans'][2]['perPaymentCents'])) . ' a month for 24 months. The payments stop by themselves after the last one.</p>'
        . '<p>A monthly gift of any amount carries on until you ask us to stop. To change or stop it, email <a class="wef-link" href="mailto:' . e($contact['email']) . '">' . e($contact['email']) . '</a>.</p>'],
    ['How will my name appear on the center?',
        '<p>Center sponsors are named on the center&rsquo;s plaque. In the form, tell us exactly how you would like your name to appear, for example &ldquo;The Khan Family&rdquo; or &ldquo;In loving memory of our parents&rdquo;. If you would like to help name the center, tick the box and we will be in touch.</p>'],
    ['Can I give in honor or in memory of someone?',
        '<p>Yes. In the form, choose &ldquo;In honor of&rdquo; or &ldquo;In memory of&rdquo; and add their name. We record the dedication with your gift.</p>'],
    ['Can I fund just one part of a center?',
        '<p>Yes. Choose &ldquo;Fund part of a center&rdquo; and tick the items you would like to fund, for one center or several. You can also add any other amount.</p>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>

<?php
  $seoTitle = 'Donate — ' . $org['legalName'];
  $seoDescription = 'Give to ' . $org['legalName'] . ', a 501(c)(3) nonprofit bringing early childhood education to villages in Chitral, Pakistan.';
  $seoImage = 'assets/images/donate/bright-beginnings.webp';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
  <link rel="stylesheet" href="<?= e(asset('css/donate.css')) ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content" class="wef">

    <!-- Hero -->
    <section class="wef-hero">
      <div class="wef-wrap wef-hero-grid">
        <div>
          <p class="wef-eyebrow wef-eyebrow--warm">Bright Beginnings · Chitral, Pakistan</p>
          <h1 class="wef-hero-title">Give a child a bright beginning</h1>
          <p class="wef-hero-lead">Every gift helps a child in Chitral start school ready to learn. Your generosity equips early childhood learning centers in villages across Upper and Lower Chitral, Pakistan.</p>
          <dl class="wef-stats">
            <?php foreach ($cfg['impact'] as $s): ?>
            <div>
              <dt><?= e($s['label']) ?></dt>
              <dd><?= e($s['value']) ?></dd>
            </div>
            <?php endforeach; ?>
          </dl>
        </div>
        <img class="wef-photo wef-hero-photo" src="assets/images/donate/bright-beginnings.webp" width="1536" height="1152" alt="Children learning with Montessori materials in a Bright Beginnings center in Chitral" fetchpriority="high" decoding="async">
      </div>
    </section>

    <!-- The form, pulled up over the hero -->
    <section id="give" aria-labelledby="give-title" class="wef-give">
      <h2 id="give-title" class="sr-only-wef">Make a donation</h2>
      <div class="wef-wrap">
        <noscript>
          <p class="wef-notice wef-notice--error wef-noscript">This donation form needs JavaScript. Please turn it on, or email <a class="wef-link" href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a> and we will help you give.</p>
        </noscript>

        <form id="donate-form" class="wef-form-grid" novalidate data-config="<?= e(json_encode($clientConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">

          <!-- Left: one card, three steps -->
          <div class="wef-card wef-form">
            <?php if ($canceled): ?>
            <p role="status" class="wef-notice wef-notice--spaced">Checkout was cancelled and you have not been charged. You can pick up again below whenever you are ready.</p>
            <?php endif; ?>

            <!-- Step 1: three big choices -->
            <section aria-labelledby="step-1" class="wef-form-step">
              <?= donateStepHeading(1, 'step-1', 'How would you like to give?') ?>
              <fieldset>
                <legend class="sr-only-wef">How would you like to give?</legend>
                <div class="wef-tiles">
                  <?php foreach ($options as $id => $o): ?>
                  <label class="wef-choice wef-choice--tile">
                    <span class="wef-choice-row">
                      <input type="radio" name="option" value="<?= e($id) ?>"<?= $id === 'center' ? ' checked' : '' ?>>
                      <span class="wef-choice-title wef-heading"><?= e($o['title']) ?></span>
                    </span>
                    <span class="wef-tile-sub"><?= e($o['price']) ?></span>
                  </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
              <?php if ($cfg['scholarships']['showComingSoonCard']): ?>
              <p class="wef-soon"><span class="wef-badge">Coming soon</span><span><?= e($cfg['scholarships']['comingSoonCopy']) ?></span></p>
              <?php endif; ?>
            </section>

            <!-- Step 2: the panel for the chosen option -->
            <section aria-labelledby="step-2" class="wef-form-step">
              <?= donateStepHeading(2, 'step-2', $options['center']['panelTitle'], $options['center']['blurb'], 'step-2-title', 'step-2-sub') ?>

              <!-- Sponsor a center -->
              <div class="wef-stack" data-panel="center">
                <fieldset id="field-plan" tabindex="-1" data-field="plan">
                  <legend class="wef-label">How would you like to pay?</legend>
                  <div class="wef-tiles wef-tiles--spaced">
                    <?php foreach ($center['plans'] as $i => $p): ?>
                    <label class="wef-choice wef-choice--tile">
                      <span class="wef-choice-row">
                        <input type="radio" name="plan" value="<?= e($p['id']) ?>"<?= $i === 0 ? ' checked' : '' ?>>
                        <span class="wef-choice-title"><?= e($p['label']) ?></span>
                      </span>
                      <span class="wef-tile-price"><span data-plan-price="<?= e($p['id']) ?>"><?= e(donateMoney($p['perPaymentCents'])) ?></span><?php if ($p['installments'] > 1): ?><small> / month</small><?php endif; ?></span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <p class="wef-error" data-error="plan" hidden></p>
                </fieldset>

                <div class="wef-pair">
                  <?= donateStepper('field-centers', $cfg['maxCenters']) ?>
                  <div class="wef-pair-offset">
                    <label class="wef-check">
                      <input type="checkbox" id="field-wantsNaming">
                      <span>I&rsquo;d like to help name my center(s)
                        <span class="wef-hint">We will be in touch about naming your center.</span>
                      </span>
                    </label>
                  </div>
                </div>

                <div id="center-name-wrap" hidden>
                  <label for="field-centerName" class="wef-label">Do you have a name in mind? (optional)</label>
                  <input id="field-centerName" class="wef-input" maxlength="100" data-field="centerName" aria-describedby="field-centerName-hint field-centerName-error">
                  <p id="field-centerName-hint" class="wef-hint">For example, a family name or the name of someone you would like to remember.</p>
                  <p id="field-centerName-error" class="wef-error" data-error="centerName" hidden></p>
                </div>
              </div>

              <!-- Fund part of a center -->
              <div class="wef-stack" data-panel="parts" hidden>
                <fieldset id="field-items" tabindex="-1" data-field="items">
                  <legend class="sr-only-wef">Choose one or more. Prices are per center.</legend>
                  <div class="wef-list">
                    <?php foreach ($cfg['centerParts'] as $p): ?>
                    <label class="wef-choice wef-choice--row">
                      <input type="checkbox" name="items" value="<?= e($p['id']) ?>">
                      <span class="wef-choice-title"><?= e($p['label']) ?></span>
                      <span class="wef-choice-price"><?= e(donateMoney($p['priceCents'])) ?></span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <p class="wef-error" data-error="items" hidden></p>
                </fieldset>

                <div class="wef-pair">
                  <?= donateStepper('field-centers-parts', $cfg['maxCenters']) ?>
                  <?= donateMoneyField('field-otherAmountCents', 'otherAmountCents', 'Other amount (optional)', 'Added on top of the items above, or on its own.') ?>
                </div>
              </div>

              <!-- Give any amount -->
              <div class="wef-stack" data-panel="custom" hidden>
                <fieldset id="field-frequency" tabindex="-1" data-field="frequency">
                  <legend class="wef-label">How often?</legend>
                  <div class="wef-segment">
                    <label><input type="radio" name="frequency" value="once" checked><span>One time</span></label>
                    <label><input type="radio" name="frequency" value="monthly"><span>Monthly</span></label>
                  </div>
                </fieldset>

                <div>
                  <p class="wef-label" id="amount-label">Amount</p>
                  <div class="wef-chips" role="group" aria-labelledby="amount-label">
                    <?php foreach ($cfg['custom']['presetsCents'] as $cents): ?>
                    <button type="button" class="wef-chip" data-preset="<?= (int) $cents ?>" aria-pressed="false"><?= e(donateMoney($cents)) ?></button>
                    <?php endforeach; ?>
                  </div>
                  <div class="wef-narrow">
                    <?= donateMoneyField('field-amountCents', 'amountCents', 'Or enter another amount', donateMoney($cfg['custom']['minCents']) . ' to ' . donateMoney($cfg['custom']['maxCents']) . '.') ?>
                  </div>
                </div>
              </div>
            </section>

            <!-- Step 3: about the donor -->
            <section aria-labelledby="step-3" class="wef-form-step">
              <?= donateStepHeading(3, 'step-3', 'About you') ?>

              <div class="wef-fields">
                <div class="wef-fields wef-fields--two">
                  <div>
                    <label for="field-donorName" class="wef-label">Your name<span aria-hidden="true"> *</span></label>
                    <input id="field-donorName" class="wef-input" autocomplete="name" maxlength="100" required data-field="donorName" aria-describedby="field-donorName-error">
                    <p id="field-donorName-error" class="wef-error" data-error="donorName" hidden></p>
                  </div>
                  <div>
                    <label for="field-donorEmail" class="wef-label">Email<span aria-hidden="true"> *</span></label>
                    <input id="field-donorEmail" type="email" class="wef-input" autocomplete="email" maxlength="254" required data-field="donorEmail" aria-describedby="field-donorEmail-hint field-donorEmail-error">
                    <p id="field-donorEmail-hint" class="wef-hint">Your receipt is sent here.</p>
                    <p id="field-donorEmail-error" class="wef-error" data-error="donorEmail" hidden></p>
                  </div>
                </div>

                <div>
                  <label for="field-recognitionName" class="wef-label"><span id="recognition-label">How should your name appear on the center plaque?</span><span aria-hidden="true" id="recognition-required"> *</span></label>
                  <input id="field-recognitionName" class="wef-input" maxlength="100" required data-field="recognitionName" aria-describedby="field-recognitionName-hint field-recognitionName-error">
                  <p id="field-recognitionName-hint" class="wef-hint">Write it exactly as it should read, for example &ldquo;The Khan Family&rdquo;.</p>
                  <p id="field-recognitionName-error" class="wef-error" data-error="recognitionName" hidden></p>
                </div>

                <fieldset>
                  <legend class="wef-label">Give in honor or in memory of someone? (optional)</legend>
                  <div class="wef-segment">
                    <label><input type="radio" name="honorType" value="none" checked><span>No</span></label>
                    <label><input type="radio" name="honorType" value="honor"><span>In honor of</span></label>
                    <label><input type="radio" name="honorType" value="memory"><span>In memory of</span></label>
                  </div>
                </fieldset>

                <div id="honor-name-wrap" hidden>
                  <label for="field-honorName" class="wef-label"><span id="honor-label">Name of the person you are honoring</span><span aria-hidden="true"> *</span></label>
                  <input id="field-honorName" class="wef-input" maxlength="100" data-field="honorName" aria-describedby="field-honorName-error">
                  <p id="field-honorName-error" class="wef-error" data-error="honorName" hidden></p>
                </div>

                <label class="wef-check">
                  <input type="checkbox" id="field-updatesOptIn">
                  <span>Send me updates from the field
                    <span class="wef-hint">News and photos from the centers in Chitral.</span>
                  </span>
                </label>
              </div>
            </section>
          </div>

          <!-- Right: running total and the pay button -->
          <aside aria-labelledby="summary-title" class="wef-summary">
            <div class="wef-summary-head" aria-live="polite">
              <h2 id="summary-title" class="wef-eyebrow">Your gift</h2>
              <div id="summary-total">
                <p class="wef-total"><?= e($centerPrice) ?></p>
                <p class="wef-summary-note">One-time gift.</p>
              </div>
            </div>

            <div class="wef-summary-body">
              <ul class="wef-lines" id="summary-lines">
                <li><span>Bright Beginnings Center</span><span><?= e($centerPrice) ?></span></li>
              </ul>

              <p role="alert" class="wef-notice wef-notice--error" id="summary-errors" hidden></p>
              <p role="alert" class="wef-notice wef-notice--error" id="summary-server" hidden></p>

              <button type="submit" class="wef-btn" id="donate-submit">Continue to payment</button>

              <p class="wef-secure">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="currentColor" stroke-width="1.6"/></svg>
                Secure payment by Stripe
              </p>
              <p class="wef-muted wef-fineprint">You pay on Stripe&rsquo;s own page; we never see your card details. <?= e($org['legalName']) ?> is a 501(c)(3) nonprofit, EIN <?= e($org['ein']) ?>. <?= e($cfg['legal']['noGoodsOrServices']) ?></p>
            </div>
          </aside>
        </form>
      </div>
    </section>

    <!-- Why give + where the money goes -->
    <section class="wef-section wef-section--white" aria-labelledby="why-title">
      <div class="wef-wrap wef-split">
        <div>
          <p class="wef-step">Why give</p>
          <h2 id="why-title" class="wef-h2">Your support changes lives</h2>
          <p class="wef-para">At the <?= e($org['legalName']) ?>, we believe talent is universal, but opportunity is not. Bright Beginnings brings early childhood education to underserved villages in Chitral, so children start school ready to learn.</p>
          <p class="wef-para">A gift of <?= e($centerPrice) ?> equips one center with Montessori learning materials, furniture, a carpet, a Smart TV and technology. Sponsor a whole center, spread it over 12 or 24 months, or fund one part of it.</p>
          <ul class="wef-ticks">
            <li>Secure payment by Stripe</li>
            <li>Tax-deductible: 501(c)(3) organization, EIN <?= e($org['ein']) ?></li>
            <li>A thank-you email and tax receipt, sent right away</li>
            <li>Monthly center plans stop by themselves</li>
          </ul>
        </div>

        <div class="wef-card wef-card--roomy">
          <h3 class="wef-h3">Where your <?= e($centerPrice) ?> goes</h3>
          <p class="wef-muted wef-small">The cost of starting up one center.</p>
          <ul class="wef-breakdown">
            <?php foreach ($cfg['centerParts'] as $p): ?>
            <li>
              <span>
                <span class="wef-breakdown-label"><?= e($p['label']) ?></span>
                <span class="wef-muted wef-small"><?= e($p['about']) ?></span>
              </span>
              <span class="wef-breakdown-price"><?= e(donateMoney($p['priceCents'])) ?></span>
            </li>
            <?php endforeach; ?>
            <li class="wef-breakdown-total">
              <span>One complete center</span>
              <span><?= e($centerPrice) ?></span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Donor-advised funds -->
    <section class="wef-section" aria-labelledby="daf-title">
      <div class="wef-wrap wef-split wef-split--daf">
        <div>
          <p class="wef-step">Another way to give</p>
          <h2 id="daf-title" class="wef-h2">Give from a donor-advised fund</h2>
          <p class="wef-muted wef-para">Have a DAF at Fidelity Charitable, Schwab, Vanguard, a community foundation or another sponsor? Recommend a grant to us using these details. You can name Bright Beginnings in the grant note.</p>
        </div>
        <dl class="wef-card wef-card--roomy wef-facts">
          <div><dt>Legal name</dt><dd><?= e($org['legalName']) ?></dd></div>
          <div><dt>EIN</dt><dd><?= e($org['ein']) ?> (501(c)(3) public charity)</dd></div>
          <div><dt>Address</dt><dd><?php foreach ($contact['address'] as $line): ?><span class="wef-block"><?= e($line) ?></span><?php endforeach; ?></dd></div>
        </dl>
      </div>
    </section>

    <!-- FAQ -->
    <section class="wef-section wef-section--white" aria-labelledby="faq-title">
      <div class="wef-wrap wef-split wef-split--faq">
        <div>
          <p class="wef-step">Questions</p>
          <h2 id="faq-title" class="wef-h2">Frequently asked questions</h2>
          <p class="wef-muted wef-para">Can&rsquo;t find your answer? Email <a class="wef-link" href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a> or call <a class="wef-link wef-nowrap" href="<?= e($tel) ?>"><?= e($contact['phone']) ?></a>.</p>
        </div>
        <div class="wef-faq">
          <?php foreach ($faq as [$q, $a]): ?>
          <details>
            <summary><?= e($q) ?></summary>
            <div><?= $a /* built above from escaped values */ ?></div>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
  <script src="<?= asset('js/donate.js') ?>" defer></script>
</body>
</html>
