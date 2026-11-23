<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/media-specs.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM team_members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That team member could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Team Member' : 'Add Team Member';
$activeNav = 'Team';

$values = flashInputGet() ?? $existing ?? [
    'name' => '', 'role' => '', 'bio' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Team</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="form-row-2">
            <div class="field">
              <label for="name">Name</label>
              <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="160">
            </div>
            <div class="field">
              <label for="role">Role / Title</label>
              <input type="text" id="role" name="role" value="<?= e($values['role']) ?>" required maxlength="160">
            </div>
          </div>

          <div class="field">
            <label for="section">Section</label>
            <select id="section" name="section" required>
              <?php foreach (TEAM_SECTIONS as $section): ?>
                <option value="<?= e($section) ?>"<?= ($values['section'] ?? TEAM_SECTIONS[0]) === $section ? ' selected' : '' ?>><?= e($section) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field-help">Which heading this person appears under on the public Our Team page.</p>
          </div>

          <div class="field">
            <label for="bio">Bio (optional)</label>
            <textarea id="bio" name="bio" rows="8" maxlength="4000"><?= e($values['bio'] ?? '') ?></textarea>
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

          <div class="field">
            <label for="image">Photo</label>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('team') ?>>
              <?= imageSpecHelp('team') ?>
            <?php if ($isEdit && !empty($existing['image_path'])): ?>
              <div class="current-image" id="current-image">
                <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                <span>Current photo — upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Add Team Member' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
