<?php
/**
 * Shared pieces of the Submissions module.
 *
 * The bulk actions here are not the content modules' publish/draft/delete, so
 * this module does not use processBulkAction() from includes/bulk.php — an
 * enquiry is never "published". What it needs instead is the triage workflow,
 * which is defined once below and read by both the toolbar that offers the
 * actions and the handler that performs them, so the dropdown can never offer
 * something bulk.php would reject.
 */

require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

/**
 * The bulk actions this module offers, in the order they appear.
 *
 * 'status' is the value written for the four workflow moves; 'trash' is the
 * one action that is not a status change and is handled separately.
 *
 * @var array<string, array{label: string, done: string, status: ?string}>
 */
const SUBMISSION_BULK_ACTIONS = [
    'read' => ['label' => 'Mark as Read', 'done' => 'marked as read', 'status' => 'read'],
    'replied' => ['label' => 'Mark as Replied', 'done' => 'marked as replied', 'status' => 'replied'],
    'archived' => ['label' => 'Archive', 'done' => 'archived', 'status' => 'archived'],
    'new' => ['label' => 'Mark as New (unread)', 'done' => 'marked as new', 'status' => 'new'],
    'trash' => ['label' => 'Move to Trash', 'done' => 'moved to Trash', 'status' => null],
];

/**
 * Select-all + action dropdown above the list. Mirrors bulkToolbar() from
 * includes/bulk.php — same markup, same data-attributes, so admin.js drives it
 * with no changes — but offers this module's actions instead of the content
 * ones.
 */
function submissionBulkToolbar(): string
{
    if (!can('submissions.manage')) {
        return '';
    }

    $options = '';
    foreach (SUBMISSION_BULK_ACTIONS as $value => $action) {
        $options .= '<option value="' . e($value) . '">' . e($action['label']) . '</option>';
    }

    return '<div class="bulk-bar" data-bulk-bar hidden>'
        . '<span class="bulk-bar-count" data-bulk-count aria-live="polite"></span>'
        . '<label class="sr-only" for="bulk-action">Bulk action</label>'
        . '<select id="bulk-action" name="bulk_action" form="bulk-form" required>'
        . '<option value="">Choose an action…</option>' . $options
        . '</select>'
        . '<button type="submit" form="bulk-form" class="btn-filter">Apply</button>'
        . '</div>';
}

/**
 * Whether a newsletter signup reached the Constant Contact mailing list, as one
 * of four states.
 *
 * 'off'     — the integration is not connected or has no list, so there is
 *             nothing this row could have been added to. Renders as nothing:
 *             flagging every signup as un-synced on a site that does not sync
 *             would be noise, not information.
 * 'synced'  — on the list, with the time it got there.
 * 'failed'  — tried and did not make it. Carries the reason.
 * 'pending' — never attempted. Either the row predates the integration, or the
 *             push crashed before it could record anything.
 *
 * Only newsletter rows have any of these; contact and volunteer submissions are
 * never pushed anywhere and return 'off'.
 *
 * $integrationActive is passed in rather than read here so a list of 50 rows
 * asks the question once instead of 50 times.
 *
 * @param array<string, mixed> $row
 */
function submissionSyncState(array $row, bool $integrationActive): string
{
    if (($row['form_type'] ?? '') !== 'newsletter' || !$integrationActive) {
        return 'off';
    }

    if (!empty($row['newsletter_synced_at'])) {
        return 'synced';
    }

    return empty($row['newsletter_sync_error']) ? 'pending' : 'failed';
}

/**
 * The small badge the list shows for each newsletter row. Empty string for
 * anything with nothing to say, so the caller needs no conditional of its own.
 *
 * @param array<string, mixed> $row
 */
function submissionSyncBadge(array $row, bool $integrationActive): string
{
    $labels = [
        'synced' => ['On mailing list', 'replied'],
        'failed' => ['Not added to list', 'new'],
        'pending' => ['Not sent to list', 'archived'],
    ];

    $state = submissionSyncState($row, $integrationActive);
    if (!isset($labels[$state])) {
        return '';
    }

    [$label, $variant] = $labels[$state];

    // title carries the provider's own words on hover. Escaped like everything
    // else — it is text from Constant Contact, not from us.
    $title = $state === 'failed'
        ? ' title="' . e((string) $row['newsletter_sync_error']) . '"'
        : '';

    return '<span class="status-badge status-badge--' . $variant . '"' . $title . '>' . e($label) . '</span>';
}

/**
 * Writes a new status onto a set of submissions and returns how many changed.
 *
 * handled_by / handled_at record who last moved a submission along and when —
 * the question "did anyone deal with this?" is the whole point of the status
 * column, and a status with nobody's name against it only half answers it.
 * Both are cleared when a row is put back to 'new', so an untriaged row never
 * carries a stale claim that someone had already handled it.
 *
 * @param list<int> $ids
 */
function submissionSetStatus(array $ids, string $status, int $userId): int
{
    if (!isset(SUBMISSION_STATUSES[$status])) {
        adminLog('ERROR', "submissionSetStatus(): unknown status '{$status}' — nothing was changed.");
        return 0;
    }

    $placeholders = dbIdPlaceholders($ids);
    if ($placeholders['ids'] === []) {
        return 0;
    }

    $handledBy = $status === 'new' ? null : $userId;
    $handledAt = $status === 'new' ? null : date('Y-m-d H:i:s');

    return dbExecute(
        "UPDATE form_submissions
            SET status = ?, handled_by = ?, handled_at = ?
          WHERE id IN ({$placeholders['sql']}) AND deleted_at IS NULL",
        array_merge([$status, $handledBy, $handledAt], $placeholders['ids'])
    );
}
