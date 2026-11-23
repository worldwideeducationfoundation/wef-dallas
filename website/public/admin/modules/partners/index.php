<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Partner Logos';
$activeNav = 'Partner Logos';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'name' => 'name',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['name', 'link_url'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM partners' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, name, logo_path, link_url, display_order, status, updated_at FROM partners'
    . $where['sql']
    . listOrderLimit($params, 'name')
);
$rowsStmt->execute($where['params']);
$partners = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($partners);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <div>
            <h1>Partner Logos</h1>
            <p class="module-intro">The logos that scroll under "Our Partners" on the home page, in the order below.</p>
          </div>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Partner</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search partner name…') ?>

        <?php if (!$partners): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No partners match that search.'
                : 'No partners yet - the home page is showing its built-in logos. Add the first one to take over.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('partners') ?>
          <div class="listing-view" data-view-key="partners">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all partners on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Logo</span></th>
                  <?= listSortHeader('Name', 'name', $params) ?>
                  <th>Link</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($partners as $partner): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $partner['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $partner['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($partner['name']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($partner['name']) ?><?php endif; ?>
                    <?= orderCell($partner['display_order'], $dupeOrders) ?>
                    <td class="col-thumb">
                      <?php if (!empty($partner['logo_path'])): ?>
                        <img class="table-thumb table-thumb--logo" src="../../uploads/<?= e($partner['logo_path']) ?>" alt="" loading="lazy">
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary"><?= e($partner['name']) ?></td>
                    <td class="data-table-url"><?= e($partner['link_url'] ?? '') ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($partner['status']) ?>"><?= e(ucfirst($partner['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($partner['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $partner['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($partner['name']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($partner['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $partner['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($partner['name']) ?></span></button>
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
              <?php foreach ($partners as $partner): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($partner['name']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $partner['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media grid-card-media--logo">
                    <?php if (!empty($partner['logo_path'])): ?>
                      <img src="../../uploads/<?= e($partner['logo_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(mb_substr($partner['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($partner['name']) ?></div>
                    <div class="grid-card-meta"><?= e($partner['link_url'] ?? '') ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($partner['status']) ?>"><?= e(ucfirst($partner['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $partner['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($partner['name']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($partner['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $partner['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($partner['name']) ?></span></button>
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
