<?php
/**
 * Pages On/Off — switch a public page off, and back on.
 *
 * A page that is off leaves the header menu, every link to it on the site
 * turns into plain text, and its address answers "Page not found". All of
 * that happens in includes/page-visibility.php; this page only flips
 * pages.is_enabled. The home page has no switch.
 *
 * Each switch is its own small form, so it works without JavaScript (and
 * under the admin's script policy) and a click saves straight away.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/page-visibility.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Pages On/Off';
$activeNav = 'Pages On/Off';
$linkBase = '../../';
$canEdit = can('content.edit');

// page_key => is_enabled. Fails only on a database that has not had the
// update yet (no is_enabled column): the switches are then not offered.
$ready = true;
$states = [];
try {
    $states = getDb()->query('SELECT page_key, is_enabled FROM pages')->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $ready = false;
}

$siteBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
$offCount = count(array_filter(array_keys(SWITCHABLE_PAGES), fn($slug) => isset($states[$slug]) && (int) $states[$slug] === 0));

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Pages On/Off</h1>
        </div>

        <div class="welcome-card">
          <p>
            Turn a page <strong>off</strong> to take it off the website: it disappears from the menu at the top,
            every link to it becomes plain text, and its address shows "Page not found". Its content is kept,
            so turning it back <strong>on</strong> brings it back exactly as it was. The home page is always on.
          </p>
        </div>

        <?php if (!$ready): ?>
          <div class="alert alert-error" role="alert">
            The database needs a one-time update before pages can be switched off: import
            <code>page-switches-20261016.sql</code> in phpMyAdmin (or run
            <code>php admin/database/migrate.php</code>). Until then every page stays on.
          </div>
        <?php endif; ?>

        <p class="data-table-secondary">
          <?= $offCount === 0 ? 'All pages are on.' : e($offCount . ' of ' . count(SWITCHABLE_PAGES) . ' pages are off.') ?>
        </p>

        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>Page</th>
                <th>Status</th>
                <th><span class="sr-only">Switch and view</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (SWITCHABLE_PAGES as $slug => $label): ?>
                <?php $on = !isset($states[$slug]) || (int) $states[$slug] === 1; ?>
                <tr>
                  <td>
                    <div class="data-table-primary"><?= e($label) ?></div>
                    <div class="data-table-secondary">/<?= e($slug) ?></div>
                  </td>
                  <td>
                    <span class="status-badge status-badge--<?= $on ? 'active' : 'draft' ?>"><?= $on ? 'On' : 'Off' ?></span>
                  </td>
                  <td class="data-table-actions">
                    <?php if ($canEdit && $ready): ?>
                      <form method="post" action="save.php" class="page-switch-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="page" value="<?= e($slug) ?>">
                        <input type="hidden" name="enabled" value="<?= $on ? '0' : '1' ?>">
                        <button type="submit" class="page-switch" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>"
                          aria-label="<?= e($label) ?> page" title="<?= $on ? 'Turn off' : 'Turn on' ?>">
                          <span class="page-switch-track" aria-hidden="true"><span class="page-switch-thumb"></span></span>
                          <span class="page-switch-text" aria-hidden="true"><?= $on ? 'Turn off' : 'Turn on' ?></span>
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($on): ?>
                      <a href="<?= e($siteBase . '/' . $slug) ?>" class="link-edit" target="_blank" rel="noopener">View<span class="sr-only"> <?= e($label) ?></span></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
