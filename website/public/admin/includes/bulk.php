<?php
/**
 * Shared bulk-action handler for the content modules.
 *
 * Each module's bulk.php is a few lines describing its table; all the
 * actual work — CSRF, capability checks, id validation, file cleanup,
 * audit logging — happens here once. The alternative was five copies of
 * the same 60 lines, which is exactly the kind of drift that ends with
 * one module forgetting to delete its orphaned images.
 *
 * The table name is interpolated into SQL, so it is taken from the
 * caller's own config array and additionally checked against
 * BULK_ALLOWED_TABLES. It never comes from the request.
 */

require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/trash.php';

const BULK_ALLOWED_TABLES = ['programs', 'team_members', 'gallery_items', 'annual_reports', 'sponsors', 'project_updates', 'completed_projects', 'success_stories', 'video_gallery', 'mission_projects', 'impact_milestones', 'hero_slides', 'partners', 'map_centers', 'donate_options'];

const BULK_ACTIONS = [
    'publish' => ['capability' => 'content.edit', 'label' => 'published'],
    'draft' => ['capability' => 'content.edit', 'label' => 'moved to draft'],
    'delete' => ['capability' => 'content.delete', 'label' => 'moved to Trash'],
];

/**
 * @param array{table: string, module: string, labelColumn: string, fileColumns?: list<string>, redirect?: string} $config
 */
function processBulkAction(array $config): void
{
    $redirect = $config['redirect'] ?? 'index.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($redirect);
    }

    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        flashSet('error', 'Your session expired — please try again.');
        redirect($redirect);
    }

    $table = $config['table'];
    if (!in_array($table, BULK_ALLOWED_TABLES, true)) {
        flashSet('error', 'Unknown content type.');
        redirect($redirect);
    }

    $action = (string) ($_POST['bulk_action'] ?? '');
    if (!isset(BULK_ACTIONS[$action])) {
        flashSet('error', 'Please choose an action to apply.');
        redirect($redirect);
    }

    requireCapability(BULK_ACTIONS[$action]['capability'], $redirect);

    // Cast every id to int and drop anything that isn't a positive
    // number, so the IN (...) list below is only ever digits.
    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['ids'] ?? [])),
        fn(int $id): bool => $id > 0
    )));

    if ($ids === []) {
        flashSet('error', 'No items were selected.');
        redirect($redirect);
    }

    $db = getDb();
    $user = currentUser();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($action === 'delete') {
        // Labels are read before the move purely so the audit entry can
        // name what went to Trash. Nothing is unlinked here — the files
        // have to survive for a restore to mean anything.
        $select = $db->prepare(
            "SELECT {$config['labelColumn']} FROM {$table}
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $select->execute($ids);
        $rows = $select->fetchAll();

        if ($rows === []) {
            flashSet('error', 'Those items could not be found.');
            redirect($redirect);
        }

        $count = trashItems($config['trashType'] ?? $config['module'], $ids);

        logActivity(
            $user['id'],
            'delete',
            $config['module'],
            sprintf('Bulk moved %d to Trash: %s', $count, implode(', ', array_column($rows, $config['labelColumn'])))
        );
    } else {
        $status = $action === 'publish' ? 'active' : 'draft';
        $update = $db->prepare(
            "UPDATE {$table} SET status = ? WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $update->execute(array_merge([$status], $ids));
        $count = $update->rowCount();

        logActivity(
            $user['id'],
            'update',
            $config['module'],
            sprintf('Bulk %s %d item(s)', BULK_ACTIONS[$action]['label'], count($ids))
        );
    }

    flashSet('success', sprintf(
        '%d item%s %s.',
        $count,
        $count === 1 ? '' : 's',
        BULK_ACTIONS[$action]['label']
    ));

    redirect($redirect);
}

/**
 * The select-all checkbox + action dropdown shown above a list. Rendered
 * only when the user can actually do at least one of the actions.
 */
function bulkToolbar(): string
{
    $canEdit = can('content.edit');
    $canDelete = can('content.delete');

    if (!$canEdit && !$canDelete) {
        return '';
    }

    $options = '';
    if ($canEdit) {
        $options .= '<option value="publish">Publish (set Active)</option>';
        $options .= '<option value="draft">Move to Draft</option>';
    }
    if ($canDelete) {
        // "Move to Trash", not "Delete permanently": this action sets
        // deleted_at and the item stays recoverable. The old label contradicted
        // both the action and its own confirmation message ("moved to Trash"),
        // which teaches people to distrust the wording — and risks the opposite
        // mistake, where someone avoids a reversible action believing it is not.
        $options .= '<option value="delete">Move to Trash</option>';
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
