<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Success Stories';
$activeNav = 'Success Stories';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'title' => 'title',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['title', 'tag'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM success_stories' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, title, tag, story_date, image_path, display_order, status, updated_at FROM success_stories'
    . $where['sql']
    . listOrderLimit($params, 'title')
);
$rowsStmt->execute($where['params']);
$stories = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($stories);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Success Stories</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Success Story</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search title or tag…') ?>

        <?php if (!$stories): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No success stories match that search.'
                : 'No success stories yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('success-stories') ?>
          <div class="listing-view" data-view-key="success-stories">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all success stories on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Title', 'title', $params) ?>
                  <th>Tag</th>
                  <th>Date</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($stories as $story): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $story['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $story['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($story['title']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($story['title']) ?><?php endif; ?>
                    <?= orderCell($story['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary"><?= e($story['title']) ?></td>
                    <td><?= e($story['tag'] ?? '') ?></td>
                    <td><?= e($story['story_date'] ?? '') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($story['status']) ?>"><?= e(ucfirst($story['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($story['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $story['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($story['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($story['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $story['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($story['title']) ?></span></button>
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
              <?php foreach ($stories as $story): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($story['title']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $story['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($story['image_path'])): ?>
                      <img src="../../uploads/<?= e($story['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(mb_substr($story['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($story['title']) ?></div>
                    <div class="grid-card-meta"><?= e($story['tag'] ?? '') ?><?= !empty($story['story_date']) ? ' &middot; ' . e($story['story_date']) : '' ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($story['status']) ?>"><?= e(ucfirst($story['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $story['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($story['title']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($story['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $story['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($story['title']) ?></span></button>
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
