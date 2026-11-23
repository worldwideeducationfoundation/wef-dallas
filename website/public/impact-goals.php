<?php require_once __DIR__ . '/../includes/content-blocks.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
  // Social-card and canonical metadata. Values mirror this page's own
  // <title> and description below, so the card and the page always agree.
  $seoTitle = 'Impact Goals — Worldwide Education Foundation';
  $seoDescription = 'Our ten-year roadmap: growing from 10 to 1,200 learning centers, reaching 36,000 students and training 2,400 teachers and entrepreneurs by 2035.';
?>
<?php include __DIR__ . '/../includes/layout/head-meta.php'; ?>
  <title><?= $seoEsc($seoTitle) ?></title>
  <meta name="description" content="<?= $seoEsc($seoDescription) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <?= asyncStylesheet('css/styles.css') ?>
  <?= asyncStylesheet('css/impact-goals-new.css') ?>
</head>
<body>
<?php include __DIR__ . '/../includes/layout/site-header.php'; ?>

  <main id="main-content">
    <div class="impact-goals-wrapper">
        
        <!--==============================
            Hero
        ===============================-->
        <section class="hero">
            <h1><strong><?= block('impact-goals', 'page_title') ?></strong></h1>
            <p class="subtitle"><?= blockRich('impact-goals', 'intro') ?></p>
        </section>

        <!--==============================
            SVG Chart Data Visualization
        ===============================-->
        <?php
          // The chart's geometry (each point's x/y on the curve) is a fixed
          // 5-slot layout hand-tuned for this 1200x900 viewBox - not data, so
          // it stays here as a constant, positionally zipped with up to the
          // first 5 active rows from publishedImpactMilestones(). See the
          // comment on the impact_milestones table in schema.sql for why.
          $chartSlots = [
              ['x' => 110, 'y' => 250],
              ['x' => 340, 'y' => 180],
              ['x' => 600, 'y' => 110],
              ['x' => 850, 'y' => 50],
              ['x' => 1090, 'y' => -20],
          ];
          $milestones = array_slice(publishedImpactMilestones(), 0, count($chartSlots));
          $chartPoints = [];
          foreach ($milestones as $i => $milestone) {
              $chartPoints[] = $chartSlots[$i] + ['milestone' => $milestone];
          }
          $lastMilestone = $chartPoints ? end($chartPoints)['milestone'] : null;
          $firstYear = $chartPoints ? (preg_match('/(\d{4})/', $chartPoints[0]['milestone']['milestone_date'], $m) ? $m[1] : '') : '';
          $lastYear = $lastMilestone ? (preg_match('/(\d{4})/', $lastMilestone['milestone_date'], $m) ? $m[1] : '') : '';
        ?>
        <?php if ($chartPoints): ?>
        <div class="new-svg-chart-wrapper">
            <div class="new-svg-chart-container">
                <svg viewBox="0 -150 1200 900">
                    <!-- Main Line -->
                    <path class="line" d="M<?= implode(' L', array_map(fn(array $p): string => "{$p['x']} {$p['y']}", $chartPoints)) ?>"/>
                    <!-- Vertical -->
                    <?php foreach ($chartPoints as $point): ?>
                    <line class="vertical" x1="<?= (int) $point['x'] ?>" y1="<?= (int) $point['y'] ?>" x2="<?= (int) $point['x'] ?>" y2="650"/>
                    <?php endforeach; ?>
                    <!-- Bars -->
                    <rect class="bar students" x="40" y="300" width="1100" height="35" rx="18"/>
                    <text class="label" x="600" y="324" text-anchor="middle"><?= block('impact-goals', 'chart_students_label') ?></text>
                    <rect class="bar teachers" x="40" y="500" width="1100" height="35" rx="18"/>
                    <text class="label" x="600" y="524" text-anchor="middle"><?= block('impact-goals', 'chart_teachers_label') ?></text>
                    <!-- Timeline -->
                    <line class="timeline" x1="80" y1="650" x2="1090" y2="650"/>
                    <polygon class="arrow" points="1090,650 1055,625 1055,675"/>
                    <!-- Bottom Dots -->
                    <circle cx="80" cy="650" r="10" fill="var(--clr-primary)"/>
                    <circle cx="1090" cy="650" r="10" fill="var(--clr-primary)"/>
                    <!-- Groups -->
                    <?php foreach ($chartPoints as $point): $m = $point['milestone']; ?>
                    <g class="group">
                        <circle class="point" cx="<?= (int) $point['x'] ?>" cy="<?= (int) $point['y'] ?>" r="11"/>
                        <text x="<?= (int) $point['x'] ?>" y="<?= (int) $point['y'] - 105 ?>" text-anchor="middle" class="small">
                            <tspan class="counter" data-target="<?= (int) $m['centers_target'] ?>">0</tspan>
                            <tspan x="<?= (int) $point['x'] ?>" dy="24">CENTERS</tspan>
                            <tspan x="<?= (int) $point['x'] ?>" dy="24"><?= e($m['milestone_date']) ?></tspan>
                        </text>
                        <text x="<?= (int) $point['x'] ?>" y="380" text-anchor="middle">
                            <tspan class="counter" data-target="<?= (int) $m['students_target'] ?>">0</tspan>
                        </text>
                        <text x="<?= (int) $point['x'] ?>" y="580" text-anchor="middle">
                            <tspan class="counter" data-target="<?= (int) $m['teachers_target'] ?>">0</tspan>
                        </text>
                    </g>
                    <?php endforeach; ?>
                    <text x="80" y="700" class="year"><?= e($firstYear) ?></text>
                    <text x="1090" y="700" class="year" text-anchor="end"><?= e($lastYear) ?></text>
                </svg>
            </div>
        </div>

        <!-- Below ~600px the SVG (a fixed 1200x900 viewBox scaled to the
             container width) shrinks its labels down to a few unreadable
             pixels - the chart's own geometry doesn't reflow, it just gets
             smaller. This renders the exact same $chartPoints as a vertical
             list instead of a second query, and reuses class="counter"
             data-target="…" unchanged, so impact-goals-new.js's existing
             animateCounters() (which just queries every .counter on the
             page) picks these up with no JS changes. Swapped for the SVG
             via CSS only (.chart-mobile-list), so both exist in the DOM
             and the counters in whichever one is visible still animate
             when scrolled into view. -->
        <div class="chart-mobile-list">
            <?php foreach ($chartPoints as $point): $m = $point['milestone']; ?>
            <div class="chart-mobile-item">
                <div class="chart-mobile-date"><?= e($m['milestone_date']) ?></div>
                <div class="chart-mobile-stats">
                    <div class="chart-mobile-stat">
                        <span class="chart-mobile-stat-label">Centers</span>
                        <span class="counter" data-target="<?= (int) $m['centers_target'] ?>">0</span>
                    </div>
                    <div class="chart-mobile-stat">
                        <span class="chart-mobile-stat-label"><?= block('impact-goals', 'chart_students_label') ?></span>
                        <span class="counter" data-target="<?= (int) $m['students_target'] ?>">0</span>
                    </div>
                    <div class="chart-mobile-stat">
                        <span class="chart-mobile-stat-label"><?= block('impact-goals', 'chart_teachers_label') ?></span>
                        <span class="counter" data-target="<?= (int) $m['teachers_target'] ?>">0</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!--==============================
            Impact Summary
        ===============================-->
        <div class="card-carousel">
          <button type="button" class="carousel-arrow carousel-arrow--prev" aria-label="Previous summary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <section class="summary card-carousel-track">
            <div class="summary-card">
                <div class="icon">
                    <img width="256" height="168" src="assets/icons/SVG/LEARNING SPACES.png" alt="Learning Spaces" decoding="async" loading="lazy">
                </div>
                <h2 class="counter" data-target="<?= (int) ($lastMilestone['centers_target'] ?? 0) ?>">0</h2>
                <h4><?= block('impact-goals', 'summary_centers_label') ?></h4>
                <p><?= blockRich('impact-goals', 'summary_centers_text') ?></p>
            </div>
            <div class="summary-card">
                <div class="icon">
                    <img width="256" height="202" src="assets/icons/SVG/STUDENTS.png" alt="Students" decoding="async" loading="lazy">
                </div>
                <h2 class="counter" data-target="<?= (int) ($lastMilestone['students_target'] ?? 0) ?>">0</h2>
                <h4><?= block('impact-goals', 'summary_students_label') ?></h4>
                <p><?= blockRich('impact-goals', 'summary_students_text') ?></p>
            </div>
            <div class="summary-card">
                <div class="icon">
                    <img width="256" height="189" src="assets/icons/SVG/TEACHERS.png" alt="Teachers" decoding="async" loading="lazy">
                </div>
                <h2 class="counter" data-target="<?= (int) ($lastMilestone['teachers_target'] ?? 0) ?>">0</h2>
                <h4><?= block('impact-goals', 'summary_teachers_label') ?></h4>
                <p><?= blockRich('impact-goals', 'summary_teachers_text') ?></p>
            </div>
          </section>
          <button type="button" class="carousel-arrow carousel-arrow--next" aria-label="Next summary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>

        <!--==============================
            Call To Action
        ===============================-->
        <section class="cta">
            <h2><?= block('impact-goals', 'cta_title') ?></h2>
            <p><?= blockRich('impact-goals', 'cta_text') ?></p>
            <button type="button" data-scroll-to=".new-svg-chart-wrapper">Explore Our Journey</button>
        </section>

    </div>
  </main>

<?php include __DIR__ . '/../includes/layout/footer-full.php'; ?>

  <script src="<?= asset('js/main.js') ?>" defer></script>
  <script src="<?= asset('js/impact-goals-new.js') ?>" defer></script>
</body>
</html>
