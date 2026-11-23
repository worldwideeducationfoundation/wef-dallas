<?php
/**
 * Drag-to-reorder support for the content lists.
 *
 * display_order was only editable by typing a number into each item's
 * edit form one at a time — to move an item up you had to guess a number,
 * save, and check the list. This lets the list itself be dragged, which
 * is what the column was always for.
 *
 * Responds with JSON, because the reorder happens over fetch() without
 * a page reload. Every other handler in this app is a plain form POST;
 * this is the one place that isn't, and only because reloading the page
 * after each drag would undo the point of dragging.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
// orderHeader() below builds a sortable column header, which lives in listing.php.
require_once __DIR__ . '/listing.php';

const REORDER_ALLOWED_TABLES = ['programs', 'team_members', 'gallery_items', 'annual_reports', 'sponsors', 'project_updates', 'completed_projects', 'success_stories', 'video_gallery', 'mission_projects', 'impact_milestones', 'hero_slides', 'partners', 'map_centers', 'donate_options'];

function reorderRespond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * @param array{table: string, module: string} $config
 */
function processReorder(array $config): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reorderRespond(405, ['error' => 'Method not allowed.']);
    }

    // 403, not 419: 419 is a Laravel convention, not an IANA-registered
    // status, and Apache rewrites unrecognised codes to a bare 500 —
    // which is what this returned before, hiding a clean rejection behind
    // what looked like a server crash.
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        reorderRespond(403, ['error' => 'Your session expired — please reload the page.']);
    }

    if (!can('content.edit')) {
        reorderRespond(403, ['error' => 'You do not have permission to reorder items.']);
    }

    $table = $config['table'];
    if (!in_array($table, REORDER_ALLOWED_TABLES, true)) {
        reorderRespond(400, ['error' => 'Unknown content type.']);
    }

    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['ids'] ?? [])),
        fn(int $id): bool => $id > 0
    )));

    if ($ids === []) {
        reorderRespond(400, ['error' => 'No items were supplied.']);
    }

    $db = getDb();

    // The submitted list is one page of results, so the new positions must
    // start from that page's existing lowest display_order rather than 0 —
    // otherwise reordering page 2 would jump those rows to the top of the
    // whole list.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $baseStmt = $db->prepare("SELECT COALESCE(MIN(display_order), 0) FROM {$table} WHERE id IN ({$placeholders})");
    $baseStmt->execute($ids);
    $base = (int) $baseStmt->fetchColumn();

    $db->beginTransaction();
    try {
        $update = $db->prepare("UPDATE {$table} SET display_order = ? WHERE id = ?");
        foreach ($ids as $index => $id) {
            $update->execute([$base + $index, $id]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    logActivity(currentUser()['id'], 'reorder', $config['module'], sprintf('Reordered %d item(s)', count($ids)));

    reorderRespond(200, ['ok' => true, 'count' => count($ids)]);
}

/**
 * The drag handle cell. Reordering only makes sense while the list is in
 * its own display order and unfiltered — once it's sorted by name or
 * filtered to a subset, "position 3" no longer refers to anything the
 * user can see, so the handle is withheld rather than silently writing a
 * confusing order.
 */
function reorderEnabled(array $params, array $scopeFilters = []): bool
{
    if (!can('content.edit') || $params['sort'] !== 'default' || $params['dir'] !== 'ASC' || $params['q'] !== '') {
        return false;
    }

    // $scopeFilters names filters that define a sub-list which is itself
    // ordered — Programs is ordered by (category, display_order), so
    // dragging is only meaningful once a single category is chosen.
    // Those filters must be present; any other filter disables dragging.
    foreach ($scopeFilters as $key) {
        if (!isset($params['filters'][$key])) {
            return false;
        }
    }

    return array_keys($params['filters']) === $scopeFilters
        || ($scopeFilters === [] && $params['filters'] === []);
}

/**
 * Header for the display_order column.
 *
 * Why the number is shown at all, when the list is already *in* that order:
 * display_order was invisible everywhere except inside an item's own edit
 * form. That made two ordinary questions unanswerable from the list — "what
 * number is this one on?" and "why is this item above that one?" — and it hid
 * the case that actually causes confusion, two items sharing the same number.
 * When that happens the tie is broken by name, so the list looks arbitrary and
 * dragging appears not to stick. With the numbers on screen the duplicate is
 * obvious at a glance.
 *
 * Sortable through the 'default' key, which every reorderable module already
 * maps to display_order.
 */
function orderHeader(array $params): string
{
    return listSortHeader('Order', 'default', $params);
}

/**
 * One row's display_order. Duplicates are flagged, because a repeated number
 * is the one value in this column that means something is wrong.
 *
 * @param list<string> $duplicates keys from duplicateOrders()
 * @param string       $group      this row's group value, for grouped lists
 */
function orderCell(?int $order, array $duplicates = [], string $group = ''): string
{
    $order = (int) $order;
    $isDuplicate = in_array($group . '|' . $order, $duplicates, true);

    return '<td class="col-order">'
        . '<span class="order-number' . ($isDuplicate ? ' is-duplicate' : '') . '"'
        . ($isDuplicate ? ' title="Another item here has the same order number, so their positions are decided by name instead."' : '')
        . '>' . $order . '</span>'
        . ($isDuplicate ? '<span class="sr-only"> — shares this order number with another item</span>' : '')
        . '</td>';
}

/**
 * The display_order values that repeat within a page of rows, as
 * "group|order" keys.
 *
 * $groupColumn exists because display_order is not always unique across a
 * whole table. Programs are ordered by (category, display_order, name), so
 * every category counts up from its own 1 and three programs numbered 1 in
 * three different categories is correct, not a clash. Passing 'category' scopes
 * the comparison so only a genuine collision inside one category is flagged.
 * Ungrouped lists pass nothing and every row shares the empty group.
 *
 * Computed per page rather than per table: this column only claims to explain
 * the order of what is currently on screen.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function duplicateOrders(array $rows, ?string $groupColumn = null): array
{
    $counts = [];
    foreach ($rows as $row) {
        $group = $groupColumn === null ? '' : (string) ($row[$groupColumn] ?? '');
        $key = $group . '|' . (int) ($row['display_order'] ?? 0);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    return array_values(array_keys(array_filter(
        $counts,
        static fn(int $n): bool => $n > 1
    )));
}

/**
 * The drag handle + keyboard move buttons for one row.
 */
function reorderCell(string $label): string
{
    return '<td class="col-drag">'
        . '<span class="drag-handle" aria-hidden="true">⠿</span>'
        . '<span class="move-buttons">'
        . '<button type="button" data-move="up" aria-label="Move ' . e($label) . ' up">▲</button>'
        . '<button type="button" data-move="down" aria-label="Move ' . e($label) . ' down">▼</button>'
        . '</span></td>';
}

/**
 * Opening <table> tag attributes that switch dragging on.
 */
function reorderTableAttributes(bool $enabled, string $endpoint = 'reorder.php'): string
{
    if (!$enabled) {
        return '';
    }

    return ' data-reorder-url="' . e($endpoint) . '" data-reorder-token="' . e(csrfToken()) . '"';
}

function reorderNotice(array $params): string
{
    if (!can('content.edit') || reorderEnabled($params)) {
        return '';
    }

    // Link to the bare script — clearing individual keys via listUrl()
    // would miss whichever module-specific filters are in play.
    $bare = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (isset($_GET['group_id'])) {
        $bare .= '?group_id=' . (int) $_GET['group_id'];
    }

    return '<p class="reorder-notice">Drag-to-reorder is available when the list is unfiltered and in its default order. '
        . '<a href="' . e($bare) . '">Reset the view</a> to rearrange items.</p>';
}
