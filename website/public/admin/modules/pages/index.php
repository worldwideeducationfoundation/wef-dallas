<?php
/**
 * Page Content — the editable text blocks on each public page.
 *
 * These are the parts of the site that aren't one of the six content
 * types: page headings, intro paragraphs, section copy. They used to be
 * hardcoded across 18 PHP files.
 *
 * The registry (includes/content-blocks.php) defines which blocks exist
 * and their default text; this module edits the overrides.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/content-blocks.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Page Content';
$activeNav = 'Page Content';
$linkBase = '../../';

// One query for the whole overview rather than one per page.
$counts = getDb()->query(
    "SELECT page, COUNT(*) AS n, MAX(updated_at) AS last_edit
     FROM content_blocks
     WHERE value IS NOT NULL AND value <> ''
     GROUP BY page"
)->fetchAll(PDO::FETCH_UNIQUE);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Page Content</h1>
        </div>

        <div class="welcome-card">
          <p>
            Text that appears directly on a page — headings, intro paragraphs, section copy.
            Anything left untouched shows its original wording, and <strong>Reset</strong> puts a block back to that
            original at any time. Content lists like Programs, Team and Gallery are edited in their own sections.
          </p>
        </div>

        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>Page</th>
                <th>Editable blocks</th>
                <th>Customised</th>
                <th>Last edited</th>
                <th><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (blockRegistry() as $pageKey => $config): ?>
                <?php
                  $edited = (int) ($counts[$pageKey]['n'] ?? 0);
                  $total = count($config['blocks']);
                ?>
                <tr>
                  <td>
                    <div class="data-table-primary"><?= e($config['label']) ?></div>
                    <div class="data-table-secondary"><?= e($config['url']) ?></div>
                  </td>
                  <td><?= $total ?></td>
                  <td>
                    <?php if ($edited > 0): ?>
                      <span class="status-badge status-badge--active"><?= $edited ?> of <?= $total ?></span>
                    <?php else: ?>
                      <span class="status-badge status-badge--draft">Using defaults</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= isset($counts[$pageKey]['last_edit'])
                        ? e(date('M j, Y', strtotime($counts[$pageKey]['last_edit'])))
                        : '<span class="data-table-secondary">—</span>' ?>
                  </td>
                  <td class="data-table-actions">
                    <a href="edit.php?page=<?= e(urlencode($pageKey)) ?>" class="link-edit">Edit<span class="sr-only"> <?= e($config['label']) ?></span></a>
                    <a href="<?= e(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'], 2)), '/') . $config['url']) ?>" class="link-edit" target="_blank" rel="noopener">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
