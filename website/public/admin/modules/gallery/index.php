<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Gallery';
$activeNav = 'Gallery';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'caption' => 'caption',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere($params, ['caption'], ['status' => 'status'],
    [notTrashed()]);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM gallery_items' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, caption, image_path, display_order, status, updated_at,
            (SELECT p.name FROM programs p WHERE p.id = gallery_items.program_id AND p.deleted_at IS NULL) AS project_name
     FROM gallery_items'
    . $where['sql']
    . listOrderLimit($params, 'caption')
);
$rowsStmt->execute($where['params']);
$items = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($items);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Gallery</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Photo</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search captions…') ?>

        <?php if (!$items): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No photos match that search.'
                : 'No gallery photos yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('gallery') ?>
          <div class="listing-view" data-view-key="gallery">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all photos on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Photo</span></th>
                  <?= listSortHeader('Caption', 'caption', $params) ?>
                  <th>Project</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $item['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $item['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($item['caption']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($item['caption']) ?><?php endif; ?>
                    <?= orderCell($item['display_order'], $dupeOrders) ?>
                    <td>
                      <?php if ($item['image_path']): ?>
                        <img src="../../uploads/<?= e($item['image_path']) ?>" alt="" class="data-table-avatar" loading="lazy">
                      <?php else: ?>
                        <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary"><?= e($item['caption']) ?></td>
                    <td><?= e($item['project_name'] ?? 'General') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($item['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $item['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($item['caption']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($item['caption']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($item['caption']) ?></span></button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($total, $params) ?>
          </div>

          <div class="view-panel view-panel--grid">
            <div class="grid-view-cards">
              <?php foreach ($items as $item): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($item['caption']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $item['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($item['image_path'])): ?>
                      <img src="../../uploads/<?= e($item['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true">—</div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($item['caption']) ?></div>
                    <div class="grid-card-meta"><?= e($item['project_name'] ?? 'General') ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $item['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($item['caption']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($item['caption']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($item['caption']) ?></span></button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          </div>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
