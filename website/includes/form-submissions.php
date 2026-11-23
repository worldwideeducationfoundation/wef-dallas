<?php
/**
 * The one description of what the public forms accept.
 *
 * Three places need to agree about the contact and volunteer forms: the pages
 * that render the dropdowns (contact.php, volunteer.php), the endpoint that
 * validates what comes back (api/contact.php), and the admin list that has to
 * print "Partnership Opportunity" rather than the stored key "partnership"
 * (admin/modules/submissions/). Held in one file so a new topic is added to a
 * dropdown, accepted by the validator, and labelled in the CMS by a single
 * edit — the alternative is three lists that agree right up until they don't,
 * and the way that fails is a visitor picking an option the server rejects.
 *
 * Loaded by BOTH tiers, so it deliberately depends on nothing but db.php: no
 * session, no auth, no e(). The public site and the admin app define their own
 * helpers and error handlers, and a shared file that reached for either would
 * only work from one side.
 */

require_once __DIR__ . '/db.php';

/**
 * Each form: its human label, the page it lives on (used to send a no-JS
 * visitor back where they came from without ever reading a redirect target
 * out of the request), and the subjects its dropdown offers.
 *
 * The two subject lists overlap but are not identical, which is the reason
 * subjects are per-form rather than one global list: "Donation Question" on
 * the volunteer form would be a validator that accepts more than the form can
 * actually send.
 */
const SUBMISSION_FORMS = [
    'contact' => [
        'label' => 'Contact',
        'page' => 'contact.php',
        'subjects' => [
            'general' => 'General Inquiry',
            'partnership' => 'Partnership Opportunity',
            'volunteer' => 'Volunteer with WEF',
            'donation' => 'Donation Question',
            'media' => 'Media & Press',
            'other' => 'Other',
        ],
    ],
    'volunteer' => [
        'label' => 'Volunteer',
        'page' => 'volunteer.php',
        'subjects' => [
            'volunteer' => 'Volunteer with WEF',
            'mentorship' => 'Mentorship',
            'other' => 'Other',
        ],
    ],
    /**
     * Newsletter signup, in the site footer.
     *
     * Added when the footer was consolidated onto footer-full across every
     * page. That form was decoration in the same way the contact form was:
     * js/main.js showed "You're subscribed — thank you for supporting WEF."
     * after a setTimeout and stored nothing. Putting it on 22 more pages
     * without a backend would have multiplied a false promise elevenfold, so
     * it is wired to the same endpoint and inbox as everything else.
     *
     * 'fields' is what makes that possible: a newsletter signup is one email
     * address, with no name, subject or message to give. Without it the
     * shared validator would demand all four and reject every signup.
     */
    'newsletter' => [
        'label' => 'Newsletter',
        'page' => 'index.php',
        'fields' => ['email'],
        // "We will get back to you shortly" is wrong for a mailing-list
        // signup — nobody is going to reply to it. Each form can state what
        // actually happens next.
        'successMessage' => "You're subscribed — thank you for supporting WEF.",
        'subjects' => [
            'newsletter' => 'Newsletter signup',
        ],
    ],
];

/**
 * What the visitor is told after a successful submission. Falls back to the
 * wording the contact and volunteer forms have always used.
 */
function submissionSuccessMessage(string $type): string
{
    return SUBMISSION_FORMS[$type]['successMessage']
        ?? 'Thank you — your message has been received. We will get back to you shortly.';
}

/**
 * The fields a given form actually collects. Defaults to the full set, so the
 * contact and volunteer forms are unaffected by this existing at all.
 *
 * @return list<string>
 */
function submissionFields(string $type): array
{
    return SUBMISSION_FORMS[$type]['fields'] ?? ['name', 'email', 'subject', 'message'];
}

/**
 * Triage workflow, in the order a submission moves through it. Deliberately
 * not the draft/active pair every content table uses — nothing here is ever
 * published, and reusing those words would suggest it might be.
 */
const SUBMISSION_STATUSES = [
    'new' => 'New',
    'read' => 'Read',
    'replied' => 'Replied',
    'archived' => 'Archived',
];

/**
 * What the form pages print for each result code when JavaScript is off.
 *
 * The no-JS path is a redirect back to the page with ?form=<code>, and the
 * page renders the matching entry here. Held as a map keyed by code — rather
 * than passing the message itself through the query string — so the only thing
 * a visitor can influence is *which* of these fixed sentences appears, never
 * its text. A message read out of the URL is a message anyone can choose to
 * have displayed, styled as our own, on our own domain.
 *
 * @var array<string, array{type: string, text: string}>
 */
const SUBMISSION_RESULT_MESSAGES = [
    'ok' => [
        'type' => 'success',
        'text' => 'Thank you — your message has been received. We will get back to you shortly.',
    ],
    'invalid' => [
        'type' => 'error',
        'text' => 'Some details were missing or incorrect. Please check the form and send it again.',
    ],
    'throttled' => [
        'type' => 'error',
        'text' => 'You have sent several messages already. Please wait a few minutes before sending another.',
    ],
    'method' => [
        'type' => 'error',
        'text' => 'Something went wrong sending that. Please try again.',
    ],
];

/**
 * Resolves ?form=<code> into a message to render, or null when the parameter
 * is absent or not one we issued.
 *
 * @return array{type: string, text: string}|null
 */
function submissionResultMessage(?string $code): ?array
{
    return $code === null ? null : (SUBMISSION_RESULT_MESSAGES[$code] ?? null);
}

// Column widths from schema.sql. Kept as constants because the validator and
// the insert both need them and a mismatch is silent: MySQL in strict mode
// rejects the row, and the visitor sees a failure with nothing explaining it.
const SUBMISSION_MAX_NAME = 160;
const SUBMISSION_MAX_EMAIL = 190;
const SUBMISSION_MAX_MESSAGE = 5000;

// Flood control for the public endpoint. Generous enough that a family
// sharing an address, or someone who genuinely writes twice, is unaffected;
// low enough that a script cannot fill the table unattended.
const SUBMISSION_MAX_PER_IP = 5;
const SUBMISSION_FLOOD_WINDOW_MINUTES = 15;

/**
 * @return array{label: string, page: string, subjects: array<string, string>}|null
 */
function submissionForm(string $type): ?array
{
    return SUBMISSION_FORMS[$type] ?? null;
}

/**
 * The subject dropdown for a form, ready to render.
 *
 * @return array<string, string> stored key => visible label
 */
function submissionSubjects(string $type): array
{
    return SUBMISSION_FORMS[$type]['subjects'] ?? [];
}

/**
 * Turns a stored key back into its label for display.
 *
 * Falls back to the raw key rather than an empty cell: a row whose subject is
 * no longer in the registry (an option that was removed after someone used it)
 * still has to be readable in the CMS, and showing nothing would look like
 * missing data instead of a retired option.
 */
function submissionSubjectLabel(string $type, string $subject): string
{
    return SUBMISSION_FORMS[$type]['subjects'][$subject] ?? $subject;
}

function submissionFormLabel(string $type): string
{
    return SUBMISSION_FORMS[$type]['label'] ?? $type;
}

function submissionStatusLabel(string $status): string
{
    return SUBMISSION_STATUSES[$status] ?? $status;
}

/**
 * Validates one submission.
 *
 * Returns the cleaned values alongside a field => message map of problems, so
 * the caller can both report every error at once and repopulate the form with
 * what was typed. An empty 'errors' means the values are safe to insert.
 *
 * Everything is checked server-side even though the markup carries `required`
 * and `type="email"`: the browser attributes are there to help the person
 * filling the form in, and are absent entirely for anything posting directly
 * to the endpoint.
 *
 * @param array<string, mixed> $input raw $_POST
 * @return array{values: array<string, string>, errors: array<string, string>}
 */
function submissionValidate(array $input): array
{
    $errors = [];

    $type = trim((string) ($input['form_type'] ?? ''));
    if (submissionForm($type) === null) {
        // Not a field the visitor can see or get wrong — a bad form_type means
        // the request did not come from one of our forms, so the whole thing is
        // rejected rather than defaulted to 'contact'.
        return [
            'values' => [],
            'errors' => ['form_type' => 'This form could not be identified. Please reload the page and try again.'],
        ];
    }

    // Normalise the whitespace people paste in from other applications before
    // measuring length, so a name of 200 spaces is empty rather than too long.
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')) ?? '');
    $email = trim((string) ($input['email'] ?? ''));
    $subject = trim((string) ($input['subject'] ?? ''));
    // Message keeps its line breaks — it is prose, and collapsing paragraphs
    // into one block would destroy the only field with real content in it.
    $message = trim((string) ($input['message'] ?? ''));

    // Only the fields this form actually collects are required. A newsletter
    // signup has an email and nothing else; demanding a name and a message
    // from it would reject every submission.
    $collects = submissionFields($type);

    if (in_array('name', $collects, true)) {
        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        } elseif (mb_strlen($name) > SUBMISSION_MAX_NAME) {
            $errors['name'] = 'Please keep your name under ' . SUBMISSION_MAX_NAME . ' characters.';
        }
    }

    // Email is required on every form — it is the only way to reply, and a
    // submission nobody can answer is worse than no submission.
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (mb_strlen($email) > SUBMISSION_MAX_EMAIL || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (in_array('subject', $collects, true)) {
        if ($subject === '') {
            $errors['subject'] = 'Please choose a subject.';
        } elseif (!isset(SUBMISSION_FORMS[$type]['subjects'][$subject])) {
            $errors['subject'] = 'Please choose one of the listed subjects.';
        }
    } else {
        // Forms that show no subject picker still store one, so the CMS list
        // and the CSV export have something meaningful in that column. It is
        // taken from the registry, never from the request.
        $subject = array_key_first(SUBMISSION_FORMS[$type]['subjects'] ?? []) ?? 'other';
    }

    if (in_array('message', $collects, true)) {
        if ($message === '') {
            $errors['message'] = 'Please tell us how we can help.';
        } elseif (mb_strlen($message) > SUBMISSION_MAX_MESSAGE) {
            $errors['message'] = 'Please keep your message under ' . number_format(SUBMISSION_MAX_MESSAGE) . ' characters.';
        }
    }

    // name and message are NOT NULL columns, so a form that does not collect
    // them stores an empty string rather than tripping strict mode.
    if (!in_array('name', $collects, true) && $name === '') {
        $name = '';
    }

    return [
        'values' => [
            'form_type' => $type,
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ],
        'errors' => $errors,
    ];
}

/**
 * True when this address has already sent SUBMISSION_MAX_PER_IP messages
 * inside the window.
 *
 * Counted from form_submissions itself rather than a separate throttle table:
 * the rows being counted are exactly the rows we are trying to limit, so the
 * count cannot drift from reality, and there is no second table to prune.
 * Trashed rows still count — deleting spam from the CMS should not hand the
 * sender a fresh allowance.
 */
function submissionFloodLimitReached(string $ip): bool
{
    $recent = (int) dbFetchColumn(
        'SELECT COUNT(*) FROM form_submissions
         WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL :minutes MINUTE)',
        ['ip' => $ip, 'minutes' => SUBMISSION_FLOOD_WINDOW_MINUTES],
        0
    );

    return $recent >= SUBMISSION_MAX_PER_IP;
}

/**
 * Stores a validated submission and returns its new id.
 *
 * @param array<string, string> $values as returned by submissionValidate()
 */
function submissionInsert(array $values, string $ip, ?string $userAgent): int
{
    return dbInsert(
        'INSERT INTO form_submissions (form_type, name, email, subject, message, ip_address, user_agent)
         VALUES (:form_type, :name, :email, :subject, :message, :ip, :agent)',
        [
            'form_type' => $values['form_type'],
            'name' => $values['name'],
            'email' => $values['email'],
            'subject' => $values['subject'],
            'message' => $values['message'],
            'ip' => $ip,
            // Truncated to the column width rather than left for MySQL to
            // reject: a long user-agent string must never be the reason a
            // visitor's message is lost.
            'agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]
    );
}

/**
 * How many submissions are still untriaged. Drives the sidebar badge and the
 * dashboard card, so both read the same number.
 */
function submissionUnreadCount(): int
{
    return (int) dbFetchColumn(
        "SELECT COUNT(*) FROM form_submissions WHERE status = 'new' AND deleted_at IS NULL",
        [],
        0
    );
}
