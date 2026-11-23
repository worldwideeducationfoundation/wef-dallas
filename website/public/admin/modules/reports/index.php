<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Annual Reports';
$activeNav = 'Annual Reports';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'title' => 'title',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere($params, ['title', 'description'], ['status' => 'status'],
    [notTrashed()]);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM annual_reports' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, title, cover_image_path, pdf_path, display_order, status, updated_at FROM annual_reports'
    . $where['sql']
    . listOrderLimit($params, 'title DESC')
);
$rowsStmt->execute($where['params']);
$reports = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($reports);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Annual Reports</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Report</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search title or description…') ?>

        <?php if (!$reports): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No reports match that search.'
                : 'No annual reports yet. Add the first one to get started.' ?>
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
                    <input type="checkbox" data-bulk-all aria-label="Select all reports on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Cover</span></th>
                  <?= listSortHeader('Title', 'title', $params) ?>
                  <th>PDF</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $report): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $report['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $report['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($report['title']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($report['title']) ?><?php endif; ?>
                    <?= orderCell($report['display_order'], $dupeOrders) ?>
                    <td>
                      <?php if ($report['cover_image_path']): ?>
                        <img src="../../uploads/<?= e($report['cover_image_path']) ?>" alt="" class="data-table-avatar" loading="lazy">
                      <?php else: ?>
                        <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary"><?= e($report['title']) ?></td>
                    <td>
                      <?php if ($report['pdf_path']): ?>
                        <a class="link-edit" href="../../uploads/<?= e($report['pdf_path']) ?>" target="_blank" rel="noopener">Attached</a>
                      <?php else: ?>
                        <span class="status-badge status-badge--draft">Missing</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="status-badge status-badge--<?= e($report['status']) ?>"><?= e(ucfirst($report['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($report['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $report['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($report['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($report['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($report['title']) ?></span></button>
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
