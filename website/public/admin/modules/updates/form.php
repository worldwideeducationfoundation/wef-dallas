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
    $stmt = getDb()->prepare('SELECT * FROM project_updates WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That update could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Update' : 'Add Project Update';
$activeNav = 'Project Updates';

$values = flashInputGet() ?? $existing ?? [
    'title' => '', 'slug' => '', 'content' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Project Updates</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="form-row-2">
            <div class="field">
              <label for="title">Title</label>
              <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="160" placeholder="e.g. New School Opening in Chitral">
            </div>
            <div class="field">
              <label for="slug">URL Slug (leave blank to auto-generate)</label>
              <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" maxlength="160" placeholder="e.g. new-school-opening">
            </div>
          </div>

          <div class="field">
            <label for="content">Content</label>
            <textarea id="content" name="content" rows="6" required><?= e($values['content'] ?? '') ?></textarea>
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
            <label for="image">Cover Image (optional)</label>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('programs') ?>>
            <?= imageSpecHelp('programs') ?>
            <?php if ($isEdit && !empty($existing['image_path'])): ?>
              <div class="current-image" id="current-image">
                <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current image">
                <span>Current image — upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current image') ?>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Update' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
