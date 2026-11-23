<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$groupId = isset($_GET['group_id']) ? (int) $_GET['group_id'] : null;

if (!$groupId) {
    flashSet('error', 'No sponsor group specified.');
    redirect('index.php');
}

$groupStmt = getDb()->prepare('SELECT id, title FROM sponsor_groups WHERE id = :id LIMIT 1');
$groupStmt->execute(['id' => $groupId]);
$group = $groupStmt->fetch();

if (!$group) {
    flashSet('error', 'That sponsor group could not be found.');
    redirect('index.php');
}

$pageTitle = $group['title'];
$activeNav = 'Honor Board / Sponsors';

$sortable = [
    'default' => 'display_order',
    'name' => 'name',
    'badge' => 'badge_text',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere($params, ['name', 'badge_text'], ['status' => 'status'],
    [notTrashed()]);

// The group filter is always applied, on top of whatever the toolbar
// produced — this page only ever lists one group's sponsors.
$whereSql = ($where['sql'] === '' ? ' WHERE ' : $where['sql'] . ' AND ') . 'group_id = :group_id';
$bound = $where['params'] + ['group_id' => $groupId];

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM sponsors' . $whereSql);
$countStmt->execute($bound);
$total = (int) $countStmt->fetchColumn();

$sponsorsStmt = getDb()->prepare(
    'SELECT id, name, badge_text, display_order, status, updated_at FROM sponsors'
    . $whereSql
    . listOrderLimit($params, 'name')
);
$sponsorsStmt->execute($bound);
$sponsors = $sponsorsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
// Already scoped to one group by the page itself, so no extra filter is
// needed to make an order meaningful here.
$canReorder = reorderEnabled($params);
// The page is already scoped to one group, so no grouping key is needed —
// every row on screen is competing for position within this group.
$dupeOrders = duplicateOrders($sponsors);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <a href="index.php" class="link-back">&larr; Back to Honor Board</a>

        <div class="module-header">
          <h1><?= e($group['title']) ?></h1>
          <?php if (can('content.create')): ?>
          <a href="sponsor-form.php?group_id=<?= (int) $groupId ?>" class="btn-primary">+ Add Sponsor</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search sponsors…') ?>

        <?php if (!$sponsors): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No sponsors in this group match that search.'
                : 'No sponsors in this group yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="sponsor-bulk.php" id="bulk-form">
            <?= csrfField() ?>
            <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
          </form>
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder, 'sponsor-reorder.php') ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all sponsors on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Name', 'name', $params) ?>
                  <?= listSortHeader('Badge', 'badge', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sponsors as $sponsor): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $sponsor['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $sponsor['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($sponsor['name']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($sponsor['name']) ?><?php endif; ?>
                    <?= orderCell($sponsor['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary"><?= e($sponsor['name']) ?></td>
                    <td><?= e($sponsor['badge_text'] ?? '—') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($sponsor['status']) ?>"><?= e(ucfirst($sponsor['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($sponsor['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="sponsor-form.php?id=<?= (int) $sponsor['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($sponsor['name']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="sponsor-delete.php" data-confirm="Delete &quot;<?= e($sponsor['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $sponsor['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($sponsor['name']) ?></span></button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($total, $params) ?>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
