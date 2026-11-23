<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>

<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'WEF At A Glance — Worldwide Education Foundation';
  $seoDescription = 'Worldwide Education Foundation (WEF) At A Glance: Our Active Projects, Vision, Mission, and Beliefs.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>

  <!-- The Google Fonts preconnects and stylesheet link that were here are gone.
       Both families this page uses are now self-hosted and declared in
       css/fonts.css, which head-meta.php links on every page — so Cinzel is
       covered, and the five weights of Plus Jakarta Sans this tag re-requested
       were already being loaded by the shared sheet. Two preconnects to
       origins the page no longer contacts were pure overhead. -->

  <style>
    /* ==========================================================================
       WEF AT A GLANCE — EXACT PDF COLOR PALETTE & MARGIN LAYOUT STYLING
       ========================================================================== */
    :root {
      --pdf-orange: #df772c;
      --pdf-green: #60ae51;
      --pdf-green-dark: #4d9440;
      --pdf-dark: #221f20;
      --pdf-gray: #494849;
      --pdf-light-orange: #fdf8f4;
      --pdf-light-green: #f4fbf2;
      --pdf-border: #e2e8f0;
    }

    body {
      background-color: #ffffff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--pdf-dark);
      margin: 0;
      padding: 0;
    }

    .glance-page-container {
      max-width: 1300px;
      margin: 0 auto;
      padding-top: calc(var(--header-height, 90px) + 2.5rem);
      padding-bottom: clamp(1.5rem, 1rem + 2vw, 3rem);
      padding-left: 1.5rem;
      padding-right: 1.5rem;
      box-sizing: border-box;
    }

    .glance-title-header {
      text-align: center;
      margin-top: 1rem;
      margin-bottom: 3rem;
    }

    .glance-main-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(2.2rem, 4vw, 3.2rem);
      font-weight: 800;
      color: var(--pdf-orange);
      letter-spacing: 0.04em;
      margin: 0 0 0.5rem 0;
      line-height: 1.25;
    }

    .glance-sub-title {
      font-size: clamp(1.1rem, 1.8vw, 1.4rem);
      font-weight: 700;
      color: var(--pdf-gray);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin: 0;
      line-height: 1.4;
    }

    /* Executive Data Table Frame */
    .glance-card-frame {
      background: #ffffff;
      border-radius: 14px;
      box-shadow: 0 8px 25px rgba(34, 31, 32, 0.06);
      border: 1px solid var(--pdf-border);
      overflow: hidden;
      margin-bottom: 4rem;
    }

    .glance-table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .glance-pdf-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.925rem;
      text-align: left;
    }

    .glance-pdf-table th {
      background: var(--pdf-green);
      color: #ffffff;
      font-weight: 700;
      font-size: 0.85rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 1.25rem 1.25rem;
      border-bottom: 3px solid var(--pdf-green-dark);
      white-space: nowrap;
    }

    .glance-pdf-table th:nth-child(1), .glance-pdf-table td:nth-child(1) { min-width: 240px; }
    .glance-pdf-table th:nth-child(2), .glance-pdf-table td:nth-child(2) { min-width: 220px; }
    .glance-pdf-table th:nth-child(3), .glance-pdf-table td:nth-child(3) { min-width: 180px; }
    .glance-pdf-table th:nth-child(4), .glance-pdf-table td:nth-child(4) { min-width: 180px; }
    .glance-pdf-table th:nth-child(5), .glance-pdf-table td:nth-child(5) { min-width: 250px; }

    .glance-pdf-table tbody tr {
      border-bottom: 1px solid var(--pdf-border);
      transition: background-color 0.15s ease;
    }

    .glance-pdf-table tbody tr:nth-child(even) {
      background-color: var(--pdf-light-green);
    }

    @media (hover: hover) and (pointer: fine) {
      .glance-pdf-table tbody tr:hover {
        background-color: #eaf6e8;
      }
    }

    .glance-pdf-table td {
      padding: 1.5rem 1.25rem;
      vertical-align: top;
      line-height: 1.65;
    }

    .project-what-header {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--pdf-dark);
      margin: 0 0 0.35rem 0;
      line-height: 1.35;
    }

    .project-what-sub {
      font-size: 0.875rem;
      color: var(--pdf-gray);
      font-weight: 500;
      margin-bottom: 0.5rem;
      line-height: 1.45;
    }

    .project-launch-date {
      display: inline-block;
      font-size: 0.775rem;
      color: var(--pdf-orange);
      font-weight: 700;
      margin-top: 0.25rem;
    }

    .pdf-ul-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .pdf-ul-list li {
      position: relative;
      padding-left: 1.25rem;
      margin-bottom: 0.5rem;
      color: var(--pdf-dark);
      line-height: 1.6;
    }

    .pdf-ul-list li:last-child {
      margin-bottom: 0;
    }

    .pdf-ul-list li::before {
      content: '•';
      position: absolute;
      left: 0;
      color: var(--pdf-orange);
      font-weight: bold;
      font-size: 1.2rem;
      line-height: 1;
    }

    .notes-bold-highlight {
      font-weight: 700;
      color: var(--pdf-dark);
      margin-top: 0.75rem;
      background: var(--pdf-light-orange);
      padding: 0.6rem 0.85rem;
      border-left: 3.5px solid var(--pdf-orange);
      border-radius: 4px;
      line-height: 1.55;
    }

    /* Below 768px the 5-column table (1070px of combined column min-width)
       stopped fitting the page at all - WHERE/WHO/IMPACT/NOTES cut off
       mid-word with no visible way to discover the horizontal scroll that
       was technically still there. At 768px and up, the same horizontal
       scroll (.glance-table-wrapper, already overflow-x:auto) stays exactly
       as it was: on a tablet or small laptop there's enough surrounding
       page for a partially-visible next column to read as "scroll for
       more," so the table itself is left alone there.
       Each row becomes its own card; each cell keeps its column label
       (from its own data-label attribute) so meaning survives losing the
       table structure. The WHAT cell needs no label - its own heading
       already reads as the card's title. */
    @media (max-width: 768px) {
      .glance-table-wrapper {
        overflow-x: visible;
      }

      .glance-pdf-table,
      .glance-pdf-table tbody,
      .glance-pdf-table tr,
      .glance-pdf-table td {
        display: block;
        width: auto;
      }

      /* :nth-child(n) (matches every cell) rather than a plain `td` selector
         so this has the same specificity tier as the per-column
         `td:nth-child(1..5) { min-width: ... }` rules above and can
         actually override them - a plain `.glance-pdf-table td` selector
         is specificity (0,2,0), lower than theirs (0,3,0), and would lose
         regardless of appearing later in the file. */
      .glance-pdf-table td:nth-child(n) {
        min-width: 0;
      }

      .glance-pdf-table thead {
        /* Column meaning now comes from each cell's own data-label instead
           - a sighted-only display:none would leave screen readers with
           headers for a table structure that no longer visually exists. */
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
      }

      .glance-pdf-table tbody tr {
        padding: 1.25rem 1.25rem 1.5rem;
        border-bottom: 8px solid var(--pdf-light-green);
      }

      .glance-pdf-table tbody tr:last-child {
        border-bottom: none;
      }

      .glance-pdf-table td {
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--pdf-border);
      }

      .glance-pdf-table td:first-child {
        padding-top: 0;
      }

      .glance-pdf-table td:last-child {
        border-bottom: none;
        padding-bottom: 0;
      }

      .glance-pdf-table td[data-label]::before {
        content: attr(data-label);
        display: block;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--pdf-green-dark);
        margin-bottom: 0.5rem;
      }
    }

    /* Foundational Pillars & Image Row */
    .pillars-image-grid {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 3rem;
      align-items: stretch;
      margin-top: 3.5rem;
      margin-bottom: 4rem;
    }

    @media (max-width: 992px) {
      .pillars-image-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
      }
    }

    .pillar-item-box {
      background: #ffffff;
      border-radius: 14px;
      padding: 1.75rem 2rem;
      border: 1px solid var(--pdf-border);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
      margin-bottom: 1.5rem;
    }

    .pillar-item-box:last-child {
      margin-bottom: 0;
    }

    .pillar-item-box h4 {
      font-family: 'Cinzel', serif;
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--pdf-green);
      margin: 0 0 0.6rem 0;
    }

    .pillar-item-box p {
      font-size: 1.025rem;
      line-height: 1.7;
      color: var(--pdf-dark);
      margin: 0;
    }

    .glance-photo-card {
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 12px 30px rgba(34, 31, 32, 0.1);
      border: 4px solid #ffffff;
      height: 100%;
      min-height: 380px;
      display: flex;
    }

    .glance-photo-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    /* PDF Footer Statement */
    .glance-footer-note {
      background: #ffffff;
      border-radius: 14px;
      padding: 2.5rem 2rem;
      border: 1px solid var(--pdf-border);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
      text-align: center;
      margin-top: 2rem;
      margin-bottom: 0;
    }

    .glance-footer-note p {
      font-size: 0.975rem;
      line-height: 1.8;
      color: var(--pdf-gray);
      max-width: 1000px;
      margin: 0 auto 1.25rem auto;
    }

    .glance-footer-date {
      font-weight: 800;
      color: var(--pdf-dark);
      font-size: 1.05rem;
      margin-bottom: 1.75rem;
    }

    .btn-download-pdf-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.9rem 2.25rem;
      background: linear-gradient(135deg, var(--pdf-orange) 0%, #c5621c 100%);
      color: #ffffff;
      font-weight: 700;
      font-size: 0.95rem;
      border-radius: 12px;
      text-decoration: none;
      box-shadow: 0 8px 20px rgba(223, 119, 44, 0.3);
      transition: all 0.2s ease;
    }

    @media (hover: hover) and (pointer: fine) {
      .btn-download-pdf-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 25px rgba(223, 119, 44, 0.45);
        color: #ffffff;
      }
    }
  </style>
</head>

<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content" class="glance-page-container">

    <!-- Document Title -->
    <div class="glance-title-header">
      <h1 class="glance-main-title"><?= block('our-mission', 'page_title') ?></h1>
      <h2 class="glance-sub-title"><?= block('our-mission', 'subtitle') ?></h2>
    </div>

    <!-- Active Projects Table -->
    <div class="glance-card-frame">
      <div class="glance-table-wrapper">
        <table class="glance-pdf-table">
          <thead>
            <tr>
              <th style="width: 24%;">WHAT</th>
              <th style="width: 22%;">WHERE</th>
              <th style="width: 18%;">WHO</th>
              <th style="width: 18%;">IMPACT</th>
              <th style="width: 18%;">NOTES</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (publishedMissionProjects() as $project): ?>
            <tr>
              <td>
                <div class="project-what-header"><?= e($project['what_header']) ?></div>
                <?php if (!empty($project['what_sub'])): ?><div class="project-what-sub"><?= e($project['what_sub']) ?></div><?php endif; ?>
                <?php if (!empty($project['launch_year'])): ?><div class="project-launch-date">(Launched in <?= e($project['launch_year']) ?>)</div><?php endif; ?>
              </td>
              <td data-label="Where"><?= missionBulletList($project['where_text'] ?? null) ?></td>
              <td data-label="Who"><?= missionBulletList($project['who_text'] ?? null) ?></td>
              <td data-label="Impact"><?= missionBulletList($project['impact_text'] ?? null) ?></td>
              <td data-label="Notes">
                <?= missionBulletList($project['notes_text'] ?? null) ?>
                <?php if (!empty($project['notes_highlight'])): ?>
                <div class="notes-bold-highlight">
                  <?= e($project['notes_highlight']) ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pillars & Image Row -->
    <div class="pillars-image-grid">
      <div>
        <div class="pillar-item-box">
          <h4><?= block('our-mission', 'pillar1_title') ?></h4>
          <p><?= blockRich('our-mission', 'pillar1_text') ?></p>
        </div>

        <div class="pillar-item-box">
          <h4><?= block('our-mission', 'pillar2_title') ?></h4>
          <p><?= blockRich('our-mission', 'pillar2_text') ?></p>
        </div>

        <div class="pillar-item-box">
          <h4><?= block('our-mission', 'pillar3_title') ?></h4>
          <p><?= blockRich('our-mission', 'pillar3_text') ?></p>
        </div>
      </div>

      <div class="glance-photo-card">
        <img width="1536" height="1152" src="<?= e(blockImage('our-mission', 'pillars_image')) ?>" alt="Bright Beginnings Early Childhood Learning Center in Chitral" loading="lazy" decoding="async">
      </div>
    </div>

    <!-- PDF Footer Note & Download Button -->
    <div class="glance-footer-note">
      <p>
        <?= blockRich('our-mission', 'footer_note') ?>
      </p>
      <div class="glance-footer-date"><?= block('our-mission', 'footer_date') ?></div>

      <div>
        <a href="assets/PDF/WEF-at-a-Glance-Final-Updated.pdf" target="_blank" rel="noopener noreferrer" class="btn-download-pdf-primary">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <?= block('our-mission', 'download_button_label') ?>
        </a>
      </div>
    </div>

  </main>

  <!-- FOOTER -->
<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
</body>

</html>
