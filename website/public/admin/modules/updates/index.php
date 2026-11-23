<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Project Updates';
$activeNav = 'Project Updates';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'title' => 'title',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere($params, ['title', 'content'], ['status' => 'status'],
    [notTrashed()]);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM project_updates' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, title, image_path, display_order, status, updated_at FROM project_updates'
    . $where['sql']
    . listOrderLimit($params, 'title DESC')
);
$rowsStmt->execute($where['params']);
$updates = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($updates);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Project Updates</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Update</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search title or content…') ?>

        <?php if (!$updates): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No updates match that search.'
                : 'No project updates yet. Add the first one to get started.' ?>
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
                    <input type="checkbox" data-bulk-all aria-label="Select all updates on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Image</span></th>
                  <?= listSortHeader('Title', 'title', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($updates as $update): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $update['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $update['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($update['title']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($update['title']) ?><?php endif; ?>
                    <?= orderCell($update['display_order'], $dupeOrders) ?>
                    <td>
                      <?php if ($update['image_path']): ?>
                        <img src="../../uploads/<?= e($update['image_path']) ?>" alt="" class="data-table-avatar" loading="lazy">
                      <?php else: ?>
                        <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary"><?= e($update['title']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($update['status']) ?>"><?= e(ucfirst($update['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($update['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $update['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($update['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($update['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $update['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($update['title']) ?></span></button>
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
