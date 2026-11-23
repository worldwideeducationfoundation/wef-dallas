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
    $stmt = getDb()->prepare('SELECT * FROM gallery_items WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That photo could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Photo' : 'Add Photo';
$activeNav = 'Gallery';

$values = flashInputGet() ?? $existing ?? [
    'caption' => '', 'program_id' => null, 'display_order' => 0, 'status' => 'draft',
];

// The Gallery page labels and filters photos by project.
$projects = getDb()->query(
    'SELECT id, name FROM programs WHERE deleted_at IS NULL ORDER BY display_order, name'
)->fetchAll();

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Gallery</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="caption">Caption</label>
            <input type="text" id="caption" name="caption" value="<?= e($values['caption']) ?>" required maxlength="160" placeholder="e.g. Graduation Day">
          </div>

          <div class="field">
            <label for="program_id">Project</label>
            <select id="program_id" name="program_id">
              <option value="">General (no specific project)</option>
              <?php foreach ($projects as $project): ?>
                <option value="<?= (int) $project['id'] ?>"<?= (int) ($values['program_id'] ?? 0) === (int) $project['id'] ? ' selected' : '' ?>><?= e($project['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field-hint">The Gallery page shows the project's name on the photo and lets visitors filter by project.</p>
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
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= $isEdit ? '' : ' required' ?><?= imageSpecAttributes('gallery') ?>>
            <?= imageSpecHelp('gallery') ?>
            <?php if ($isEdit && !empty($existing['image_path'])): ?>
              <div class="current-image" id="current-image">
                <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                <span>Current photo — upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Add Photo' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
