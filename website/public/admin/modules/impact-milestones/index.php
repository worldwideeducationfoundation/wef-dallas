<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Impact Goals Roadmap';
$activeNav = 'Impact Goals Roadmap';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'date' => 'milestone_date',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['milestone_date'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM impact_milestones' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, milestone_date, centers_target, students_target, teachers_target, display_order, status, updated_at FROM impact_milestones'
    . $where['sql']
    . listOrderLimit($params, 'milestone_date')
);
$rowsStmt->execute($where['params']);
$milestones = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($milestones);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Impact Goals Roadmap</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Milestone</a>
          <?php endif; ?>
        </div>
        <p class="field-hint">The 5-point roadmap chart on the Impact Goals page. Only the first 5 active milestones, in this order, appear on the chart — its layout is a fixed 5-point curve, not an arbitrary-length list.</p>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search date…') ?>

        <?php if (!$milestones): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No milestones match that search.'
                : 'No milestones yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all milestones on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Date', 'date', $params) ?>
                  <th>Centers</th>
                  <th>Students</th>
                  <th>Teachers</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($milestones as $milestone): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $milestone['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $milestone['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($milestone['milestone_date']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($milestone['milestone_date']) ?><?php endif; ?>
                    <?= orderCell($milestone['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary"><?= e($milestone['milestone_date']) ?></td>
                    <td><?= number_format((int) $milestone['centers_target']) ?></td>
                    <td><?= number_format((int) $milestone['students_target']) ?></td>
                    <td><?= number_format((int) $milestone['teachers_target']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($milestone['status']) ?>"><?= e(ucfirst($milestone['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($milestone['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $milestone['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($milestone['milestone_date']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete the &quot;<?= e($milestone['milestone_date']) ?>&quot; milestone? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $milestone['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($milestone['milestone_date']) ?></span></button>
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
