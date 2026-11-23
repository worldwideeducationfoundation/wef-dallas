<?php
/**
 * Restores a dump produced by backup.php. CLI only.
 *
 * A backup nobody has restored is a hypothesis, not a backup. This exists so
 * the restore path is exercised rather than discovered during an incident.
 *
 * Usage, from the project root:
 *   php admin/database/restore.php --file=wef-db-20260731-020000.sql.gz --into=wef_restore_test
 *   php admin/database/restore.php --file=... --into=wef_admin --i-understand-this-overwrites
 *
 * --into is required and has no default. Restoring into the live database is
 * possible but needs the explicit --i-understand-this-overwrites flag, because
 * the dump begins with DROP TABLE for every table: running it against the wrong
 * schema destroys it. The normal drill is to restore into a scratch database
 * and compare row counts, which is what --verify does automatically.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';

$projectRoot = dirname(__DIR__, 3);

// Must resolve the same way backup.php does, or restore would look in the
// project while production writes above the web root — the failure would only
// show up during an actual recovery, which is the worst possible moment.
$backupDir = defined('APP_BACKUP_DIR') && APP_BACKUP_DIR !== ''
    ? APP_BACKUP_DIR
    : $projectRoot . '/storage/backups';

function opt(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

$file   = opt($argv, 'file');
$into   = opt($argv, 'into');
$verify = !in_array('--no-verify', $argv, true);
$force  = in_array('--i-understand-this-overwrites', $argv, true);

if ($file === null || $into === null) {
    fwrite(STDERR, "Usage: php admin/database/restore.php --file=<dump> --into=<database>\n");
    fwrite(STDERR, "\nAvailable dumps in {$backupDir}:\n");
    foreach (glob("{$backupDir}/wef-db-*") ?: [] as $f) {
        fwrite(STDERR, sprintf("  %-40s %8.1f KB  %s\n",
            basename($f), filesize($f) / 1024, date('Y-m-d H:i', filemtime($f))));
    }
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $into)) {
    fwrite(STDERR, "Database name must be alphanumeric/underscore: {$into}\n");
    exit(1);
}

if ($into === DB_NAME && !$force) {
    fwrite(STDERR, "Refusing to overwrite the live database '{$into}'.\n");
    fwrite(STDERR, "The dump starts with DROP TABLE for every table.\n");
    fwrite(STDERR, "Restore into a scratch database first, or pass --i-understand-this-overwrites.\n");
    exit(1);
}

$path = is_file($file) ? $file : "{$backupDir}/{$file}";
if (!is_file($path)) {
    fwrite(STDERR, "No such dump: {$path}\n");
    exit(1);
}

echo "Restoring " . basename($path) . " into `{$into}`\n";

// Read the dump, transparently handling the gzip that backup.php produces.
$sql = str_ends_with($path, '.gz')
    ? (string) gzdecode((string) file_get_contents($path))
    : (string) file_get_contents($path);

if (trim($sql) === '') {
    fwrite(STDERR, "Dump is empty — refusing to proceed.\n");
    exit(1);
}

$root = new PDO(
    sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET),
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$root->exec("CREATE DATABASE IF NOT EXISTS `{$into}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$root->exec("USE `{$into}`");

// The dump is one script; hand it over whole rather than splitting on ';',
// which would break on any semicolon inside a string literal.
$root->exec($sql);

echo "  restored.\n";

if ($verify) {
    echo "\nVERIFY — row counts, restored vs live\n";
    $live = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $tables = $root->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $mismatch = 0;
    printf("  %-20s %10s %10s\n", 'TABLE', 'RESTORED', 'LIVE');
    foreach ($tables as $t) {
        $r = (int) $root->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        try {
            $l = (int) $live->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        } catch (Throwable $e) {
            $l = -1;
        }
        $flag = ($l >= 0 && $r !== $l) ? '  MISMATCH' : '';
        if ($flag !== '') { $mismatch++; }
        printf("  %-20s %10d %10s%s\n", $t, $r, $l < 0 ? 'n/a' : $l, $flag);
    }

    echo $mismatch === 0
        ? "\n  All tables match. The backup is restorable.\n"
        : "\n  {$mismatch} table(s) differ — expected if the site changed since the dump.\n";

    if ($into !== DB_NAME) {
        echo "\n  Scratch database `{$into}` left in place for inspection. Remove with:\n";
        echo "    mysql -e \"DROP DATABASE \\`{$into}\\`\"\n";
    }
}
