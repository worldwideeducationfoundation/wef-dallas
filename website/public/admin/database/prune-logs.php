<?php
/**
 * Trims the two tables that grow without bound. CLI only.
 *
 * Why this is needed: migrate.php's own comment calls activity_logs "the one
 * table that grows without bound", and nothing was ever written to bound it.
 * Every save, delete, reorder, login and logout appends a row forever.
 * login_attempts is the same story — it is only ever read for the last 15
 * minutes, but rows from months ago are still sitting in it.
 *
 * form_submissions is the third, and the one that needed the most care. It is
 * the only table an anonymous visitor can insert into, so it grows from the
 * open internet rather than from admin activity — but unlike the other two it
 * holds business data, not logs. Deleting an enquiry because it is old would
 * destroy something a person wrote to WEF and expected an answer to.
 *
 * So it is pruned on a deliberately narrow condition: rows that an admin has
 * ALREADY moved to Trash, and only after they have sat there a while. Nothing
 * live is ever touched, whatever its age or status. Trash is a recycle bin,
 * not an archive, and this is what stops it becoming one.
 *
 * Usage, from the project root:
 *   php admin/database/prune-logs.php              # apply
 *   php admin/database/prune-logs.php --dry-run    # report only
 *   php admin/database/prune-logs.php --activity-days=365 --attempts-days=30
 *   php admin/database/prune-logs.php --trash-days=90
 *
 * Suggested cron on Hostinger (monthly is plenty):
 *   0 3 1 * * cd /home/USER/public_html && php admin/database/prune-logs.php
 *
 * Deliberately conservative defaults. activity_logs is an audit trail, so a
 * year is kept rather than a month — it is the only record of who changed what,
 * and it is cheap. Deletes are batched so a very large first run cannot hold a
 * long transaction or blow past a shared host's execution limit.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';

$dryRun = in_array('--dry-run', $argv, true);

function argValue(array $argv, string $name, int $default): int
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return max(1, (int) substr($arg, strlen($name) + 3));
        }
    }
    return $default;
}

$activityDays = argValue($argv, 'activity-days', 365);
$attemptsDays = argValue($argv, 'attempts-days', 30);
$trashDays = argValue($argv, 'trash-days', 90);
$batchSize = 5000;

/**
 * Each target names the column its age is measured from, and may add a further
 * condition. Both are literals defined here, never request input, which is what
 * keeps them safe to interpolate.
 *
 * form_submissions measures from deleted_at, not created_at: the clock that
 * matters is how long the row has been in Trash, not how old the enquiry is.
 * A message from two years ago that nobody has triaged still has to survive.
 */
$targets = [
    [
        'table' => 'activity_logs',
        'days' => $activityDays,
        'dateColumn' => 'created_at',
        'extraWhere' => '',
        'describe' => 'older than %d days',
    ],
    [
        'table' => 'login_attempts',
        'days' => $attemptsDays,
        'dateColumn' => 'created_at',
        'extraWhere' => '',
        'describe' => 'older than %d days',
    ],
    [
        'table' => 'form_submissions',
        'days' => $trashDays,
        'dateColumn' => 'deleted_at',
        // deleted_at IS NOT NULL is the whole safety guarantee: only rows an
        // admin has already chosen to delete are eligible. A live submission
        // cannot be reached by this query at any age.
        'extraWhere' => 'deleted_at IS NOT NULL',
        'describe' => 'in Trash for more than %d days',
    ],
];

$db = getDb();

foreach ($targets as $target) {
    $table = $target['table'];
    $days = $target['days'];
    $dateColumn = $target['dateColumn'];

    $where = "{$dateColumn} < (NOW() - INTERVAL :days DAY)";
    if ($target['extraWhere'] !== '') {
        $where .= ' AND ' . $target['extraWhere'];
    }

    $label = sprintf($target['describe'], $days);

    // Table and column names are literals from the array above.
    $countStmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $countStmt->execute(['days' => $days]);
    $total = (int) $countStmt->fetchColumn();

    $kept = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() - $total;

    if ($total === 0) {
        printf("  %-18s nothing %s (%d rows kept)\n", $table, $label, $kept);
        continue;
    }

    if ($dryRun) {
        printf("  %-18s WOULD DELETE %d rows %s (%d kept)\n", $table, $total, $label, $kept);
        continue;
    }

    $deleted = 0;
    do {
        $del = $db->prepare("DELETE FROM {$table} WHERE {$where} LIMIT {$batchSize}");
        $del->execute(['days' => $days]);
        $n = $del->rowCount();
        $deleted += $n;
    } while ($n === $batchSize);

    printf("  %-18s deleted %d rows %s (%d kept)\n", $table, $deleted, $label, $kept);
}

echo $dryRun ? "\nDry run — nothing was changed.\n" : "\nDone.\n";
