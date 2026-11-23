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
    $stmt = getDb()->prepare('SELECT * FROM programs WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That program could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Program' : 'Add Program';
$activeNav = 'Programs';

// Flashed input (from a failed validation attempt) takes priority over
// the DB row, so the user doesn't lose what they just typed.
$values = flashInputGet() ?? $existing ?? [
    'slug' => '', 'category' => 'programs', 'name' => '', 'summary' => '', 'description' => '',
    'badge_text' => '', 'location_text' => '', 'year_launched' => '',
    'stat1_value' => '', 'stat1_label' => '', 'stat2_value' => '', 'stat2_label' => '',
    'stat3_value' => '', 'stat3_label' => '', 'display_order' => 0, 'status' => 'draft',
];

// The project page's photo set (program_photos): each shown with its
// caption, order, a Replace file field and a Remove box; new ones are added
// through the multi-file field below the list. Guarded so the form still
// opens on a database that migrate.php has not given the table yet.
$projectPhotos = [];
if ($isEdit) {
    try {
        $photoStmt = getDb()->prepare('SELECT id, image_path, caption, display_order FROM program_photos WHERE program_id = :id ORDER BY display_order, id');
        $photoStmt->execute(['id' => $id]);
        $projectPhotos = $photoStmt->fetchAll();
    } catch (Throwable $e) {
        $projectPhotos = [];
    }
}

$categories = [
    'early_childhood' => 'Early Childhood',
    'programs' => 'Programs',
    'scholarships' => 'Scholarships',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Programs</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="form-row-2">
            <div class="field">
              <label for="name">Program Name</label>
              <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="160">
            </div>
            <div class="field">
              <label for="slug">Slug</label>
              <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="category">Category</label>
              <select id="category" name="category" required>
                <?php foreach ($categories as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $values['category'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
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
            <label for="summary">Summary (used on project cards)</label>
            <textarea id="summary" name="summary" rows="2" required maxlength="500"><?= e($values['summary']) ?></textarea>
          </div>

          <div class="field">
            <label for="description">Full Description (used on the program page)</label>
            <textarea id="description" name="description" rows="8" required maxlength="8000"><?= e($values['description']) ?></textarea>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="badge_text">Badge Text</label>
              <input type="text" id="badge_text" name="badge_text" value="<?= e($values['badge_text'] ?? '') ?>" maxlength="40" placeholder="e.g. Since 2025">
            </div>
            <div class="field">
              <label for="location_text">Location</label>
              <input type="text" id="location_text" name="location_text" value="<?= e($values['location_text'] ?? '') ?>" maxlength="120" placeholder="e.g. Upper Chitral">
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="year_launched">Year Launched</label>
              <input type="number" id="year_launched" name="year_launched" value="<?= e((string) ($values['year_launched'] ?? '')) ?>" min="1900" max="2100" placeholder="e.g. 2015">
            </div>
          </div>

          <fieldset class="stat-fieldset">
            <legend>Stats (shown as the three tiles on the project card)</legend>
            <div class="form-row-3">
              <div class="field">
                <label for="stat1_value">Stat 1 Value</label>
                <input type="text" id="stat1_value" name="stat1_value" value="<?= e($values['stat1_value'] ?? '') ?>" maxlength="40">
              </div>
              <div class="field">
                <label for="stat1_label">Stat 1 Label</label>
                <input type="text" id="stat1_label" name="stat1_label" value="<?= e($values['stat1_label'] ?? '') ?>" maxlength="60">
              </div>
            </div>
            <div class="form-row-3">
              <div class="field">
                <label for="stat2_value">Stat 2 Value</label>
                <input type="text" id="stat2_value" name="stat2_value" value="<?= e($values['stat2_value'] ?? '') ?>" maxlength="40">
              </div>
              <div class="field">
                <label for="stat2_label">Stat 2 Label</label>
                <input type="text" id="stat2_label" name="stat2_label" value="<?= e($values['stat2_label'] ?? '') ?>" maxlength="60">
              </div>
            </div>
            <div class="form-row-3">
              <div class="field">
                <label for="stat3_value">Stat 3 Value</label>
                <input type="text" id="stat3_value" name="stat3_value" value="<?= e($values['stat3_value'] ?? '') ?>" maxlength="40">
              </div>
              <div class="field">
                <label for="stat3_label">Stat 3 Label</label>
                <input type="text" id="stat3_label" name="stat3_label" value="<?= e($values['stat3_label'] ?? '') ?>" maxlength="60">
              </div>
            </div>
          </fieldset>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
            <div class="field">
              <label for="image">Photo</label>
              <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('programs') ?>>
              <?= imageSpecHelp('programs') ?>
              <?php if ($isEdit && !empty($existing['image_path'])): ?>
                <div class="current-image" id="current-image">
                  <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                  <span>Current photo — upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
              <?php endif; ?>
            </div>
          </div>

          <fieldset class="form-section">
            <legend>Before &amp; after (optional)</legend>
            <p class="field-hint">Two photos of the same place, taken before and after WEF's work. When both are set, the project page shows a slider visitors drag to compare them. Leave empty if the project has nothing to compare.</p>
            <div class="form-row-2">
              <?php foreach (['before' => 'Before photo', 'after' => 'After photo'] as $side => $sideLabel): ?>
                <?php $sidePath = $existing[$side . '_image_path'] ?? null; ?>
                <div class="field">
                  <label for="<?= $side ?>_image"><?= e($sideLabel) ?></label>
                  <input type="file" id="<?= $side ?>_image" name="<?= $side ?>_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('programs') ?>>
                  <?php if ($isEdit && !empty($sidePath)): ?>
                    <div class="current-image" id="current-<?= $side ?>-image">
                      <img src="../../uploads/<?= e($sidePath) ?>" alt="Current <?= e(strtolower($sideLabel)) ?>">
                      <span>Upload a new file to replace it.</span>
                    </div>
                    <?= removeFileCheckbox($side . '_image', $sidePath, 'current-' . $side . '-image', 'Remove ' . strtolower($sideLabel)) ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend>Project photos</legend>
            <p class="field-hint">These illustrate the project page: one photo beside each section of text, in the order below, and all of them in the photo carousel at the foot of the page. A section without a photo shows a colour panel instead. Gallery photos filed under this project are used after these.</p>

            <?php if ($projectPhotos): ?>
              <div class="project-photo-list">
                <?php foreach ($projectPhotos as $photo): ?>
                  <?php $pid = (int) $photo['id']; ?>
                  <div class="project-photo" id="project-photo-<?= $pid ?>">
                    <img src="../../uploads/<?= e($photo['image_path']) ?>" alt="" class="project-photo-thumb" loading="lazy">
                    <div class="project-photo-fields">
                      <div class="field">
                        <label for="photo-caption-<?= $pid ?>">Caption</label>
                        <input type="text" id="photo-caption-<?= $pid ?>" name="photos[<?= $pid ?>][caption]" value="<?= e((string) $photo['caption']) ?>" maxlength="160" placeholder="e.g. Morning circle time in Booni">
                      </div>
                      <div class="form-row-2">
                        <div class="field">
                          <label for="photo-order-<?= $pid ?>">Order</label>
                          <input type="number" id="photo-order-<?= $pid ?>" name="photos[<?= $pid ?>][order]" value="<?= (int) $photo['display_order'] ?>" step="1">
                        </div>
                        <div class="field">
                          <label for="photo-replace-<?= $pid ?>">Replace with</label>
                          <input type="file" id="photo-replace-<?= $pid ?>" name="photo_replace_<?= $pid ?>" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                      </div>
                      <label class="remove-file">
                        <input type="checkbox" name="photos[<?= $pid ?>][remove]" value="1" data-remove-toggle="project-photo-<?= $pid ?>">
                        <span>Remove this photo</span>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="field">
              <label for="new_photos"><?= $projectPhotos ? 'Add more photos' : 'Add photos' ?></label>
              <input type="file" id="new_photos" name="new_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
              <p class="field-hint">Choose several at once (up to 12 per save, 5MB each). Captions can be added after saving.</p>
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Program' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
