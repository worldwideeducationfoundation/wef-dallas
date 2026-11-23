<?php
/**
 * Shared admin shell (sidebar + topbar + flash message slot). The
 * including page must already have called requireLogin() and set $user
 * itself before requiring this — auth is intentionally not enforced in
 * here, so it's never possible to add a new page and have it "just
 * happen" to be protected without that being visible in the page's own
 * first few lines.
 *
 * Expects, from the including page:
 *   $pageTitle  — string, used in <title>
 *   $activeNav  — string, must match one of $navItems' labels below
 *   $user       — array from currentUser()
 *   $linkBase   — relative path back to the admin/ root ('' for pages
 *                 directly in admin/, '../../' for admin/modules/x/*.php)
 */

// The Log Out button is a POST form carrying a CSRF token, so this shell
// needs csrfField() whichever includes the page itself loaded:
// dashboard.php, for one, loads only auth.php.
require_once __DIR__ . '/csrf.php';

// 'cap' is the capability required to reach the page (null = everyone
// signed in). Filtered against the same can() used by requireCapability()
// on the target page itself, so the sidebar can never offer a link that
// the destination would immediately bounce the user back from.
$navItems = [
    ['label' => 'Dashboard', 'href' => 'dashboard.php', 'cap' => null, 'soon' => false, 'group' => ''],
    ['label' => 'Home Slideshow', 'href' => 'modules/hero-slides/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Page Content', 'href' => 'modules/pages/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Pages On/Off', 'href' => 'modules/page-visibility/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Donate Options', 'href' => 'modules/donate-options/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Partner Logos', 'href' => 'modules/partners/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Project Updates', 'href' => 'modules/updates/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Gallery', 'href' => 'modules/gallery/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Success Stories', 'href' => 'modules/success-stories/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Video Gallery', 'href' => 'modules/video-gallery/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Map Centers', 'href' => 'modules/map-centers/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Website content'],
    ['label' => 'Programs', 'href' => 'modules/programs/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Projects & impact'],
    ['label' => 'Completed Projects', 'href' => 'modules/completed-projects/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Projects & impact'],
    ['label' => 'Mission Projects', 'href' => 'modules/mission-projects/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Projects & impact'],
    ['label' => 'Impact Goals Roadmap', 'href' => 'modules/impact-milestones/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Projects & impact'],
    ['label' => 'Annual Reports', 'href' => 'modules/reports/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Projects & impact'],
    ['label' => 'Team', 'href' => 'modules/team/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'People'],
    ['label' => 'Honor Board / Sponsors', 'href' => 'modules/sponsors/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'People'],
    ['label' => 'Form Submissions', 'href' => 'modules/submissions/index.php', 'cap' => 'submissions.view', 'soon' => false, 'group' => 'Messages'],
    ['label' => 'Newsletter', 'href' => 'modules/newsletter/index.php', 'cap' => 'newsletter.manage', 'soon' => false, 'group' => 'Messages'],
    ['label' => 'Media Manager', 'href' => 'modules/media/index.php', 'cap' => 'media.view', 'soon' => false, 'group' => 'Files'],
    ['label' => 'Trash', 'href' => 'modules/trash/index.php', 'cap' => 'content.view', 'soon' => false, 'group' => 'Files'],
    ['label' => 'Activity Log', 'href' => 'modules/activity/index.php', 'cap' => 'activity.view', 'soon' => false, 'group' => 'Administration'],
    ['label' => 'Users & Roles', 'href' => 'modules/users/index.php', 'cap' => 'users.manage', 'soon' => false, 'group' => 'Administration'],
    ['label' => 'Settings', 'href' => 'modules/settings/index.php', 'cap' => 'settings.manage', 'soon' => false, 'group' => 'Administration'],
];

// trashCount() and submissionUnreadCount() are used by the nav badges below.
require_once __DIR__ . '/trash.php';
require_once __DIR__ . '/../../../includes/form-submissions.php';

$navItems = array_values(array_filter(
    $navItems,
    fn(array $item): bool => $item['cap'] === null || can($item['cap'])
));

$initial = strtoupper(substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> — WEF Admin</title>
<!-- Self-hosted DM Sans (extras/tools/fetch-fonts.php). Linked here rather than
     @import-ed from admin.css so it downloads in parallel with the stylesheet
     instead of being discovered only after it has been parsed. -->
<link rel="stylesheet" href="<?= e($linkBase) ?>assets/css/fonts.css">
<link rel="stylesheet" href="<?= e($linkBase) ?>assets/css/admin.css">
<?php // Page-specific stylesheets (paths under admin/assets/), e.g. the map
      // library on the Map Centers form. ?>
<?php foreach ($extraStyles ?? [] as $extraStyle): ?>
<link rel="stylesheet" href="<?= e($linkBase . $extraStyle) ?>">
<?php endforeach; ?>
<?php // Kept inline (before first paint, so a dark-theme admin never flashes light)
      // and allowed by its SHA-256 hash in the admin .htaccess policy. One line on
      // purpose: the hash covers every byte, line endings included. Editing it
      // means updating that hash - see extras/tools/csp-hashes.php. ?>
<script>try{if(localStorage.getItem('admin-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="app-shell">
    <div class="app-sidebar-backdrop" data-nav-close hidden></div>
    <aside class="app-sidebar" id="app-sidebar">
      <div class="app-sidebar-brand">
        <img src="<?= e($linkBase) ?>../assets/images/logo.png" alt="WEF Logo" width="36" height="36" class="app-sidebar-brand-logo">
        <span>Admin Panel</span>
      </div>
      <nav class="app-nav" aria-label="Admin sections">
        <?php $navGroup = ''; ?>
        <?php foreach ($navItems as $item): ?>
          <?php // 22 links in one column were hard to scan; a heading marks
                // each group. Items the role can't see are already filtered
                // out, so a group with nothing visible prints no heading. ?>
          <?php if (($item['group'] ?? '') !== $navGroup): $navGroup = $item['group'] ?? ''; ?>
            <div class="app-nav-group"><?= e($navGroup) ?></div>
          <?php endif; ?>
          <?php if ($item['soon']): ?>
            <div class="app-nav-item is-disabled">
              <span><?= e($item['label']) ?></span>
              <span class="app-nav-soon">Soon</span>
            </div>
          <?php else: ?>
            <a href="<?= e($linkBase . $item['href']) ?>" class="app-nav-item<?= $item['label'] === $activeNav ? ' is-active' : '' ?>"<?= $item['label'] === $activeNav ? ' aria-current="page"' : '' ?>>
              <span><?= e($item['label']) ?></span>
              <?php if ($item['label'] === 'Trash' && ($trashPending = trashCount()) > 0): ?>
                <span class="app-nav-count"><?= $trashPending ?></span>
              <?php endif; ?>
              <?php // Unread enquiries, badged like Trash. The count is only
                    // queried when this item is in the nav at all, so a role
                    // without submissions.view never runs it. ?>
              <?php if ($item['label'] === 'Form Submissions' && ($unreadPending = submissionUnreadCount()) > 0): ?>
                <span class="app-nav-count app-nav-count--alert"><?= $unreadPending ?></span>
              <?php endif; ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="app-main">
      <header class="app-topbar">
        <div class="app-topbar-left">
          <button type="button" class="nav-toggle" data-nav-toggle aria-controls="app-sidebar" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="app-topbar-right">
          <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
            <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display: none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          </button>
          <!-- The signed-in user's own name is the link to their own account
               page — the place people instinctively click to change a password.
               Available to every role: account.php only ever acts on
               currentUser(), so it needs no capability. -->
          <a class="app-user" href="<?= e($linkBase) ?>account.php"
             aria-label="Your account settings for <?= e($user['name']) ?>">
            <div class="app-user-avatar" aria-hidden="true"><?= e($initial) ?></div>
            <div>
              <div class="app-user-name"><?= e($user['name']) ?></div>
              <div class="app-user-role"><?= e(ROLE_LABELS[$user['role']] ?? ucwords(str_replace('_', ' ', $user['role']))) ?></div>
            </div>
          </a>
        </div>
        <?php // A form, not a link: signing out changes state, so it takes a
              // POST with the CSRF token - any page could otherwise log an
              // admin out just by embedding logout.php. ?>
        <form method="post" action="<?= e($linkBase) ?>logout.php" class="logout-form">
          <?= csrfField() ?>
          <button type="submit" class="btn-logout">Log Out</button>
        </form>
      </header>

      <main class="app-content" id="main-content">
        <?php if ($flash = flashGet()): ?>
          <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
