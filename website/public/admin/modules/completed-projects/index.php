<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Completed Projects';
$activeNav = 'Completed Projects';
$linkBase = '../../';

// category is free text here (unlike programs' fixed 3-value set), so the
// filter's options come from what's actually in the table rather than a
// hardcoded list — this table already holds 8 different categories that
// were never enumerated anywhere before this module existed.
$categoryRows = getDb()->query(
    "SELECT DISTINCT category FROM completed_projects WHERE category IS NOT NULL AND category != '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);
$categoryLabels = array_combine($categoryRows, $categoryRows);

// 'default' reproduces the original ordering (category, then display
// order, then title) so the page looks unchanged until someone sorts.
$sortable = [
    'default' => 'category',
    'title' => 'title',
    'category' => 'category',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status', 'category']);
$where = listWhere(
    $params,
    ['title', 'slug', 'summary', 'location'],
    ['status' => 'status', 'category' => 'category'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM completed_projects' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, slug, category, title, image_path, completion_year, display_order, status, updated_at FROM completed_projects'
    . $where['sql']
    . listOrderLimit($params, $params['sort'] === 'default' ? 'display_order, title' : 'id')
);
$rowsStmt->execute($where['params']);
$projects = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];

// Same reasoning as Programs: display_order only means something within one
// category, so dragging only switches on once the list is narrowed to one.
$canReorder = reorderEnabled($params, ['category']);
$dupeOrders = duplicateOrders($projects, 'category');

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Completed Projects</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Completed Project</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
            'category' => ['label' => 'Categories', 'options' => $categoryLabels],
        ], 'Search title, slug, summary, or location…') ?>

        <?php if (!$projects): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No completed projects match that search.'
                : 'No completed projects yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('completed-projects') ?>
          <div class="listing-view" data-view-key="completed-projects">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?php if (!$canReorder && can('content.edit') && $params['sort'] === 'default' && $params['q'] === '' && !isset($params['filters']['category'])): ?>
            <p class="reorder-notice">Choose a single category above to drag projects into order within it.</p>
          <?php else: ?>
            <?= reorderNotice($params) ?>
          <?php endif; ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all completed projects on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Title', 'title', $params) ?>
                  <?= listSortHeader('Category', 'category', $params) ?>
                  <th>Year</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projects as $project): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $project['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $project['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($project['title']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($project['title']) ?><?php endif; ?>
                    <?= orderCell($project['display_order'], $dupeOrders, $project['category']) ?>
                    <td>
                      <div class="data-table-primary"><?= e($project['title']) ?></div>
                      <div class="data-table-secondary"><?= e($project['slug']) ?></div>
                    </td>
                    <td><?= e($project['category'] ?? '') ?></td>
                    <td><?= e($project['completion_year'] ?? '') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($project['status']) ?>"><?= e(ucfirst($project['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($project['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $project['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($project['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($project['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($project['title']) ?></span></button>
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
              <?php foreach ($projects as $project): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($project['title']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $project['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($project['image_path'])): ?>
                      <img src="../../uploads/<?= e($project['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(mb_substr($project['title'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($project['title']) ?></div>
                    <div class="grid-card-meta"><?= e($project['category'] ?? '') ?><?= !empty($project['completion_year']) ? ' &middot; ' . e($project['completion_year']) : '' ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($project['status']) ?>"><?= e(ucfirst($project['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $project['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($project['title']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($project['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($project['title']) ?></span></button>
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
