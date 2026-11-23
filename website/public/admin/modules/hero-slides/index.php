<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Home Slideshow';
$activeNav = 'Home Slideshow';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'title' => 'title_highlight',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['title_lead', 'title_highlight', 'description'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM hero_slides' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, title_lead, title_highlight, description, image_path, display_order, status, updated_at FROM hero_slides'
    . $where['sql']
    . listOrderLimit($params, 'title_highlight')
);
$rowsStmt->execute($where['params']);
$slides = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($slides);

$slideTitle = static fn(array $s): string => trim($s['title_lead'] . ' ' . $s['title_highlight']);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <div>
            <h1>Home Slideshow</h1>
            <p class="module-intro">The photos and headlines that rotate on the first screen of the home page. Active slides show in the order below; the first one loads first, so put the strongest photo at the top.</p>
          </div>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Slide</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search headline or description…') ?>

        <?php if (!$slides): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No slides match that search.'
                : 'No slides yet - the home page is showing its built-in slides. Add the first one to take over.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('hero-slides') ?>
          <div class="listing-view" data-view-key="hero-slides">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all slides on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Photo</span></th>
                  <?= listSortHeader('Headline', 'title', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($slides as $slide): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $slide['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $slide['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($slideTitle($slide)) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($slideTitle($slide)) ?><?php endif; ?>
                    <?= orderCell($slide['display_order'], $dupeOrders) ?>
                    <td class="col-thumb">
                      <?php if (!empty($slide['image_path'])): ?>
                        <img class="table-thumb table-thumb--wide" src="../../uploads/<?= e($slide['image_path']) ?>" alt="" loading="lazy">
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary">
                      <?= e($slide['title_lead']) ?> <em class="text-accent"><?= e($slide['title_highlight']) ?></em>
                      <?php if (!empty($slide['description'])): ?>
                        <div class="data-table-sub"><?= e(mb_strimwidth($slide['description'], 0, 110, '…')) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="status-badge status-badge--<?= e($slide['status']) ?>"><?= e(ucfirst($slide['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($slide['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $slide['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($slideTitle($slide)) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete the &quot;<?= e($slideTitle($slide)) ?>&quot; slide? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($slideTitle($slide)) ?></span></button>
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
              <?php foreach ($slides as $slide): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($slideTitle($slide)) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $slide['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($slide['image_path'])): ?>
                      <img src="../../uploads/<?= e($slide['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(mb_substr($slide['title_highlight'], 0, 1)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($slideTitle($slide)) ?></div>
                    <div class="grid-card-meta"><?= e(mb_strimwidth((string) $slide['description'], 0, 90, '…')) ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($slide['status']) ?>"><?= e(ucfirst($slide['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $slide['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($slideTitle($slide)) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete the &quot;<?= e($slideTitle($slide)) ?>&quot; slide? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($slideTitle($slide)) ?></span></button>
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
