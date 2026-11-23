<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';

requireCapability('users.manage', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Users & Roles';
$activeNav = 'Users & Roles';
$linkBase = '../../';

$sortable = [
    'name' => 'name',
    'email' => 'email',
    'role' => 'role',
    'status' => 'status',
    'last_login' => 'last_login_at',
];

$params = listParams($sortable, 'name', ['role', 'status']);
$where = listWhere($params, ['name', 'email'], ['role' => 'role', 'status' => 'status']);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM admin_users' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, name, email, role, status, last_login_at FROM admin_users'
    . $where['sql']
    . listOrderLimit($params, 'id')
);
$rowsStmt->execute($where['params']);
$users = $rowsStmt->fetchAll();

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Users &amp; Roles</h1>
          <a href="form.php" class="btn-primary">+ Add User</a>
        </div>

        <?= listToolbar($params, [
            'role' => ['label' => 'Roles', 'options' => ROLE_LABELS],
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'disabled' => 'Disabled']],
        ], 'Search name or email…') ?>

        <?php if (!$users): ?>
          <div class="empty-state">
            <?= $isFiltered ? 'No users match that search.' : 'No admin users found.' ?>
          </div>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <?= listSortHeader('Name', 'name', $params) ?>
                  <?= listSortHeader('Email', 'email', $params) ?>
                  <?= listSortHeader('Role', 'role', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Last Login', 'last_login', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $row): ?>
                  <tr>
                    <td class="data-table-primary"><?= e($row['name']) ?><?= (int) $row['id'] === (int) $user['id'] ? ' <span class="data-table-secondary">(you)</span>' : '' ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e(ROLE_LABELS[$row['role']] ?? ucwords(str_replace('_', ' ', $row['role']))) ?></td>
                    <td>
                      <span class="status-badge status-badge--<?= $row['status'] === 'active' ? 'active' : 'draft' ?>"><?= e(ucfirst($row['status'])) ?></span>
                    </td>
                    <td><?= $row['last_login_at'] ? e(date('M j, Y', strtotime($row['last_login_at']))) : '—' ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $row['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($row['name']) ?></span></a>
                      <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                        <form method="post" action="delete.php" data-confirm="Delete the account &quot;<?= e($row['name']) ?>&quot;? Admin accounts are not kept in Trash — this cannot be undone.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($row['name']) ?></span></button>
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
