<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM donate_options WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That option could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Donate Option' : 'Add Donate Option';
$activeNav = 'Donate Options';

$values = flashInputGet() ?? $existing ?? [
    'label' => '', 'slug' => '', 'program_id' => null, 'summary' => '', 'display_order' => 0, 'status' => 'active',
];

// Projects that can be linked (not deleted), with whether they are live.
$programs = getDb()->query(
    'SELECT id, name, slug, status FROM programs WHERE deleted_at IS NULL ORDER BY display_order, name'
)->fetchAll();

$maxMb = (int) (UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024));

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Donate Options</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="program_id">Linked project (optional)</label>
            <select id="program_id" name="program_id">
              <option value="">None - a separate option, such as "Where it's needed most"</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= (string) ($values['program_id'] ?? '') === (string) $p['id'] ? ' selected' : '' ?>>
                  <?= e($p['name']) ?><?= $p['status'] !== 'active' ? ' (unpublished)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="field-hint">A linked option uses the project's name, photo, description and numbers, unless you fill those in below. It is hidden while the project is unpublished.</p>
          </div>

          <div class="field">
            <label for="label">Label in the dropdown</label>
            <input type="text" id="label" name="label" value="<?= e((string) ($values['label'] ?? '')) ?>" maxlength="200" placeholder="Where it's needed most">
            <p class="field-hint">Required for an option without a linked project. For a linked one, leave blank to use the project's name.</p>
          </div>

          <div class="field">
            <label for="summary">Description (optional)</label>
            <textarea id="summary" name="summary" rows="3" maxlength="1000"><?= e((string) ($values['summary'] ?? '')) ?></textarea>
            <p class="field-hint">Shown under the dropdown and on the photo card. Leave blank to use the linked project's summary.</p>
          </div>

          <div class="field">
            <label for="image">Photo (optional)</label>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
            <p class="field-help">JPG, PNG or WebP · max <?= $maxMb ?>MB · a landscape photo works best. Leave empty to use the linked project's photo (or the general photo).</p>
            <?php if ($isEdit && !empty($existing['image_path'])): ?>
              <div class="current-image" id="current-image">
                <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                <span>Current photo - upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="slug">Name in the address (optional)</label>
            <input type="text" id="slug" name="slug" value="<?= e((string) ($values['slug'] ?? '')) ?>" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="made from the label">
            <p class="field-hint">Lowercase letters, numbers and dashes, as in <code>donate?program=<strong>bright-beginnings</strong></code>. Leave blank to make it from the project or the label. The project pages' Donate buttons use the project's own name here, so keep it for linked options. <code>general</code> is the option selected when the page opens.</p>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active"<?= ($values['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active - shown in the dropdown</option>
                <option value="draft"<?= ($values['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Draft - hidden from the dropdown</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Option' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
