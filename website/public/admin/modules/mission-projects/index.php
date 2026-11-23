<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Mission Projects';
$activeNav = 'Mission Projects';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'title' => 'what_header',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['what_header', 'what_sub'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM mission_projects' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, what_header, what_sub, launch_year, display_order, status, updated_at FROM mission_projects'
    . $where['sql']
    . listOrderLimit($params, 'what_header')
);
$rowsStmt->execute($where['params']);
$projects = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($projects);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Mission Projects</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Project</a>
          <?php endif; ?>
        </div>
        <p class="field-hint">The "WEF At A Glance" table on the Our Mission page — WHAT / WHERE / WHO / IMPACT / NOTES.</p>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search project name…') ?>

        <?php if (!$projects): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No mission projects match that search.'
                : 'No mission projects yet. Add the first one to get started.' ?>
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
                    <input type="checkbox" data-bulk-all aria-label="Select all mission projects on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Project', 'title', $params) ?>
                  <th>Launched</th>
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
                             data-bulk-item aria-label="Select <?= e($project['what_header']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($project['what_header']) ?><?php endif; ?>
                    <?= orderCell($project['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary">
                      <?= e($project['what_header']) ?>
                      <?php if (!empty($project['what_sub'])): ?><br><span class="field-hint" style="margin-top:0;"><?= e($project['what_sub']) ?></span><?php endif; ?>
                    </td>
                    <td><?= e($project['launch_year'] ?? '') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($project['status']) ?>"><?= e(ucfirst($project['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($project['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $project['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($project['what_header']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($project['what_header']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($project['what_header']) ?></span></button>
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
