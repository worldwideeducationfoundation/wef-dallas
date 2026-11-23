<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
﻿<?php require_once __DIR__ . '/../includes/content.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>

  <?php
    // Social-card and canonical metadata. Values mirror this page's own
    // <title> and description below, so the card and the page always agree.
    $seoTitle = 'Our Team — Worldwide Education Foundation';
    $seoDescription = 'Meet the Advisory Board Members and leadership team of the Worldwide Education Foundation.';
  ?>
  <?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
</head>

<body>
  <?php $wefTagline = 'Providing the Seeds for Growth';
  include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">

    <!-- PAGE HEADER -->
    <?php $pageHero = pageHero('our-team'); ?>
    <section class="team-page-header<?= $pageHero['class'] ?>"<?= $pageHero['style'] ?>>
      <?= $pageHero['layers'] ?>
      <div class="container">
        <h1><?= block('our-team', 'page_title') ?></h1>
        <p><?= blockRich('our-team', 'intro') ?></p>
      </div>
    </section>

    <!-- TEAM SECTION -->
    <?php
    // Team members come from the CMS (admin → Team). Only members set to
    // Active appear here; drafts stay hidden. Order and section come from
    // the same place, so rearranging in the admin rearranges this page.
    $teamSections = publishedTeamBySection([
      'Advisory Board Members',
      'Chitral Team',
      'Collaborating Partners',
    ]);
    ?>
    <section class="section team-section">
      <div class="container">
        <?php foreach ($teamSections as $sectionTitle => $members): ?>
        <div class="team-group-header">
        <?php
            $parts = explode(' ', $sectionTitle, 2);
            $label = $parts[0];
            $rest = isset($parts[1]) ? $parts[1] : '';
          ?>
          <div class="header-fieldset">
            <span class="header-fieldset-label"><?= e($label) ?></span>
            <h2 class="no-underline"><?= e($rest) ?></h2>
          </div>
        </div>
        <div class="team-grid<?= $sectionTitle === 'Collaborating Partners' ? ' team-grid--centered' : '' ?>">
          <?php foreach ($members as $member): ?>
            <?php
              // Anchor id kept in the same slug form the hand-written
              // markup used, so existing deep links like #connie-daya
              // keep working.
              $anchor = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($member['name']))), '-');
              $photo = uploadUrl($member['image_path']);
            ?>
            <div class="team-card reveal" id="<?= e($anchor) ?>">
              <?php if ($photo !== null): ?>
                <div class="team-card-image">
                  <img src="<?= e($photo) ?>" alt="<?= e($member['name']) ?>" loading="lazy" decoding="async">
                </div>
              <?php endif; ?>
              <div class="team-card-body">
                <h3><?= e($member['name']) ?></h3>
                <span class="team-role"><?= e($member['role']) ?></span>
                <?php if (trim((string) $member['bio']) !== ''): ?>
                  <div class="team-bio"><?= richText($member['bio']) ?></div>
                  <button class="team-read-more" aria-expanded="false">Read More <span>&rarr;</span></button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </section>

    <!-- FOOTER -->
  </main>

  <?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <!-- TEAM MODAL -->
  <div class="team-modal-overlay" id="team-modal-overlay">
    <div class="team-modal-content">
      <div class="team-modal-header-actions">
        <button class="team-modal-back" aria-label="Back to team">
          &larr;
        </button>
        <button class="team-modal-close" id="team-modal-close" aria-label="Close modal">&times;</button>
      </div>
      <div class="team-modal-layout">
        <div class="team-modal-image">
          <img id="team-modal-img" src="" alt="" decoding="async">
        </div>
        <div class="team-modal-body">
          <h3 id="team-modal-name"></h3>
          <span class="team-role" id="team-modal-role"></span>
          <div class="team-bio" id="team-modal-bio"></div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= asset('js/main.js') ?>" defer></script>
  <script src="<?= asset('js/our-team.js') ?>" defer></script>
</body>

</html>