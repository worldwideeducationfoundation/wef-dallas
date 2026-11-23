<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('users.manage', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That user could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit User' : 'Add User';
$activeNav = 'Users & Roles';

$values = flashInputGet() ?? $existing ?? [
    'name' => '', 'email' => '', 'role' => 'editor', 'status' => 'active',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Users</a>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="form-row-2">
            <div class="field">
              <label for="name">Name</label>
              <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="120">
            </div>
            <div class="field">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required maxlength="190">
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="role">Role</label>
              <select id="role" name="role" required>
                <option value="editor"<?= $values['role'] === 'editor' ? ' selected' : '' ?>>Editor</option>
                <option value="administrator"<?= $values['role'] === 'administrator' ? ' selected' : '' ?>>Administrator</option>
                <option value="super_admin"<?= $values['role'] === 'super_admin' ? ' selected' : '' ?>>Super Admin</option>
              </select>
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="disabled"<?= $values['status'] === 'disabled' ? ' selected' : '' ?>>Disabled</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="password">Password<?= $isEdit ? ' (leave blank to keep current password)' : '' ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
