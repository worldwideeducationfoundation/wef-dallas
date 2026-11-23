<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Video Gallery';
$activeNav = 'Video Gallery';
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
    ['title', 'youtube_url'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM video_gallery' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, title, youtube_url, display_order, status, updated_at FROM video_gallery'
    . $where['sql']
    . listOrderLimit($params, 'title')
);
$rowsStmt->execute($where['params']);
$videos = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($videos);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Video Gallery</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Video</a>
          <?php endif; ?>
        </div>

        <div class="welcome-card">
          <p>Shown on the Video Gallery page and, capped to the first 2, on the homepage's "Featured Videos" section — both read this same list.</p>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search title or URL…') ?>

        <?php if (!$videos): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No videos match that search.'
                : 'No videos yet. Add the first one to get started.' ?>
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
                    <input type="checkbox" data-bulk-all aria-label="Select all videos on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Title', 'title', $params) ?>
                  <th>YouTube URL</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($videos as $video): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $video['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $video['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($video['title']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($video['title']) ?><?php endif; ?>
                    <?= orderCell($video['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary"><?= e($video['title']) ?></td>
                    <td class="data-table-secondary"><?= e($video['youtube_url']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($video['status']) ?>"><?= e(ucfirst($video['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($video['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $video['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($video['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($video['title']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($video['title']) ?></span></button>
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
