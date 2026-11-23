<?php
/**
 * Everything about WHAT can be given and for HOW MUCH lives in this one file.
 * Prices are in cents (integers) so there is never a rounding error.
 *
 * Ported from the tested Next.js donate page (lib/donate/
 * config.ts). The numbers and rules are unchanged.
 *
 * The server charges from these numbers only. The browser sends ids and
 * counts ("parts: montessori + technology, 2 centers"); it never sends a
 * price, so nobody can change the amount by editing the page. The only
 * amounts taken from the browser are "Give any amount" and "Other amount",
 * and both are validated against the limits below.
 *
 * Stripe keys are NOT here: they go in config/config.php (see
 * config.sample.php, "Donations (Stripe)").
 */

/** @return array<string, mixed> */
function donateConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        /** Lower-case ISO code, as Stripe expects. */
        'currency' => 'usd',

        'org' => [
            'legalName' => 'Worldwide Education Foundation',
            'shortName' => 'WEF',
            'ein' => '81-2135633',
            /** As stated on wefdallas.org/donate. */
            'overheadNote' => '100% of the dollars raised go to the implementation, operation and maintenance of WEF projects. Administrative expenses are paid by the Founders, through the Connie and Kamal Daya Gift Fund of the Dallas Foundation.',
            // Contact email, phone and address come from admin -> Settings
            // (siteSettings()), like the rest of the site. These are only the
            // fallbacks. TODO (WEF): confirm the address is the one for mail
            // and donor-advised fund grants.
            'contactEmail' => 'info@wefdallas.org',
            'phone' => '+1 (214) 502-8388',
            'mailingAddress' => ['W159N4940 Graysland Drive', 'Menomonee Falls, WI 53051'],
        ],

        /**
         * The figures in the page hero. From wefdallas.org/donate ("one center
         * reaches about 300 children over 10 years, $8 per child").
         * TODO (WEF): confirm before launch; delete any that are out of date.
         */
        'impact' => [
            ['value' => '$2,400', 'label' => 'equips one learning center'],
            ['value' => '~300', 'label' => 'children per center over ten years'],
            ['value' => '$8', 'label' => 'per child'],
        ],

        /** Open-amount gifts ("Give any amount"). */
        'custom' => [
            'minCents' => 100,        // $1
            'maxCents' => 1000000,    // $10,000
            /** Quick-pick buttons. Any amount in range is allowed. */
            'presetsCents' => [2500, 5000, 10000, 25000],
        ],

        /** The most centers one gift can cover (a sanity limit). */
        'maxCenters' => 20,

        /** A complete Bright Beginnings center. */
        'center' => [
            'name' => 'Bright Beginnings Center Start-Up',
            'place' => 'Early Childhood Learning Centers, Chitral, Pakistan',
            'priceCents' => 240000,
            /**
             * Ways to pay for a center. perPaymentCents * installments must
             * equal priceCents (checked below, so a typo fails loudly instead
             * of mis-charging).
             */
            'plans' => [
                ['id' => 'lump', 'label' => 'One-time', 'perPaymentCents' => 240000, 'installments' => 1],
                ['id' => 'monthly12', 'label' => 'Monthly for 12 months', 'perPaymentCents' => 20000, 'installments' => 12],
                ['id' => 'monthly24', 'label' => 'Monthly for 24 months', 'perPaymentCents' => 10000, 'installments' => 24],
            ],
        ],

        /** Fixed-price pieces of one center. Together they make up the $2,400.
         *  `about` is shown in "Where your gift goes". TODO (WEF): confirm wording. */
        'centerParts' => [
            ['id' => 'montessori', 'label' => 'Montessori materials', 'priceCents' => 80000, 'about' => 'Hands-on learning materials for the classroom.'],
            ['id' => 'furniture', 'label' => 'Furniture and carpet', 'priceCents' => 80000, 'about' => 'Child-sized tables, chairs, shelves and a carpet to learn on.'],
            ['id' => 'technology', 'label' => 'Technology', 'priceCents' => 50000, 'about' => 'A Smart TV and learning technology.'],
            ['id' => 'teachers', 'label' => 'Teacher training', 'priceCents' => 20000, 'about' => "Training for the center's teacher."],
            ['id' => 'transport', 'label' => 'Transportation', 'priceCents' => 10000, 'about' => 'Getting everything to the village.'],
        ],

        /**
         * FUTURE: scholarships. Nothing here is live (every entry is
         * enabled => false), so the server refuses it and the form cannot
         * select it. To launch one: set enabled, add a "scholarships" option
         * next to "parts" in pricing.php (and js/donate.js), and a panel in
         * donate.php.
         */
        'scholarships' => [
            'showComingSoonCard' => false,
            'comingSoonCopy' => 'Scholarships for students in Chitral are coming soon.',
            'programs' => [
                ['id' => 'herzing', 'label' => 'Herzing College diploma scholarship', 'priceCents' => 120000, 'unit' => 'student', 'enabled' => false],
                ['id' => 'bright-beginnings', 'label' => 'Bright Beginnings scholarship', 'priceCents' => null, 'unit' => 'child', 'enabled' => false],
                ['id' => 'secondary', 'label' => 'Secondary school scholarships', 'priceCents' => null, 'unit' => 'student', 'enabled' => false],
            ],
        ],

        /**
         * Wording that must appear on what the donor sees and receives (the
         * IRS wants a goods-or-services statement on acknowledgments of gifts
         * of $250 or more): on Checkout, the invoice and the description.
         */
        'legal' => [
            'noGoodsOrServices' => 'No goods or services were provided in exchange for this contribution.',
        ],

        /**
         * One-time gifts only. When true, Checkout also creates a paid invoice
         * so the legal text appears as the invoice footer on the receipt email.
         * Stripe may charge a small fee per invoice; false = plain receipts.
         */
        'invoiceForOneTimeGifts' => true,
    ];

    // Fail loudly if the prices stop adding up.
    foreach ($config['center']['plans'] as $plan) {
        if ($plan['perPaymentCents'] * $plan['installments'] !== $config['center']['priceCents']) {
            throw new LogicException('Donate config: plan "' . $plan['id'] . '" does not add up to the center price.');
        }
    }
    $partsTotal = array_sum(array_column($config['centerParts'], 'priceCents'));
    if ($partsTotal !== $config['center']['priceCents']) {
        throw new LogicException("Donate config: the center parts add up to {$partsTotal}, not {$config['center']['priceCents']}.");
    }

    return $config;
}

/**
 * Contact details: admin -> Settings first (like the rest of the site), the
 * config fallbacks otherwise.
 *
 * @return array{email: string, phone: string, address: list<string>}
 */
function donateContact(): array
{
    $org = donateConfig()['org'];
    $settings = function_exists('siteSettings') ? siteSettings() : [];

    $email = trim((string) ($settings['contact_email'] ?? '')) ?: $org['contactEmail'];
    $phone = trim((string) ($settings['contact_phone'] ?? '')) ?: $org['phone'];

    return ['email' => $email, 'phone' => $phone, 'address' => $org['mailingAddress']];
}

/** "$2,400" or "$12.50": whole dollars have no decimals. */
function donateMoney(int $cents): string
{
    $whole = $cents % 100 === 0;
    return '$' . number_format($cents / 100, $whole ? 0 : 2);
}

/**
 * A secret or setting from config/config.php, falling back to an environment
 * variable of the same name. Never logged, never sent to the browser.
 */
function donateSetting(string $name): string
{
    // config.php wins when it holds a value; an empty define falls through, so
    // an env var set in the host's panel or .htaccess (SetEnv) still works.
    if (defined($name)) {
        $value = trim((string) constant($name));
        if ($value !== '') {
            return $value;
        }
    }
    foreach ([getenv($name), $_SERVER[$name] ?? null, $_ENV[$name] ?? null] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }
    return '';
}
