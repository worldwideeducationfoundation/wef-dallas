<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM impact_milestones WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That milestone could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Milestone' : 'Add Milestone';
$activeNav = 'Impact Goals Roadmap';

$values = flashInputGet() ?? $existing ?? [
    'milestone_date' => '', 'centers_target' => 0, 'students_target' => 0, 'teachers_target' => 0,
    'display_order' => 0, 'status' => 'draft',
];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Impact Goals Roadmap</a>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="milestone_date">Target date (shown as typed, e.g. "May 2027")</label>
            <input type="text" id="milestone_date" name="milestone_date" value="<?= e($values['milestone_date']) ?>" required maxlength="40">
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="centers_target">Learning centers (cumulative target)</label>
              <input type="number" id="centers_target" name="centers_target" value="<?= e((string) ($values['centers_target'] ?? 0)) ?>" min="0" step="1" required>
            </div>
            <div class="field">
              <label for="students_target">Students impacted (cumulative target)</label>
              <input type="number" id="students_target" name="students_target" value="<?= e((string) ($values['students_target'] ?? 0)) ?>" min="0" step="1" required>
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="teachers_target">Teachers/entrepreneurs trained (cumulative target)</label>
              <input type="number" id="teachers_target" name="teachers_target" value="<?= e((string) ($values['teachers_target'] ?? 0)) ?>" min="0" step="1" required>
            </div>
            <div class="field">
              <label for="display_order">Display Order</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
              <p class="field-hint">Only the first 5 active milestones, in this order, appear on the chart.</p>
            </div>
          </div>

          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status" required>
              <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
              <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Create Milestone' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
