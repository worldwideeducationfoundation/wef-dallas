<?php
/**
 * Activity log viewer.
 *
 * logActivity() has been writing to activity_logs since Phase 1, but
 * nothing ever read it back — the audit trail existed and was invisible.
 * This is the read side: searchable, filterable by module and action, and
 * paginated, because this is the one table that only ever grows.
 *
 * Read-only by design. There is no delete here, deliberately: an audit
 * log an admin can edit isn't an audit log.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';

requireCapability('activity.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Activity Log';
$activeNav = 'Activity Log';
$linkBase = '../../';

$sortable = [
    'when' => 'l.created_at',
    'user' => 'u.name',
    'action' => 'l.action',
    'module' => 'l.module',
];

$params = listParams($sortable, 'when', ['module', 'action'], 30);
if (!isset($_GET['dir'])) {
    $params['dir'] = 'DESC'; // newest first is the only sensible default here
}

$where = listWhere(
    $params,
    ['u.name', 'u.email', 'l.details', 'l.ip_address'],
    ['module' => 'l.module', 'action' => 'l.action']
);

$baseSql = ' FROM activity_logs l LEFT JOIN admin_users u ON u.id = l.user_id' . $where['sql'];

$countStmt = getDb()->prepare('SELECT COUNT(*)' . $baseSql);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT l.id, l.action, l.module, l.details, l.ip_address, l.created_at,
            u.name AS user_name, u.email AS user_email'
    . $baseSql . listOrderLimit($params, 'l.id DESC')
);
$rowsStmt->execute($where['params']);
$entries = $rowsStmt->fetchAll();

// Filter dropdowns are built from what is actually in the table, so they
// stay correct as new modules start logging without anyone maintaining a
// hardcoded list here.
$modules = getDb()->query('SELECT DISTINCT module FROM activity_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
$actions = getDb()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$titleCase = fn(string $value): string => ucwords(str_replace('_', ' ', $value));

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Activity Log</h1>
        </div>

        <?= listToolbar(
            $params,
            [
                'module' => ['label' => 'Modules', 'options' => array_combine($modules, array_map($titleCase, $modules))],
                'action' => ['label' => 'Actions', 'options' => array_combine($actions, array_map($titleCase, $actions))],
            ],
            'Search user, details, or IP…'
        ) ?>

        <?php if (!$entries): ?>
          <div class="empty-state">
            <?= $params['q'] !== '' || $params['filters'] !== []
                ? 'No activity matches that search.'
                : 'No activity recorded yet.' ?>
          </div>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <?= listSortHeader('When', 'when', $params) ?>
                  <?= listSortHeader('User', 'user', $params) ?>
                  <?= listSortHeader('Action', 'action', $params) ?>
                  <?= listSortHeader('Module', 'module', $params) ?>
                  <th>Details</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($entries as $entry): ?>
                  <tr>
                    <td>
                      <div class="data-table-primary"><?= e(date('M j, Y', strtotime($entry['created_at']))) ?></div>
                      <div class="data-table-secondary"><?= e(date('g:i a', strtotime($entry['created_at']))) ?></div>
                    </td>
                    <td>
                      <?php if ($entry['user_name'] !== null): ?>
                        <div class="data-table-primary"><?= e($entry['user_name']) ?></div>
                        <div class="data-table-secondary"><?= e($entry['user_email']) ?></div>
                      <?php else: ?>
                        <span class="data-table-secondary">Deleted user</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="status-badge status-badge--<?= e($entry['action']) ?>"><?= e($titleCase($entry['action'])) ?></span></td>
                    <td><?= e($titleCase($entry['module'])) ?></td>
                    <td><?= $entry['details'] !== null ? e($entry['details']) : '<span class="data-table-secondary">—</span>' ?></td>
                    <td class="data-table-secondary"><?= e($entry['ip_address']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($total, $params) ?>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
