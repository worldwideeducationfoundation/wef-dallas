<?php
/**
 * Validation and pricing for donations. Port of the tested Next.js lib/donate/
 * pricing.ts (zod schemas, buildQuote, buildMetadata, receipt text). Same
 * rules, same messages, same output shapes.
 *
 * api/checkout.php runs this on the raw request to decide what to charge, so
 * the page can never choose the price. js/donate.js carries a copy of the
 * same checks only to show the running total and inline errors; this file is
 * the one that counts.
 */

require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* Small type checks (what zod did)                                    */
/* ------------------------------------------------------------------ */

function donateIsNumber(mixed $v): bool
{
    return (is_int($v) || is_float($v)) && is_finite((float) $v);
}

/** A whole number, as JSON sends it (2 or 2.0). */
function donateIsWhole(mixed $v): bool
{
    return is_int($v) || (is_float($v) && is_finite($v) && floor($v) === $v);
}

/** zod's email check (z.email(), its default pattern). */
const DONATE_EMAIL_PATTERN = "/^(?!\\.)(?!.*\\.\\.)([A-Za-z0-9_'+\\-\\.]*)[A-Za-z0-9_+-]@([A-Za-z0-9][A-Za-z0-9\\-]*\\.)+[A-Za-z]{2,}$/";

/**
 * Checks a whole-number amount in cents against the gift limits.
 * Returns the error message, or null when it is fine.
 */
function donateAmountError(mixed $v, string $typeMessage): ?string
{
    $c = donateConfig()['custom'];
    if (!donateIsNumber($v)) {
        return $typeMessage;
    }
    if (!donateIsWhole($v)) {
        return 'Enter an amount in dollars and cents.';
    }
    if ($v < $c['minCents']) {
        return 'The smallest gift is ' . donateMoney($c['minCents']) . '.';
    }
    if ($v > $c['maxCents']) {
        return 'Online gifts are limited to ' . donateMoney($c['maxCents']) . '. Please contact us for a larger gift.';
    }
    return null;
}

function donateCentersError(mixed $v): ?string
{
    $max = donateConfig()['maxCenters'];
    if (!donateIsNumber($v)) {
        return 'Choose how many centers.';
    }
    if (!donateIsWhole($v)) {
        return 'Choose a whole number of centers.';
    }
    if ($v < 1) {
        return 'Choose at least one center.';
    }
    if ($v > $max) {
        return "Please contact us to support more than {$max} centers.";
    }
    return null;
}

/** A trimmed string of at most $max characters, or an error. */
function donateString(mixed $v, int $max, string $tooLong, string $typeMessage, ?string &$error): string
{
    $error = null;
    if (!is_string($v)) {
        $error = $typeMessage;
        return '';
    }
    $t = trim($v);
    if (mb_strlen($t, 'UTF-8') > $max) {
        $error = $tooLong;
    }
    return $t;
}

/* ------------------------------------------------------------------ */
/* What the donor chose                                                */
/* ------------------------------------------------------------------ */

/**
 * @return array{ok: true, selection: array<string, mixed>}|array{ok: false, errors: array<string, string>}
 */
function donateValidateSelection(mixed $raw): array
{
    $cfg = donateConfig();
    $errors = [];
    $add = static function (string $key, ?string $msg) use (&$errors): void {
        if ($msg !== null && !isset($errors[$key])) {
            $errors[$key] = $msg;
        }
    };

    if (!is_array($raw) || !in_array($raw['option'] ?? null, ['center', 'parts', 'custom'], true)) {
        return ['ok' => false, 'errors' => ['option' => 'Choose how you would like to give.']];
    }

    $option = $raw['option'];

    if ($option === 'center') {
        $planIds = array_column($cfg['center']['plans'], 'id');
        if (!in_array($raw['plan'] ?? null, $planIds, true)) {
            $add('plan', 'Choose how you would like to give.');
        }
        $add('centers', donateCentersError($raw['centers'] ?? null));
        if (!is_bool($raw['wantsNaming'] ?? null)) {
            $add('wantsNaming', 'Tell us whether you would like to help name your center.');
        }
        $centerName = null;
        if (array_key_exists('centerName', $raw)) {
            $centerName = donateString($raw['centerName'], 100, 'That name is too long.', 'That name is not valid.', $err);
            $add('centerName', $err);
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $sel = [
            'option' => 'center',
            'plan' => $raw['plan'],
            'centers' => (int) $raw['centers'],
            'wantsNaming' => $raw['wantsNaming'],
        ];
        if ($centerName !== null) {
            $sel['centerName'] = $centerName;
        }
        return ['ok' => true, 'selection' => $sel];
    }

    if ($option === 'parts') {
        $partIds = array_column($cfg['centerParts'], 'id');
        $items = $raw['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            $add('items', 'Choose at least one part of a center, or enter another amount.');
            $items = [];
        } else {
            foreach ($items as $id) {
                if (!in_array($id, $partIds, true)) {
                    $add('items', 'That item is not available.');
                }
            }
            if (!isset($errors['items']) && count(array_unique($items)) !== count($items)) {
                $add('items', 'Each part can only be chosen once.');
            }
        }
        $add('centers', donateCentersError($raw['centers'] ?? null));
        $other = null;
        if (array_key_exists('otherAmountCents', $raw)) { // null (from a bad amount in the browser) is an error, as with zod
            $add('otherAmountCents', donateAmountError($raw['otherAmountCents'], 'Enter an amount in dollars and cents.'));
            $other = $raw['otherAmountCents'];
        }
        // The "at least one" rule only runs once the rest is valid (as with zod's refine).
        if (!$errors && count($items) === 0 && $other === null) {
            $add('items', 'Choose at least one part of a center, or enter another amount.');
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $sel = ['option' => 'parts', 'items' => array_values($items), 'centers' => (int) $raw['centers']];
        if ($other !== null) {
            $sel['otherAmountCents'] = (int) $other;
        }
        return ['ok' => true, 'selection' => $sel];
    }

    // custom
    if (!in_array($raw['frequency'] ?? null, ['once', 'monthly'], true)) {
        $add('frequency', 'Choose one-time or monthly.');
    }
    $add('amountCents', donateAmountError($raw['amountCents'] ?? null, 'Enter an amount.'));
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    return ['ok' => true, 'selection' => [
        'option' => 'custom',
        'frequency' => $raw['frequency'],
        'amountCents' => (int) $raw['amountCents'],
    ]];
}

/* ------------------------------------------------------------------ */
/* Who is giving                                                       */
/* ------------------------------------------------------------------ */

/**
 * @return array{ok: true, details: array<string, mixed>}|array{ok: false, errors: array<string, string>}
 */
function donateValidateDetails(mixed $raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $errors = [];
    $add = static function (string $key, ?string $msg) use (&$errors): void {
        if ($msg !== null && !isset($errors[$key])) {
            $errors[$key] = $msg;
        }
    };
    $tooLong = 'That name is too long.';

    $donorName = donateString($raw['donorName'] ?? null, 100, $tooLong, 'Please enter your name.', $err);
    $add('donorName', $err);
    if ($err === null && $donorName === '') {
        $add('donorName', 'Please enter your name.');
    }

    $email = $raw['donorEmail'] ?? null;
    if (!is_string($email) || !preg_match(DONATE_EMAIL_PATTERN, trim($email))) {
        $add('donorEmail', 'Please enter a valid email address.');
    } elseif (strlen(trim($email)) > 254) {
        $add('donorEmail', 'That email address is too long.');
    }

    $recognitionName = donateString($raw['recognitionName'] ?? null, 100, $tooLong, 'That name is not valid.', $err);
    $add('recognitionName', $err);

    $honorType = $raw['honorType'] ?? null;
    if (!in_array($honorType, ['none', 'honor', 'memory'], true)) {
        $add('honorType', 'Choose whether this gift is in honor or in memory of someone.');
    }

    $honorName = donateString($raw['honorName'] ?? null, 100, $tooLong, 'That name is not valid.', $err);
    $add('honorName', $err);

    if (!is_bool($raw['updatesOptIn'] ?? null)) {
        $add('updatesOptIn', 'Tell us whether you would like updates.');
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    return ['ok' => true, 'details' => [
        'donorName' => $donorName,
        'donorEmail' => trim($email),
        'recognitionName' => $recognitionName,
        'honorType' => $honorType,
        'honorName' => $honorName,
        'updatesOptIn' => $raw['updatesOptIn'],
    ]];
}

/**
 * Check a full request: { selection, details }.
 *
 * @return array{ok: true, donation: array{selection: array<string, mixed>, details: array<string, mixed>}}|array{ok: false, errors: array<string, string>}
 */
function donateValidateDonation(mixed $raw): array
{
    $body = is_array($raw) ? $raw : [];
    $sel = donateValidateSelection($body['selection'] ?? null);
    $det = donateValidateDetails($body['details'] ?? null);

    $errors = [];
    if (!$sel['ok']) {
        $errors = array_merge($errors, $sel['errors']);
    }
    if (!$det['ok']) {
        $errors = array_merge($errors, $det['errors']);
    }

    if ($det['ok']) {
        // Sponsoring a center needs a name for the plaque.
        if ($sel['ok'] && $sel['selection']['option'] === 'center' && $det['details']['recognitionName'] === '') {
            $errors['recognitionName'] = 'Please tell us how your name should appear on the center plaque.';
        }
        if ($det['details']['honorType'] !== 'none' && $det['details']['honorName'] === '') {
            $errors['honorName'] = 'Please enter the name of the person.';
        }
    }

    if ($errors || !$sel['ok'] || !$det['ok']) {
        return ['ok' => false, 'errors' => $errors];
    }
    return ['ok' => true, 'donation' => ['selection' => $sel['selection'], 'details' => $det['details']]];
}

/* ------------------------------------------------------------------ */
/* What it costs                                                       */
/* ------------------------------------------------------------------ */

/**
 * mode: "payment" (once) or "subscription" (monthly).
 * installments: number of payments; null = monthly until the donor cancels.
 * totalCents: null when open-ended.
 *
 * @param array<string, mixed> $selection A selection that passed donateValidateSelection().
 * @return array{mode: string, lines: list<array{label: string, name: string, description: string, unitAmountCents: int, quantity: int}>, perPaymentCents: int, installments: ?int, totalCents: ?int, summary: string, donationType: string}
 */
function donateBuildQuote(array $selection): array
{
    $cfg = donateConfig();
    $center = $cfg['center'];

    if ($selection['option'] === 'center') {
        $plan = null;
        foreach ($center['plans'] as $p) {
            if ($p['id'] === $selection['plan']) {
                $plan = $p;
            }
        }
        if ($plan === null) {
            throw new InvalidArgumentException('Unknown plan');
        }
        $monthly = $plan['installments'] > 1;
        $perPayment = $plan['perPaymentCents'] * $selection['centers'];
        return [
            'mode' => $monthly ? 'subscription' : 'payment',
            'lines' => [[
                'label' => 'Bright Beginnings Center',
                'name' => $center['name'],
                'description' => "{$center['place']}. {$plan['label']}.",
                'unitAmountCents' => $plan['perPaymentCents'],
                'quantity' => $selection['centers'],
            ]],
            'perPaymentCents' => $perPayment,
            'installments' => $plan['installments'],
            'totalCents' => $perPayment * $plan['installments'],
            'summary' => $monthly
                ? donateMoney($perPayment) . " a month for {$plan['installments']} months"
                : donateMoney($perPayment) . ', one time',
            'donationType' => $monthly ? "center_monthly_{$plan['installments']}" : 'center_lump',
        ];
    }

    if ($selection['option'] === 'parts') {
        $partsById = array_column($cfg['centerParts'], null, 'id');
        $lines = [];
        foreach ($selection['items'] as $id) {
            $part = $partsById[$id] ?? throw new InvalidArgumentException('Unknown part');
            $lines[] = [
                'label' => $part['label'],
                'name' => "{$part['label']} for a Bright Beginnings center",
                'description' => "{$center['place']}.",
                'unitAmountCents' => $part['priceCents'],
                'quantity' => $selection['centers'],
            ];
        }
        if (isset($selection['otherAmountCents'])) {
            $lines[] = [
                'label' => 'Other amount',
                'name' => 'Additional gift toward Bright Beginnings centers',
                'description' => "{$center['place']}.",
                'unitAmountCents' => $selection['otherAmountCents'],
                'quantity' => 1,
            ];
        }
        $total = 0;
        foreach ($lines as $l) {
            $total += $l['unitAmountCents'] * $l['quantity'];
        }
        return [
            'mode' => 'payment',
            'lines' => $lines,
            'perPaymentCents' => $total,
            'installments' => 1,
            'totalCents' => $total,
            'summary' => donateMoney($total) . ', one time',
            'donationType' => 'center_parts',
        ];
    }

    $monthly = $selection['frequency'] === 'monthly';
    $amount = $selection['amountCents'];
    return [
        'mode' => $monthly ? 'subscription' : 'payment',
        'lines' => [[
            'label' => 'Donation',
            'name' => 'Donation to ' . $cfg['org']['legalName'],
            'description' => 'Education in Chitral, Pakistan and Central Asia.',
            'unitAmountCents' => $amount,
            'quantity' => 1,
        ]],
        'perPaymentCents' => $amount,
        'installments' => $monthly ? null : 1,
        'totalCents' => $monthly ? null : $amount,
        'summary' => $monthly
            ? donateMoney($amount) . ' a month, until you stop it'
            : donateMoney($amount) . ', one time',
        'donationType' => $monthly ? 'custom_monthly' : 'custom_once',
    ];
}

/* ------------------------------------------------------------------ */
/* What we store with the payment in Stripe                            */
/* ------------------------------------------------------------------ */

/**
 * Shown under "Metadata" in the Stripe dashboard. Stripe allows 50 keys and
 * 500 characters per value; empty values are left out.
 *
 * @param array{selection: array<string, mixed>, details: array<string, mixed>} $donation
 * @param array<string, mixed> $quote
 * @return array<string, string>
 */
function donateBuildMetadata(array $donation, array $quote): array
{
    $selection = $donation['selection'];
    $details = $donation['details'];
    $noHonor = $details['honorType'] === 'none';

    $entries = [
        'source' => 'wef-donate',
        'donation_type' => $quote['donationType'],
        'donor_name' => $details['donorName'],
        'recognition_name' => $details['recognitionName'],
        'honor_type' => $noHonor ? '' : $details['honorType'],
        'honor_name' => $noHonor ? '' : $details['honorName'],
        'updates_opt_in' => $details['updatesOptIn'] ? 'yes' : 'no',
        'installments' => ($quote['installments'] ?? 0) > 1 ? (string) $quote['installments'] : '',
    ];

    if ($selection['option'] === 'center') {
        $entries['centers'] = (string) $selection['centers'];
        $entries['name_my_center'] = $selection['wantsNaming'] ? 'yes' : 'no';
        if ($selection['wantsNaming'] && ($selection['centerName'] ?? '') !== '') {
            $entries['suggested_center_name'] = $selection['centerName'];
        }
    }
    if ($selection['option'] === 'parts') {
        if (count($selection['items']) > 0) {
            $entries['centers'] = (string) $selection['centers'];
            $entries['parts'] = implode(',', $selection['items']);
        }
        if (isset($selection['otherAmountCents'])) {
            $entries['other_amount_cents'] = (string) $selection['otherAmountCents'];
        }
    }

    return array_filter($entries, static fn(string $v): bool => $v !== '');
}

/* ------------------------------------------------------------------ */
/* Receipt text                                                        */
/* ------------------------------------------------------------------ */

/** The line printed on the Stripe receipt/invoice. */
function donateReceiptDescription(array $quote): string
{
    $cfg = donateConfig();
    $what = implode('; ', array_map(
        static fn(array $l): string => ($l['quantity'] > 1 ? "{$l['quantity']} x " : '') . $l['name'],
        $quote['lines']
    ));
    return "Tax-deductible donation to {$cfg['org']['legalName']} (EIN {$cfg['org']['ein']}): {$what}. {$cfg['legal']['noGoodsOrServices']}";
}

/** The footer on invoices (one-time gifts and every monthly payment). */
function donateInvoiceFooter(): string
{
    $cfg = donateConfig();
    $org = $cfg['org'];
    $email = donateContact()['email'];
    return "{$org['legalName']} is a 501(c)(3) nonprofit, EIN {$org['ein']}. Your contribution is tax-deductible to the extent allowed by law. {$cfg['legal']['noGoodsOrServices']}"
        . ($email !== '' ? " Questions: {$email}" : '');
}

/** Parse "25", "25.5" or "$1,250.00" into cents; null if it is not a clean amount. */
function donateDollarsToCents(string $input): ?int
{
    $cleaned = str_replace(',', '', preg_replace('/^\$/', '', trim($input)));
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $cleaned)) {
        return null;
    }
    return (int) round(((float) $cleaned) * 100);
}
