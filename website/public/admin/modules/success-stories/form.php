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
    $stmt = getDb()->prepare('SELECT * FROM success_stories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That success story could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Success Story' : 'Add Success Story';
$activeNav = 'Success Stories';

$values = flashInputGet() ?? $existing ?? [
    'title' => '', 'tag' => 'SUCCESS STORIES', 'story_date' => '', 'link_url' => '',
    'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Success Stories</a>
        </div>

        <form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="200">
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="tag">Tag (shown on the card, e.g. "SUCCESS STORIES")</label>
              <input type="text" id="tag" name="tag" value="<?= e($values['tag'] ?? '') ?>" maxlength="40">
            </div>
            <div class="field">
              <label for="story_date">Date (shown as typed, e.g. "May 6, 2026")</label>
              <input type="text" id="story_date" name="story_date" value="<?= e($values['story_date'] ?? '') ?>" maxlength="40">
            </div>
          </div>

          <div class="field">
            <label for="link_url">Read More link (optional)</label>
            <input type="url" id="link_url" name="link_url" value="<?= e($values['link_url'] ?? '') ?>" maxlength="255" placeholder="https://…">
            <p class="field-hint">No detail page exists for stories on this site yet, so this can only point somewhere external (a blog post, a PDF, a news article). Leave blank and the card renders without a "Read More" link.</p>
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
            <label for="image">Photo (optional — a placeholder box shows if left empty)</label>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes('completed-projects') ?>>
            <?= imageSpecHelp('completed-projects') ?>
            <?php if ($isEdit && !empty($existing['image_path'])): ?>
              <div class="current-image" id="current-image">
                <img src="../../uploads/<?= e($existing['image_path']) ?>" alt="Current photo">
                <span>Current photo — upload a new file to replace it.</span>
              </div>
              <?= removeFileCheckbox('image', $existing['image_path'], 'current-image', 'Remove current photo') ?>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Success Story' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
