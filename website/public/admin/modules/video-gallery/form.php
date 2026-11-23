<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM video_gallery WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That video could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Video' : 'Add Video';
$activeNav = 'Video Gallery';

$values = flashInputGet() ?? $existing ?? [
    'title' => '', 'youtube_url' => '', 'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Video Gallery</a>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required maxlength="200">
            <p class="field-hint">Use a project's exact name (as in Our Active Projects, e.g. "Secondary School Scholarships") and the video also appears on that project's page, beside its Impact section.</p>
          </div>

          <div class="field">
            <label for="youtube_url">YouTube Embed URL</label>
            <input type="url" id="youtube_url" name="youtube_url" value="<?= e($values['youtube_url'] ?? '') ?>" required maxlength="255" placeholder="https://www.youtube.com/embed/…">
            <p class="field-hint">Must be a youtube.com or youtube-nocookie.com link. Use YouTube's own Share &rarr; Embed option and paste the URL from the <code>src</code> attribute it gives you (looks like <code>https://www.youtube.com/embed/VIDEO_ID</code>).</p>
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

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Video' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
