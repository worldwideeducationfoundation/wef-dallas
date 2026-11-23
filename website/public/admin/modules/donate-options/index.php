<?php
/**
 * Donate Options — the Program dropdown on the Donate page, in this order.
 *
 * An option can be linked to a project: it then follows that project's
 * name, photo, description and numbers (each can be overridden here), and
 * is hidden while the project is unpublished. An unlinked option ("Where
 * it's needed most", or anything else) has its own label, description and
 * photo. donate.php reads this table; see donateOptions() there.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/bulk.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Donate Options';
$activeNav = 'Donate Options';
$linkBase = '../../';

$sortable = [
    'default' => 'display_order',
    'label' => 'label',
    'status' => 'status',
    'updated' => 'updated_at',
];

$params = listParams($sortable, 'default', ['status']);
$where = listWhere(
    $params,
    ['label', 'slug', 'summary'],
    ['status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM donate_options' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, label, slug, program_id, summary, image_path, display_order, status, updated_at FROM donate_options'
    . $where['sql']
    . listOrderLimit($params, 'slug')
);
$rowsStmt->execute($where['params']);
$options = $rowsStmt->fetchAll();

// The linked projects' names and whether they are live.
$programs = getDb()->query('SELECT id, name, image_path, status, deleted_at FROM programs')->fetchAll(PDO::FETCH_UNIQUE);

$isFiltered = $params['q'] !== '' || $params['filters'] !== [];
$canReorder = reorderEnabled($params);
$dupeOrders = duplicateOrders($options);

/** What the dropdown shows for this option. */
$optionName = function (array $o) use ($programs): string {
    $label = trim((string) ($o['label'] ?? ''));
    if ($label !== '') {
        return $label;
    }
    $p = $o['program_id'] !== null ? ($programs[$o['program_id']] ?? null) : null;
    return $p !== null ? (string) $p['name'] : '(no label)';
};

/** A note when a linked project keeps the option off the page. */
$projectNote = function (array $o) use ($programs): string {
    if ($o['program_id'] === null) {
        return '';
    }
    $p = $programs[$o['program_id']] ?? null;
    if ($p === null || $p['deleted_at'] !== null) {
        return 'Its project was deleted, so it is not shown.';
    }
    return $p['status'] !== 'active' ? 'Its project is unpublished, so it is not shown.' : '';
};

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <div>
            <h1>Donate Options</h1>
            <p class="module-intro">The choices in the Donate page's "Program" dropdown, in the order below. The option named <code>general</code> is selected when the page opens.</p>
          </div>
          <?php if (can('content.create')): ?>
          <a href="form.php" class="btn-primary">+ Add Option</a>
          <?php endif; ?>
        </div>

        <?= listToolbar($params, [
            'status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'draft' => 'Draft']],
        ], 'Search options…') ?>

        <?php if (!$options): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No options match that search.'
                : 'No options yet - the Donate page is listing "Where it\'s needed most" and every published project. Add the first option to take over.' ?>
          </div>
        <?php else: ?>
          <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
          <?= bulkToolbar() ?>
          <?= reorderNotice($params) ?>
          <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>

          <div class="table-scroll">
            <table class="data-table"<?= reorderTableAttributes($canReorder) ?>>
              <thead>
                <tr>
                  <th class="col-check">
                    <input type="checkbox" data-bulk-all aria-label="Select all options on this page">
                  </th>
                  <?php if ($canReorder): ?><th><span class="sr-only">Reorder</span></th><?php endif; ?>
                  <?= orderHeader($params) ?>
                  <?= listSortHeader('Option', 'label', $params) ?>
                  <th>Linked project</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <?= listSortHeader('Updated', 'updated', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($options as $o): ?>
                  <?php $name = $optionName($o); $note = $projectNote($o); ?>
                  <tr<?= $canReorder ? ' data-id="' . (int) $o['id'] . '" draggable="true"' : '' ?>>
                    <td class="col-check">
                      <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $o['id'] ?>"
                             data-bulk-item aria-label="Select <?= e($name) ?>">
                    </td>
                    <?php if ($canReorder): ?><?= reorderCell($name) ?><?php endif; ?>
                    <?= orderCell($o['display_order'], $dupeOrders) ?>
                    <td>
                      <div class="data-table-primary"><?= e($name) ?></div>
                      <div class="data-table-secondary">donate?program=<?= e($o['slug']) ?></div>
                      <?php if ($note !== ''): ?><div class="data-table-secondary"><?= e($note) ?></div><?php endif; ?>
                    </td>
                    <td>
                      <?php if ($o['program_id'] !== null && isset($programs[$o['program_id']])): ?>
                        <?= e($programs[$o['program_id']]['name']) ?>
                      <?php else: ?>
                        <span class="data-table-secondary">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="status-badge status-badge--<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($o['updated_at']))) ?></td>
                    <td class="data-table-actions">
                      <a href="form.php?id=<?= (int) $o['id'] ?>" class="link-edit">Edit<span class="sr-only"> <?= e($name) ?></span></a>
                      <?php if (can('content.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($name) ?>&quot;? It will move to Trash, where you can restore it.">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($name) ?></span></button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($total, $params) ?>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
