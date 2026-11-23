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
    $stmt = getDb()->prepare('SELECT * FROM completed_projects WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That completed project could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Completed Project' : 'Add Completed Project';
$activeNav = 'Completed Projects';

// Flashed input (from a failed validation attempt) takes priority over
// the DB row, so the user doesn't lose what they just typed.
$values = flashInputGet() ?? $existing ?? [
    'slug' => '', 'category' => '', 'title' => '', 'summary' => '', 'description' => '',
    'location' => '', 'completion_year' => '', 'video_url' => '',
    'stat1_value' => '', 'stat1_label' => '', 'stat2_value' => '', 'stat2_label' => '',
    'stat3_value' => '', 'stat3_label' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Completed Projects</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="form-row-2">
            <div class="field">
              <label for="title">Project Title</label>
              <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="200">
            </div>
            <div class="field">
              <label for="slug">Slug</label>
              <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="category">Category</label>
              <input type="text" id="category" name="category" value="<?= e($values['category'] ?? '') ?>" maxlength="80" placeholder="e.g. Scholarships, Infrastructure">
              <p class="field-hint">Free text — reuse an existing category (shown as a filter on the list page) unless this project genuinely needs a new one.</p>
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
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?= e($values['location'] ?? '') ?>" maxlength="160" placeholder="e.g. Khorog, Tajikistan">
            </div>
            <div class="field">
              <label for="completion_year">Completion Year</label>
              <input type="text" id="completion_year" name="completion_year" value="<?= e($values['completion_year'] ?? '') ?>" maxlength="20" placeholder="e.g. 2020, or &quot;Active&quot;">
            </div>
          </div>

          <div class="field">
            <label for="summary">Summary (used on the project card)</label>
            <textarea id="summary" name="summary" rows="2" required maxlength="500"><?= e($values['summary']) ?></textarea>
          </div>

          <div class="field">
            <label for="description">Full Description (used on the project page)</label>
            <textarea id="description" name="description" rows="10" required maxlength="20000"><?= e($values['description']) ?></textarea>
            <p class="field-hint">Start a line with <code>### </code> for a subheading. Everything else renders as written.</p>
          </div>

          <fieldset class="stat-fieldset">
            <legend>Stats (shown as the "Measurable Impact" tiles)</legend>
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

          <fieldset class="stat-fieldset">
            <legend>Photos</legend>
            <div class="field">
              <label for="image">Main Photo (required — card image and page banner)</label>
              <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('completed-projects') ?><?= $isEdit ? '' : ' required' ?>>
              <?= imageSpecHelp('completed-projects') ?>
              <?php if ($isEdit && !empty($existing['image_path'])): ?>
                <div class="current-image" id="current-image">
                  <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current main photo">
                  <span>Current photo — upload a new file to replace it.</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label for="secondary_image">Second Photo (optional — falls back to the main photo)</label>
              <input type="file" id="secondary_image" name="secondary_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('completed-projects') ?>>
              <?php if ($isEdit && !empty($existing['secondary_image_path'])): ?>
                <div class="current-image" id="current-secondary-image">
                  <img src="../../uploads/<?= e($existing['secondary_image_path']) ?>" alt="Current second photo">
                  <span>Current photo — upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('secondary_image', $existing['secondary_image_path'], 'current-secondary-image', 'Remove current second photo') ?>
              <?php endif; ?>
            </div>

            <div class="field">
              <label for="tertiary_image">Third Photo (optional)</label>
              <input type="file" id="tertiary_image" name="tertiary_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('completed-projects') ?>>
              <?php if ($isEdit && !empty($existing['tertiary_image_path'])): ?>
                <div class="current-image" id="current-tertiary-image">
                  <img src="../../uploads/<?= e($existing['tertiary_image_path']) ?>" alt="Current third photo">
                  <span>Current photo — upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('tertiary_image', $existing['tertiary_image_path'], 'current-tertiary-image', 'Remove current third photo') ?>
              <?php endif; ?>
            </div>

            <div class="field">
              <label for="map_image">Map Image (optional — a static map screenshot, not an interactive map)</label>
              <input type="file" id="map_image" name="map_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('completed-projects-map') ?>>
              <?= imageSpecHelp('completed-projects-map') ?>
              <?php if ($isEdit && !empty($existing['map_image_path'])): ?>
                <div class="current-image" id="current-map-image">
                  <img src="../../uploads/<?= e($existing['map_image_path']) ?>" alt="Current map image">
                  <span>Current image — upload a new file to replace it.</span>
                </div>
                <?= removeFileCheckbox('map_image', $existing['map_image_path'], 'current-map-image', 'Remove current map image') ?>
              <?php endif; ?>
            </div>

            <div class="field">
              <label for="video_url">Video URL (optional — a YouTube link)</label>
              <input type="url" id="video_url" name="video_url" value="<?= e($values['video_url'] ?? '') ?>" maxlength="255" placeholder="https://www.youtube.com/embed/...">
              <p class="field-hint">Paste a youtube.com or youtube-nocookie.com link. The embed URL from YouTube's own Share &rarr; Embed option works best.</p>
            </div>
          </fieldset>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Completed Project' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
