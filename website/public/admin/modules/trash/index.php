<?php
/**
 * Trash — everything soft-deleted across all content types, newest first,
 * with Restore and Delete Permanently.
 *
 * One page rather than a Trash tab inside each module: when someone
 * deletes the wrong thing they usually remember *what* they deleted, not
 * which module it lived in, so a single reverse-chronological list is the
 * fastest way back.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../includes/listing.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Trash';
$activeNav = 'Trash';
$linkBase = '../../';

// Same pager the content modules use. There is no sortable column here —
// Trash is always newest-first, which is the order someone looking for a
// mistake they just made needs — so listParams is called only for its page /
// offset handling.
$params = listParams(['default' => 'deleted_at'], 'default');
$total = trashCount();
$items = trashedItems($params['offset'], $params['perPage']);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Trash</h1>
        </div>

        <div class="welcome-card">
          <p>
            Deleted content is kept here instead of being removed immediately.
            <strong>Restore</strong> puts an item back exactly as it was, including its photo.
            <strong>Delete permanently</strong> is the only action that actually removes anything —
            including the uploaded files, which is why it cannot be undone.
          </p>
        </div>

        <?php if (!$items): ?>
          <div class="empty-state">Trash is empty. Deleted content will appear here.</div>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Type</th>
                  <th>Deleted</th>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td class="data-table-primary"><?= e($item['label']) ?></td>
                    <td><?= e($item['typeLabel']) ?></td>
                    <td>
                      <div><?= e(date('M j, Y', strtotime($item['deleted_at']))) ?></div>
                      <div class="data-table-secondary"><?= e(date('g:i a', strtotime($item['deleted_at']))) ?></div>
                    </td>
                    <td class="data-table-actions">
                      <?php // Per row, not per page: this list mixes types, and
                            // what someone may do to a trashed programme is not
                            // what they may do to a trashed enquiry. The buttons
                            // check the same capability the handler enforces. ?>
                      <?php if (can(TRASH_TYPES[$item['type']]['restoreCap'])): ?>
                        <form method="post" action="restore.php">
                          <?= csrfField() ?>
                          <input type="hidden" name="type" value="<?= e($item['type']) ?>">
                          <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                          <button type="submit" class="link-edit">Restore<span class="sr-only"> <?= e($item['label']) ?></span></button>
                        </form>
                      <?php endif; ?>
                      <?php if (can(TRASH_TYPES[$item['type']]['purgeCap'])): ?>
                        <form method="post" action="purge.php"
                              data-confirm="Permanently delete &quot;<?= e($item['label']) ?>&quot;? This removes the record and its uploaded files for good. It cannot be undone.">
                          <?= csrfField() ?>
                          <input type="hidden" name="type" value="<?= e($item['type']) ?>">
                          <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                          <button type="submit" class="link-delete">Delete permanently<span class="sr-only"> <?= e($item['label']) ?></span></button>
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
