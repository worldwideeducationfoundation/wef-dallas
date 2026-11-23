<?php
/**
 * Skip link, mobile overlay and the site header/navigation.
 *
 * Previously duplicated in all 22 public pages, which had already drifted
 * apart: index.php was missing the "Impact Goals" link the other 21 had, and
 * three pages pointed the logo at ./#home instead of ./ . This partial is the
 * markup 16 of the 22 pages already agreed on, so converting them is a no-op.
 *
 * $wefTagline -- optional. Defaults to the tagline used by 20 of the 22 pages.
 *                index.php and our-team.php pass their own so their output is
 *                unchanged. The site should settle on one; that is a content
 *                decision, not a refactor.
 */
// content.php helpers used by the header.
require_once __DIR__ . '/../content.php';
require_once __DIR__ . '/../page-visibility.php';

// The menu. A page switched off in admin -> Pages On/Off is left out; a
// dropdown left with no pages is left out too, except About and Impact,
// whose heading still opens their section of the home page. A dropdown whose
// heading pointed at a page now off points at its first page still on.
$navMenus = [
    ['label' => 'About', 'href' => './#about', 'keep' => true, 'items' => [
        'our-team' => 'Our Team',
        'our-mission' => 'Our Mission',
    ]],
    ['label' => 'Impact', 'href' => './#impact', 'keep' => true, 'items' => [
        'success-stories' => 'Success Stories',
        'impact-goals' => 'Impact Goals',
        'annual-reports' => 'Annual Reports',
    ]],
    ['label' => 'What We Do', 'href' => 'active-projects', 'keep' => false, 'items' => [
        'active-projects' => 'Active Projects',
        'completed-projects' => 'Completed Projects',
    ]],
    ['label' => 'Gallery', 'href' => 'gallery', 'keep' => false, 'items' => [
        'gallery' => 'Photo Gallery',
        'video-gallery' => 'Video Gallery',
    ]],
    ['label' => 'Contact', 'href' => 'contact', 'keep' => false, 'items' => [
        'contact' => 'Get in Touch',
        'volunteer' => 'Volunteer',
    ]],
];
?>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="mobile-overlay" id="mobile-overlay"></div>

  <header class="header" id="main-header">
    <div class="container">
      <a href="./" class="header-logo" id="header-logo" aria-label="Worldwide Education Foundation Home">
        <img src="assets/images/logo.png" alt="Worldwide Education Foundation Logo" width="52" height="52" loading="eager" decoding="async">
        <div class="header-logo-text">
          <span>Worldwide Education Foundation</span>
          <span><?= htmlspecialchars($wefTagline ?? 'Empowering Through Education', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </a>

      <nav class="nav-menu" id="nav-menu" aria-label="Main Navigation">
        <a href="./">Home</a>
        <?php foreach ($navMenus as $menu): ?>
          <?php
            $menuItems = array_filter($menu['items'], 'pageEnabled', ARRAY_FILTER_USE_KEY);
            if ($menuItems === [] && !$menu['keep']) {
                continue;
            }
            $menuHref = $menu['href'];
            if (!$menu['keep'] && !pageEnabled($menuHref)) {
                $menuHref = (string) array_key_first($menuItems);
            }
          ?>
          <?php if ($menuItems === []): ?>
        <a href="<?= e($menuHref) ?>"><?= e($menu['label']) ?></a>
          <?php else: ?>
        <div class="nav-dropdown">
          <a href="<?= e($menuHref) ?>" class="dropdown-toggle" aria-expanded="false"><?= e($menu['label']) ?></a>
          <div class="dropdown-menu">
            <?php foreach ($menuItems as $slug => $label): ?>
            <a href="<?= e($slug) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (pageEnabled('donate')): ?>
        <a href="donate" class="nav-donate" id="nav-donate-btn">Donate Now</a>
        <?php endif; ?>
      </nav>

      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle Navigation" aria-expanded="false" aria-controls="nav-menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>
