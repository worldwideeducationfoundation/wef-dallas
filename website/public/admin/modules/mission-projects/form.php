<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM mission_projects WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That mission project could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Mission Project' : 'Add Mission Project';
$activeNav = 'Mission Projects';

$values = flashInputGet() ?? $existing ?? [
    'what_header' => '', 'what_sub' => '', 'launch_year' => '',
    'where_text' => '', 'who_text' => '', 'impact_text' => '',
    'notes_text' => '', 'notes_highlight' => '',
    'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Mission Projects</a>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="what_header">Project name (WHAT column heading)</label>
            <input type="text" id="what_header" name="what_header" value="<?= e($values['what_header']) ?>" required maxlength="200">
          </div>

          <div class="field">
            <label for="what_sub">Subtitle (shown under the project name)</label>
            <input type="text" id="what_sub" name="what_sub" value="<?= e($values['what_sub'] ?? '') ?>" maxlength="400">
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="launch_year">Launch year</label>
              <input type="text" id="launch_year" name="launch_year" value="<?= e($values['launch_year'] ?? '') ?>" maxlength="20" placeholder="e.g. 2025">
              <p class="field-hint">Rendered on the page as "(Launched in <?= e($values['launch_year'] ?: '2025') ?>)" — enter just the year.</p>
            </div>
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
          </div>

          <div class="field">
            <label for="where_text">WHERE (one bullet per line)</label>
            <textarea id="where_text" name="where_text" rows="3"><?= e($values['where_text'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label for="who_text">WHO (one bullet per line)</label>
            <textarea id="who_text" name="who_text" rows="3"><?= e($values['who_text'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label for="impact_text">IMPACT (one bullet per line)</label>
            <textarea id="impact_text" name="impact_text" rows="3"><?= e($values['impact_text'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label for="notes_text">NOTES (one bullet per line)</label>
            <textarea id="notes_text" name="notes_text" rows="3"><?= e($values['notes_text'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label for="notes_highlight">NOTES highlight box (optional)</label>
            <textarea id="notes_highlight" name="notes_highlight" rows="2"><?= e($values['notes_highlight'] ?? '') ?></textarea>
            <p class="field-hint">A bordered callout under the NOTES bullets — usually an investment or funding figure. Leave blank if this project doesn't have one.</p>
          </div>

          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" required>
              <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
              <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Mission Project' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
