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
    $stmt = getDb()->prepare('SELECT * FROM partners WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That partner could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Partner' : 'Add Partner';
$activeNav = 'Partner Logos';

$values = flashInputGet() ?? $existing ?? [
    'name' => '', 'link_url' => '', 'display_order' => 0, 'status' => 'draft',
];

$maxMb = (int) (UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024));

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Partner Logos</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="name">Partner name</label>
            <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="200" placeholder="University of Central Asia (UCA)">
            <p class="field-hint">Also read out to screen-reader users in place of the logo.</p>
          </div>

          <div class="field">
            <label for="logo">Logo</label>
            <!-- No fixed crop: logos come in every shape (wide wordmarks,
                 tall crests) and a forced ratio would cut them off. -->
            <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
            <p class="field-help">PNG with a transparent background works best · JPG or WebP also accepted · max <?= $maxMb ?>MB<br>Any shape - logos are scaled to the same height in the strip.</p>
            <?php if ($isEdit && !empty($existing['logo_path'])): ?>
              <div class="current-image current-image--logo" id="current-logo">
                <img src="../../uploads/<?= e($existing['logo_path']) ?>" alt="Current logo">
                <span>Current logo - upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('logo', $existing['logo_path'], 'current-logo', 'Remove current logo') ?>
            <?php endif; ?>
          </div>

          <div class="field">
            <label for="link_url">Website link (optional)</label>
            <input type="url" id="link_url" name="link_url" value="<?= e($values['link_url'] ?? '') ?>" maxlength="255" placeholder="https://…">
            <p class="field-hint">If set, the logo opens this site in a new tab.</p>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft - hidden from the website</option>
                <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active - shown on the website</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Partner' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
