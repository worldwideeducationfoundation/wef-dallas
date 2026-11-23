<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';
require_once __DIR__ . '/_options.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Map Centers';
$activeNav = 'Map Centers';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'village' => 'village',
    'district' => 'district',
    'model' => 'center_model',
    'sponsor' => 'sponsor',
    'status' => 'status',
];

$params = listParams($sortable, 'default', ['status', 'district', 'center_model']);
$where = listWhere(
    $params,
    ['village', 'sponsor', 'lead_name'],
    ['status' => 'status', 'district' => 'district', 'center_model' => 'center_model'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM map_centers' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, lead_name, sponsor, village, district, center_model, latitude, longitude, display_order, status FROM map_centers'
    . $where['sql']
    . listOrderLimit($params, 'village')
);
$rowsStmt->execute($where['params']);
$centers = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($centers);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Map Centers</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Center</a>
          <?php endif; ?>
        </div>

        <div class="welcome-card">
          <p>The learning centers on the home page's interactive map. Active centers appear on the map straight away;
            Draft keeps one off the map without deleting it. The sponsor filter on the map lists every sponsor named here,
            and clicking a sponsor on the Honor Board shows that sponsor's centers - so spell sponsors the same way in both places.</p>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
            'district' => ['label' => 'Districts', 'options' => array_combine(MAP_CENTER_DISTRICTS, MAP_CENTER_DISTRICTS)],
            'center_model' => ['label' => 'Types', 'options' => array_combine(MAP_CENTER_MODELS, MAP_CENTER_MODELS)],
        ], 'Search village, sponsor or center lead…') ?>

        <?php if (!$centers): ?>
          <div class="empty-state">
            <?= $isFiltered ? 'No centers match that search.' : 'No centers yet. Add the first one to put it on the map.' ?>
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
                    <input type="checkbox" data-bulk-all aria-label="Select all centers on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Village', 'village', $params) ?>
                  <?= listSortHeader('District', 'district', $params) ?>
                  <?= listSortHeader('Type', 'model', $params) ?>
                  <?= listSortHeader('Sponsor', 'sponsor', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($centers as $center): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $center['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $center['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($center['village']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($center['village']) ?><?php endif; ?>
                    <?= orderCell($center['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary">
                      <?= e($center['village']) ?>
                      <?php if (!empty($center['lead_name'])): ?><span class="media-variants"><?= e($center['lead_name']) ?></span><?php endif; ?>
                    </td>
                    <td><?= e($center['district']) ?></td>
                    <td><?= e($center['center_model']) ?></td>
                    <td class="data-table-secondary"><?= e($center['sponsor']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($center['status']) ?>"><?= e(ucfirst($center['status'])) ?></span>
                    </td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $center['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($center['village']) ?></span></a>
                      <a href="https://www.google.com/maps?q=<?= e((string) (float) $center['latitude']) ?>,<?= e((string) (float) $center['longitude']) ?>" target="_blank" rel="noopener" class="link-edit">Map<span class="sr-only"> of <?= e($center['village']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete the center in &quot;<?= e($center['village']) ?>&quot;? It leaves the map and moves to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $center['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($center['village']) ?></span></button>
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
