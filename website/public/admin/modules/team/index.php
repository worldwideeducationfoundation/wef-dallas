<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';
require_once __DIR__ . '/../../includes/media-specs.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Team';
$activeNav = 'Team';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'name' => 'name',
    'role' => 'role',
    'section' => 'section',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status', 'section']);
$where = listWhere(
    $params,
    ['name', 'role', 'bio'],
    ['status' => 'status', 'section' => 'section'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM team_members' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, name, role, section, image_path, display_order, status, updated_at FROM team_members'
    . $where['sql']
    . listOrderLimit($params, 'name')
);
$rowsStmt->execute($where['params']);
$members = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($members);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Team</h1>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Team Member</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'section' => ['label' => 'Sections', 'options' => array_combine(TEAM_SECTIONS, TEAM_SECTIONS)],
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search name, role, or bio…') ?>

        <?php if (!$members): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No team members match that search.'
                : 'No team members yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= viewToggle('team') ?>
          <div class="listing-view" data-view-key="team">
          <div class="view-panel view-panel--list">
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all team members on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <th><span class="sr-only">Photo</span></th>
                  <?= listSortHeader('Name', 'name', $params) ?>
                  <?= listSortHeader('Role', 'role', $params) ?>
                  <?= listSortHeader('Section', 'section', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($members as $member): ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $member['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $member['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($member['name']) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($member['name']) ?><?php endif; ?>
                    <?= orderCell($member['display_order'], $dupeOrders) ?>
                    <td>
                      <?php if ($member['image_path']): ?>
                        <img src="../../uploads/<?= e($member['image_path']) ?>" alt="" class="data-table-avatar" loading="lazy">
                      <?php else: ?>
                        <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="data-table-primary"><?= e($member['name']) ?></td>
                    <td><?= e($member['role']) ?></td>
                    <td class="data-table-secondary"><?= e($member['section']) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($member['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $member['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($member['name']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($member['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($member['name']) ?></span></button>
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
              <?php foreach ($members as $member): ?>
                <div class="grid-card">
                  <label class="grid-card-select">
                    <span class="sr-only">Select <?= e($member['name']) ?></span>
                    <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $member['id'] ?>" data-bulk-item>
                  </label>
                  <div class="grid-card-media">
                    <?php if (!empty($member['image_path'])): ?>
                      <img src="../../uploads/<?= e($member['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <div class="grid-card-media--empty" aria-hidden="true"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="grid-card-body">
                    <div class="grid-card-title"><?= e($member['name']) ?></div>
                    <div class="grid-card-meta"><?= e($member['role']) ?> &middot; <?= e($member['section']) ?></div>
                    <div class="grid-card-footer">
                      <span class="status-badge status-badge--<?= e($member['status']) ?>"><?= e(ucfirst($member['status'])) ?></span>
                      <div class="data-table-actions">
                        <a href="form.php?id=<?= (int) $member['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($member['name']) ?></span></a>
                        <?php if (can('content.delete')): ?>
                        <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($member['name']) ?>&quot;? It will move to Trash, where you can restore it.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($member['name']) ?></span></button>
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
