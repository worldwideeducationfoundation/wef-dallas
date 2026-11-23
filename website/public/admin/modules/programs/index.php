<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Programs';
$activeNav = 'Programs';
$linkBase = '../../';

$categoryLabels = [
    'early_childhood' => 'Early Childhood',
    'programs' => 'Programs',
    'scholarships' => 'Scholarships',
];

// 'default' reproduces the original ordering (category, then display
// order, then name) so the page looks unchanged until someone sorts.
$sortable = [
    'default' => 'category',
    'name' => 'name',
    'category' => 'category',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status', 'category']);
$where = listWhere(
    $params,
    ['name', 'slug', 'summary'],
    ['status' => 'status', 'category' => 'category'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM programs' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, slug, category, name, image_path, display_order, status, updated_at FROM programs'
    . $where['sql']
    . listOrderLimit($params, $params['sort'] === 'default' ? 'display_order, name' : 'id')
);
$rowsStmt->execute($where['params']);
$programs = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];

// Programs are ordered by (category, display_order), so a drag only means
// something once the list is narrowed to one category — otherwise the
// user would be rearranging rows that the default ordering will regroup
// anyway. Choosing a category in the filter bar switches dragging on.
$canReorder = reorderEnabled($params, ['category']);

// Grouped by category for the same reason dragging is: each category counts
// from its own 1, so only a repeat *within* one category is a real clash.
$dupeOrders = duplicateOrders($programs, 'category');

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Programs</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Program</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
            'category' => ['label' => 'Categories', 'options' => $categoryLabels],
        ], 'Search name, slug, or summary…') ?>

        <?php if (!$programs): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No programs match that search.'
                : 'No programs yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('programs') ?>
          <div class="listing-view" data-view-key="programs">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?php if (!$canReorder && can('content.edit') && $params['sort'] === 'default' && $params['q'] === '' && !isset($params['filters']['category'])): ?>
            <p class="reorder-notice">Choose a single category above to drag programs into order within it.</p>
          <?php else: ?>
            <?= reorderNotice($params) ?>
          <?php endif; ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all programs on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Name', 'name', $params) ?>
                  <?= listSortHeader('Category', 'category', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($programs as $program): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $program['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $program['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($program['name']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($program['name']) ?><?php endif; ?>
                    <?= orderCell($program['display_order'], $dupeOrders, $program['category']) ?>
                    <td>
                      <div class="data-table-primary"><?= e($program['name']) ?></div>
                      <div class="data-table-secondary"><?= e($program['slug']) ?></div>
                    </td>
                    <td><?= e($categoryLabels[$program['category']] ?? $program['category']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($program['status']) ?>"><?= e(ucfirst($program['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($program['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $program['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($program['name']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($program['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $program['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($program['name']) ?></span></button>
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
              <?php foreach ($programs as $program): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($program['name']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $program['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($program['image_path'])): ?>
                      <img src="../../uploads/<?= e($program['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(mb_substr($program['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($program['name']) ?></div>
                    <div class="grid-card-meta"><?= e($categoryLabels[$program['category']] ?? $program['category']) ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($program['status']) ?>"><?= e(ucfirst($program['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $program['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($program['name']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($program['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $program['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($program['name']) ?></span></button>
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
