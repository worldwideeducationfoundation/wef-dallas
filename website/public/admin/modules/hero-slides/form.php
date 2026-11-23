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
    $stmt = getDb()->prepare('SELECT * FROM hero_slides WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That slide could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Slide' : 'Add Slide';
$activeNav = 'Home Slideshow';

$values = flashInputGet() ?? $existing ?? [
    'title_lead' => '', 'title_highlight' => '', 'description' => '', 'image_alt' => '',
    'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Home Slideshow</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <fieldset class="form-section">
            <legend>Headline</legend>
            <p class="field-hint">The headline is typed out in two parts: the first in white, the second in green italics - for example <strong>Early Childhood</strong> <em class="text-accent">Learning Centers (ECLC)</em>.</p>
            <div class="form-row-2">
              <div class="field">
                <label for="title_lead">First part (white)</label>
                <input type="text" id="title_lead" name="title_lead" value="<?= e($values['title_lead']) ?>" required maxlength="120" placeholder="Early Childhood">
              </div>
              <div class="field">
                <label for="title_highlight">Second part (green italics)</label>
                <input type="text" id="title_highlight" name="title_highlight" value="<?= e($values['title_highlight']) ?>" required maxlength="120" placeholder="Learning Centers (ECLC)">
              </div>
            </div>

            <div class="field">
              <label for="description">Description</label>
              <textarea id="description" name="description" rows="3" maxlength="400"><?= e($values['description'] ?? '') ?></textarea>
              <p class="field-hint">One or two sentences under the headline. Around 20-25 words fits every screen size.</p>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend>Photo</legend>
            <div class="field">
              <label for="image">Background photo</label>
              <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('hero-slides') ?>>
              <?= imageSpecHelp('hero-slides') ?>
              <?php if ($isEdit && !empty($existing['image_path'])): ?>
                <div class="current-image current-image--wide" id="current-image">
                  <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                  <span>Current photo - upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
              <?php endif; ?>
            </div>

            <div class="field">
              <label for="image_alt">Photo description (for screen readers)</label>
              <input type="text" id="image_alt" name="image_alt" value="<?= e($values['image_alt'] ?? '') ?>" maxlength="200" placeholder="Children at work in an early childhood classroom">
              <p class="field-hint">What the photo shows, in a few words. Leave blank to use the headline.</p>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend>Publishing</legend>
            <div class="form-row-2">
              <div class="field">
                <label for="display_order">Display Order</label>
                <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
                <p class="field-hint">Lower numbers show first. You can also drag slides into order on the list.</p>
              </div>
              <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                  <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft - hidden from the website</option>
                  <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active - shown on the website</option>
                </select>
              </div>
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Slide' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
