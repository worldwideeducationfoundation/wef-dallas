<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM sponsors WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That sponsor could not be found.');
        redirect('index.php');
    }
}

$defaultGroupId = $isEdit ? (int) $existing['group_id'] : (int) ($_GET['group_id'] ?? 0);

if (!$isEdit && !$defaultGroupId) {
    flashSet('error', 'No sponsor group specified.');
    redirect('index.php');
}

$pageTitle = $isEdit ? 'Edit Sponsor' : 'Add Sponsor';
$activeNav = 'Honor Board / Sponsors';

$values = flashInputGet() ?? $existing ?? [
    'name' => '', 'badge_text' => '', 'group_id' => $defaultGroupId, 'display_order' => 0, 'status' => 'draft',
];

$groups = getDb()->query('SELECT id, title FROM sponsor_groups ORDER BY display_order, title')->fetchAll();
$backLink = 'sponsors.php?group_id=' . (int) $defaultGroupId;

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="<?= e($backLink) ?>" class="link-back">&larr; Back to Sponsors</a>
        </div>

        <form method="post" action="sponsor-save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="group_id">Sponsor Group</label>
            <select id="group_id" name="group_id" required>
              <?php foreach ($groups as $group): ?>
                <option value="<?= (int) $group['id'] ?>"<?= (int) $values['group_id'] === (int) $group['id'] ? ' selected' : '' ?>><?= e($group['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="name">Sponsor Name</label>
              <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="255">
            </div>
            <div class="field">
              <label for="badge_text">Badge (optional)</label>
              <input type="text" id="badge_text" name="badge_text" value="<?= e($values['badge_text'] ?? '') ?>" maxlength="60" placeholder="e.g. 04 Centers">
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Add Sponsor' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
