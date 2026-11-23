<?php
/**
 * Dashboard. Replaces the Phase-2 placeholder, which described modules as
 * "Soon" that have all since shipped, and showed no actual data.
 *
 * The content counts are gathered in a single UNION ALL round trip rather
 * than one query per module — six COUNT(*)s over small tables is cheap,
 * six separate connections' worth of latency is not, and this page is the
 * one every admin loads first.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../includes/form-submissions.php';

initSession();
requireLogin();

$user = currentUser();
$pageTitle = 'Dashboard';
$activeNav = 'Dashboard';
$linkBase = '';

/**
 * Each row: the module key, total rows, and how many are published.
 * SUM(status = 'active') is a count of published rows — MySQL evaluates
 * the comparison to 1/0. Tables without a status column report NULL and
 * the card just omits the "live" line.
 *
 * Every content count excludes trashed rows. Without "deleted_at IS NULL"
 * these counts included items sitting in Trash — and because soft delete
 * stamps deleted_at without touching status, a trashed-but-published item was
 * counted as LIVE. The dashboard was reporting 10 programmes when 9 were real,
 * and 26 live team members when the site was showing 25. The number an admin
 * checks to answer "what is on the site right now" was the one number that
 * could not be trusted.
 *
 * admin_users has no soft delete (accounts are deleted outright, guarded
 * against removing the last super admin), so it is counted as-is.
 */
$statsSql = <<<SQL
    SELECT 'programs' AS k, COUNT(*) AS total, SUM(status = 'active') AS live
      FROM programs WHERE deleted_at IS NULL
    UNION ALL SELECT 'updates',  COUNT(*), SUM(status = 'active')
      FROM project_updates WHERE deleted_at IS NULL
    UNION ALL SELECT 'team',     COUNT(*), SUM(status = 'active')
      FROM team_members WHERE deleted_at IS NULL
    UNION ALL SELECT 'sponsors', COUNT(*), SUM(status = 'active')
      FROM sponsors WHERE deleted_at IS NULL
    UNION ALL SELECT 'gallery',  COUNT(*), SUM(status = 'active')
      FROM gallery_items WHERE deleted_at IS NULL
    UNION ALL SELECT 'reports',  COUNT(*), SUM(status = 'active')
      FROM annual_reports WHERE deleted_at IS NULL
    UNION ALL SELECT 'users',    COUNT(*), SUM(status = 'active')
      FROM admin_users
    UNION ALL SELECT 'submissions', COUNT(*), SUM(status = 'new')
      FROM form_submissions WHERE deleted_at IS NULL
SQL;

$stats = [];
foreach (getDb()->query($statsSql)->fetchAll() as $row) {
    $stats[$row['k']] = ['total' => (int) $row['total'], 'live' => (int) $row['live']];
}

// Cards link straight into the module, but only for modules this role can
// actually open — an editor shouldn't be shown a Users card that bounces.
$cards = [
    ['key' => 'programs', 'label' => 'Programs', 'href' => 'modules/programs/index.php', 'cap' => 'content.view'],
    ['key' => 'updates', 'label' => 'Project Updates', 'href' => 'modules/updates/index.php', 'cap' => 'content.view'],
    ['key' => 'team', 'label' => 'Team Members', 'href' => 'modules/team/index.php', 'cap' => 'content.view'],
    ['key' => 'sponsors', 'label' => 'Sponsors', 'href' => 'modules/sponsors/index.php', 'cap' => 'content.view'],
    ['key' => 'gallery', 'label' => 'Gallery Photos', 'href' => 'modules/gallery/index.php', 'cap' => 'content.view'],
    ['key' => 'reports', 'label' => 'Annual Reports', 'href' => 'modules/reports/index.php', 'cap' => 'content.view'],
    ['key' => 'users', 'label' => 'Admin Users', 'href' => 'modules/users/index.php', 'cap' => 'users.manage'],
    // The 'live' slot holds the unread count for this card rather than a
    // published count — nothing here is published. Rendered differently below,
    // the same way the Users card already reads its slot as "active".
    ['key' => 'submissions', 'label' => 'Form Submissions', 'href' => 'modules/submissions/index.php', 'cap' => 'submissions.view'],
];
$cards = array_values(array_filter($cards, fn(array $c): bool => can($c['cap'])));

// Unread enquiries sit alongside drafts as the other thing on this page that
// is genuinely waiting for someone. Read from the same UNION as the cards, so
// the greeting and the card can never quote different numbers.
$unreadSubmissions = can('submissions.view') ? ($stats['submissions']['live'] ?? 0) : 0;

// Draft content is the actionable number on this page: it's work that has
// been started and isn't visible on the public site yet.
$draftTotal = 0;
foreach (['programs', 'updates', 'team', 'sponsors', 'gallery', 'reports'] as $key) {
    $draftTotal += ($stats[$key]['total'] ?? 0) - ($stats[$key]['live'] ?? 0);
}

$recent = [];
if (can('activity.view')) {
    $recent = getDb()->query(
        'SELECT l.action, l.module, l.details, l.created_at, u.name AS user_name
         FROM activity_logs l
         LEFT JOIN admin_users u ON u.id = l.user_id
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT 8'
    )->fetchAll();
}

$titleCase = fn(string $value): string => ucwords(str_replace('_', ' ', $value));

/** Compact "3 hours ago" for the activity feed. */
function timeAgo(string $timestamp): string
{
    $seconds = time() - strtotime($timestamp);

    if ($seconds < 60) {
        return 'just now';
    }
    foreach ([3600 => 'minute', 86400 => 'hour', 2592000 => 'day'] as $limit => $unit) {
        if ($seconds < $limit) {
            $divisor = ['minute' => 60, 'hour' => 3600, 'day' => 86400][$unit];
            $count = (int) floor($seconds / $divisor);
            return $count . ' ' . $unit . ($count === 1 ? '' : 's') . ' ago';
        }
    }

    return date('M j, Y', strtotime($timestamp));
}

require __DIR__ . '/includes/layout-header.php';
?>
        <!-- Every other admin page opens with an <h1> in .module-header; the
             dashboard started at <h2>, so its heading outline began one level
             down and a screen reader landing here got no page title at all.
             Hidden rather than visible on purpose: the greeting below is the
             design's heading, and adding a second visible title would be a
             redesign to fix a structural problem. -->
        <h1 class="sr-only">Dashboard</h1>

        <div class="welcome-card">
          <h2>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>.</h2>
          <p>
            <?php if ($draftTotal > 0): ?>
              You have <strong><?= $draftTotal ?></strong> item<?= $draftTotal === 1 ? '' : 's' ?>
              still in draft — they won't appear on the public site until set to Active.
            <?php else: ?>
              Everything is published — no content is sitting in draft.
            <?php endif; ?>
          </p>
          <?php if ($unreadSubmissions > 0): ?>
            <p>
              <strong><?= $unreadSubmissions ?></strong>
              unread form submission<?= $unreadSubmissions === 1 ? '' : 's' ?>
              from the Contact and Volunteer pages —
              <a href="modules/submissions/index.php?status=new">read them now</a>.
            </p>
          <?php endif; ?>
        </div>

        <div class="stat-grid">
          <?php foreach ($cards as $card): ?>
            <?php $stat = $stats[$card['key']] ?? ['total' => 0, 'live' => 0]; ?>
            <a class="stat-card" href="<?= e($card['href']) ?>">
              <span class="stat-card-value"><?= $stat['total'] ?></span>
              <span class="stat-card-label"><?= e($card['label']) ?></span>
              <span class="stat-card-meta">
                <?php if ($card['key'] === 'users'): ?>
                  <?= $stat['live'] ?> active
                <?php elseif ($card['key'] === 'submissions'): ?>
                  <?= $stat['live'] ?> new &middot; <?= $stat['total'] - $stat['live'] ?> handled
                <?php else: ?>
                  <?= $stat['live'] ?> live &middot; <?= $stat['total'] - $stat['live'] ?> draft
                <?php endif; ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if (can('activity.view')): ?>
          <div class="panel">
            <div class="panel-header">
              <h2>Recent Activity</h2>
              <a href="modules/activity/index.php" class="link-edit">View all</a>
            </div>
            <?php if (!$recent): ?>
              <div class="empty-state">No activity recorded yet.</div>
            <?php else: ?>
              <ul class="activity-feed">
                <?php foreach ($recent as $entry): ?>
                  <li>
                    <span class="status-badge status-badge--<?= e($entry['action']) ?>"><?= e($titleCase($entry['action'])) ?></span>
                    <span class="activity-feed-text">
                      <strong><?= e($entry['user_name'] ?? 'Deleted user') ?></strong>
                      <?= e($titleCase($entry['module'])) ?>
                      <?php if ($entry['details'] !== null): ?>
                        — <?= e($entry['details']) ?>
                      <?php endif; ?>
                    </span>
                    <time class="activity-feed-time" datetime="<?= e($entry['created_at']) ?>"><?= e(timeAgo($entry['created_at'])) ?></time>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>
<?php require __DIR__ . '/includes/layout-footer.php'; ?>
