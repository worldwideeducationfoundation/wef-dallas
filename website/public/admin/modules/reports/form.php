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
    $stmt = getDb()->prepare('SELECT * FROM annual_reports WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That report could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Annual Report' : 'Add Annual Report';
$activeNav = 'Annual Reports';

$values = flashInputGet() ?? $existing ?? [
    'title' => '', 'description' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Annual Reports</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="160" placeholder="e.g. Annual Report 2024 – 2025">
          </div>

          <div class="field">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" maxlength="2000"><?= e($values['description'] ?? '') ?></textarea>
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

          <div class="form-row-2">
            <div class="field">
              <label for="cover_image">Cover Image (optional)</label>
              <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('reports') ?>>
              <?= imageSpecHelp('reports') ?>
              <?php if ($isEdit && !empty($existing['cover_image_path'])): ?>
                <div class="current-image" id="current-cover-image">
                  <img src="../../uploads/<?= e($existing['cover_image_path']) ?>" alt="Current cover">
                  <span>Current cover — upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('cover_image', $existing['cover_image_path'], 'current-cover-image', 'Remove current cover image') ?>
              <?php endif; ?>
            </div>
            <div class="field">
              <label for="pdf">Report PDF<?= $isEdit ? ' (optional)' : '' ?></label>
              <input type="file" id="pdf" name="pdf" accept=".pdf"<?= $isEdit ? '' : ' required' ?>>
              <?= documentSpecHelp() ?>
              <?php if ($isEdit && !empty($existing['pdf_path'])): ?>
                <div class="current-image">
                  <span>Current file: <?= e(basename($existing['pdf_path'])) ?> — upload a new file to replace it.</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Report' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
