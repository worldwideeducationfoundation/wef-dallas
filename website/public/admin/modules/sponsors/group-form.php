<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM sponsor_groups WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That sponsor group could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Sponsor Group' : 'Add Sponsor Group';
$activeNav = 'Honor Board / Sponsors';

$values = flashInputGet() ?? $existing ?? [
    'title' => '', 'description' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Honor Board</a>
        </div>

        <form method="post" action="group-save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="title">Group Title</label>
            <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="255" placeholder="e.g. Sponsors of Bright Beginnings: Early Childhood Learning Centers">
          </div>

          <div class="field">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3"><?= e($values['description'] ?? '') ?></textarea>
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
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Group' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
