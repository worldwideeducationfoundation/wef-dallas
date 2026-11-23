<?php
/**
 * Shared search / sort / pagination for the module list pages.
 *
 * Every index.php previously ran a bare "SELECT ... ORDER BY" and printed
 * the whole table. That is fine at 9 programs and already awkward at 47
 * sponsors. This centralises the request parsing, the SQL fragments, and
 * the controls, so each module opts in with a few lines instead of
 * growing its own slightly different copy.
 *
 * The sort column never comes from the request directly: callers pass an
 * allow-list mapping a public key ("name") to a real column
 * ("p.name"), and anything not in it falls back to the default. That is
 * what keeps ORDER BY safe, since it can't be a bound parameter.
 */

// 100, not 20: at 20 a list with more items (26 team members, 47 sponsors)
// split onto a second page that was easy to miss - members that were on the
// website looked missing from the admin. Nothing on the site comes close to
// 100, so every list shows everything at once (and drag-to-reorder can reach
// every row). The activity log, which grows without end, sets its own 30.
const LIST_PER_PAGE = 100;

/**
 * @param array<string, string> $sortable public key => SQL column expression
 * @return array{q: string, sort: string, sortSql: string, dir: string, page: int, perPage: int, offset: int, filters: array<string, string>}
 */
function listParams(array $sortable, string $defaultSort, array $filterKeys = [], int $perPage = LIST_PER_PAGE): array
{
    $sort = (string) ($_GET['sort'] ?? $defaultSort);
    if (!isset($sortable[$sort])) {
        $sort = $defaultSort;
    }

    $dir = strtoupper((string) ($_GET['dir'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';
    $page = max(1, (int) ($_GET['page'] ?? 1));

    $filters = [];
    foreach ($filterKeys as $key) {
        $value = trim((string) ($_GET[$key] ?? ''));
        if ($value !== '') {
            $filters[$key] = $value;
        }
    }

    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'sort' => $sort,
        'sortSql' => $sortable[$sort],
        'dir' => $dir,
        'page' => $page,
        'perPage' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'filters' => $filters,
    ];
}

/**
 * Builds the WHERE fragment for a free-text search across $columns plus
 * any exact-match filters. Returns the SQL and the bound parameters —
 * values are always parameters, only column names are interpolated and
 * those come from the calling page, never from the request.
 *
 * @param list<string> $searchColumns
 * @param array<string, string> $filterColumns filter key => SQL column
 * @return array{sql: string, params: array<string, string>}
 */
function listWhere(array $params, array $searchColumns, array $filterColumns = [], array $alwaysConditions = []): array
{
    // Conditions that apply regardless of what the user searched or
    // filtered — in practice "deleted_at IS NULL", so trashed rows can
    // never surface in a list no matter which filters are active. Passed
    // in by the caller rather than assumed here, because this helper is
    // also used by tables that have no soft delete (activity_logs).
    $clauses = $alwaysConditions;
    $bound = [];

    if ($params['q'] !== '' && $searchColumns !== []) {
        $ors = [];
        foreach ($searchColumns as $i => $column) {
            $ors[] = "{$column} LIKE :q{$i}";
            // Escape LIKE wildcards so a literal "%" searches for "%".
            $bound["q{$i}"] = '%' . addcslashes($params['q'], '%_\\') . '%';
        }
        $clauses[] = '(' . implode(' OR ', $ors) . ')';
    }

    foreach ($params['filters'] as $key => $value) {
        if (!isset($filterColumns[$key])) {
            continue;
        }
        $clauses[] = "{$filterColumns[$key]} = :f_{$key}";
        $bound["f_{$key}"] = $value;
    }

    return [
        'sql' => $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses),
        'params' => $bound,
    ];
}

/**
 * ORDER BY + LIMIT tail. LIMIT/OFFSET are inlined as integers rather than
 * bound, because MySQL will not accept placeholders there when emulated
 * prepares are off (which they are — see includes/database.php). They are
 * cast to int on the way in, so there is nothing injectable left.
 */
function listOrderLimit(array $params, string $tieBreaker = ''): string
{
    $order = $params['sortSql'] . ' ' . $params['dir'];
    if ($tieBreaker !== '') {
        $order .= ', ' . $tieBreaker;
    }

    return ' ORDER BY ' . $order . ' LIMIT ' . (int) $params['perPage'] . ' OFFSET ' . (int) $params['offset'];
}

/**
 * Rebuilds the current query string with some keys replaced — used by the
 * sort headers and pager so they preserve the active search and filters.
 */
function listUrl(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return $query === [] ? '?' : '?' . http_build_query($query);
}

/**
 * A sortable column header. Clicking the active column flips direction.
 */
function listSortHeader(string $label, string $key, array $params): string
{
    $isActive = $params['sort'] === $key;
    $nextDir = $isActive && $params['dir'] === 'ASC' ? 'desc' : 'asc';
    $arrow = $isActive ? ($params['dir'] === 'ASC' ? ' ▲' : ' ▼') : '';
    $ariaSort = $isActive ? ($params['dir'] === 'ASC' ? 'ascending' : 'descending') : 'none';

    return sprintf(
        '<th aria-sort="%s"><a class="list-sort%s" href="%s">%s%s</a></th>',
        $ariaSort,
        $isActive ? ' is-active' : '',
        e(listUrl(['sort' => $key, 'dir' => $nextDir, 'page' => null])),
        e($label),
        $arrow
    );
}

/**
 * Search box + dropdown filters, as a plain GET form so results are
 * linkable and the back button behaves. Submits on change for the
 * selects and on enter for the text box; the Search button is kept for
 * anyone navigating by keyboard or with JS off.
 *
 * @param array<string, array{label: string, options: array<string, string>}> $filterOptions
 */
function listToolbar(array $params, array $filterOptions = [], string $searchPlaceholder = 'Search…'): string
{
    $html = '<form method="get" class="list-toolbar" role="search">';

    // Sort state travels with the search so filtering doesn't reset it.
    foreach (['sort' => $params['sort'], 'dir' => strtolower($params['dir'])] as $key => $value) {
        $html .= '<input type="hidden" name="' . e($key) . '" value="' . e((string) $value) . '">';
    }
    if (isset($_GET['group_id'])) {
        $html .= '<input type="hidden" name="group_id" value="' . (int) $_GET['group_id'] . '">';
    }

    $html .= '<div class="list-search">';
    $html .= '<label class="sr-only" for="list-q">' . e($searchPlaceholder) . '</label>';
    $html .= '<input type="search" id="list-q" name="q" value="' . e($params['q']) . '" placeholder="' . e($searchPlaceholder) . '">';
    $html .= '</div>';

    foreach ($filterOptions as $key => $config) {
        $current = $params['filters'][$key] ?? '';
        $html .= '<div class="list-filter">';
        $html .= '<label class="sr-only" for="list-' . e($key) . '">' . e($config['label']) . '</label>';
        $html .= '<select id="list-' . e($key) . '" name="' . e($key) . '" data-list-autosubmit>';
        $html .= '<option value="">All ' . e($config['label']) . '</option>';
        foreach ($config['options'] as $value => $label) {
            $selected = (string) $value === $current ? ' selected' : '';
            $html .= '<option value="' . e((string) $value) . '"' . $selected . '>' . e($label) . '</option>';
        }
        $html .= '</select></div>';
    }

    $html .= '<button type="submit" class="btn-filter">Search</button>';

    if ($params['q'] !== '' || $params['filters'] !== []) {
        $html .= '<a class="list-clear" href="' . e(listUrl(['q' => null, 'page' => null] + array_fill_keys(array_keys($filterOptions), null))) . '">Clear</a>';
    }

    return $html . '</form>';
}

/**
 * List/grid view toggle for a listing page. The chosen view persists per
 * module via localStorage (admin.js's initViewToggle()) — nothing is
 * stored server-side, so it's a per-browser display preference, not data,
 * and a fresh browser (or one with storage blocked) always starts on List.
 *
 * The caller wraps its existing table markup and a parallel grid of cards
 * as two sibling .view-panel elements inside one
 * <div class="listing-view" data-view-key="…"> using the same $moduleKey —
 * this function only renders the two buttons that switch between them.
 */
function viewToggle(string $moduleKey): string
{
    return '<div class="view-toggle" role="group" aria-label="Choose view" data-view-toggle="' . e($moduleKey) . '">'
        . '<button type="button" class="view-toggle-btn" data-view-set="list" aria-pressed="true">'
        . '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><line x1="4" y1="5" x2="16" y2="5"/><line x1="4" y1="10" x2="16" y2="10"/><line x1="4" y1="15" x2="16" y2="15"/></svg>'
        . '<span>List</span>'
        . '</button>'
        . '<button type="button" class="view-toggle-btn" data-view-set="grid" aria-pressed="false">'
        . '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/></svg>'
        . '<span>Grid</span>'
        . '</button>'
        . '</div>';
}

/**
 * Pager. Renders nothing at all when everything fits on one page, so
 * short lists look exactly as they did before pagination existed.
 */
function listPagination(int $total, array $params): string
{
    $pages = (int) ceil($total / max(1, $params['perPage']));
    if ($pages <= 1) {
        return '';
    }

    $page = min($params['page'], $pages);
    $from = $params['offset'] + 1;
    $to = min($params['offset'] + $params['perPage'], $total);

    $html = '<nav class="list-pagination" aria-label="Pagination">';
    $html .= '<span class="list-pagination-status">Showing ' . $from . '–' . $to . ' of ' . $total . '</span>';
    $html .= '<span class="list-pagination-links">';

    if ($page > 1) {
        $html .= '<a href="' . e(listUrl(['page' => $page - 1])) . '" rel="prev">&larr; Prev</a>';
    } else {
        $html .= '<span class="is-disabled">&larr; Prev</span>';
    }

    $html .= '<span class="list-pagination-page">Page ' . $page . ' of ' . $pages . '</span>';

    if ($page < $pages) {
        $html .= '<a href="' . e(listUrl(['page' => $page + 1])) . '" rel="next">Next &rarr;</a>';
    } else {
        $html .= '<span class="is-disabled">Next &rarr;</span>';
    }

    return $html . '</span></nav>';
}
