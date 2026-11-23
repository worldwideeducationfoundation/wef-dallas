<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
// For orderHeader()/orderCell(). This page has no drag handles — groups are
// reordered from their own edit form — but it still shows the order number.
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Honor Board / Sponsors';
$activeNav = 'Honor Board / Sponsors';
$linkBase = '../../';

$sortable = [
    'default' => 'g.display_order',
    'title' => 'g.title',
    'sponsors' => 'sponsor_count',
    'status' => 'g.status',
    'updated' => 'g.updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere($params, ['g.title', 'g.description'], ['status' => 'g.status'],
    [notTrashed('g')]);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM sponsor_groups g' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$groupsStmt = getDb()->prepare(
    'SELECT g.id, g.title, g.display_order, g.status, g.updated_at, COUNT(s.id) AS sponsor_count
     FROM sponsor_groups g
     LEFT JOIN sponsors s ON s.group_id = g.id'
    . $where['sql']
    . ' GROUP BY g.id, g.title, g.display_order, g.status, g.updated_at'
    . listOrderLimit($params, 'g.title')
);
$groupsStmt->execute($where['params']);
$groups = $groupsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$dupeOrders = duplicateOrders($groups);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Honor Board / Sponsors</h1>
          <?php if (can('content.create')): ?>
          <a href="group-form.php" class="btn-primary">+ Add Sponsor Group</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search groups…') ?>

        <?php if (!$groups): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No sponsor groups match that search.'
                : 'No sponsor groups yet. Add the first one to get started.' ?>
          </div>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Group', 'title', $params) ?>
                  <?= listSortHeader('Sponsors', 'sponsors', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($groups as $group): ?>
                  <tr>
                    <?= orderCell($group['display_order'], $dupeOrders) ?>
                    <td class="data-table-primary"><?= e($group['title']) ?></td>
                    <td><a href="sponsors.php?group_id=<?= (int) $group['id'] ?>" class="link-edit"><?= (int) $group['sponsor_count'] ?> sponsor<?= $group['sponsor_count'] == 1 ? '' : 's' ?></a></td>
                    <td>
                      <span class="status-badge status-badge--<?= e($group['status']) ?>"><?= e(ucfirst($group['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($group['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="group-form.php?id=<?= (int) $group['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($group['title']) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="group-delete.php" data-confirm="Delete &quot;<?= e($group['title']) ?>&quot; and its <?= (int) $group['sponsor_count'] ?> sponsor(s)? They will move to Trash together, and restoring the group brings them back.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($group['title']) ?></span></button>
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
