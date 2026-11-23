<?php
/**
 * Database + uploads backup. CLI only.
 *
 * This is the piece the project has been missing entirely: there was no dump,
 * no schedule, no offsite copy and no restore procedure. admin/uploads/ held
 * the only copy of every team photo, programme image and gallery asset an
 * admin had ever uploaded, and the database held the only copy of all the
 * content around them.
 *
 * Usage, from the project root:
 *   php admin/database/backup.php                # database + uploads
 *   php admin/database/backup.php --db-only
 *   php admin/database/backup.php --keep=14      # retention, default 30
 *   php admin/database/backup.php --out=D:/wef-backups
 *
 * Suggested cron on Hostinger (nightly, 02:30):
 *   30 2 * * * cd /home/USER/public_html && php admin/database/backup.php >> storage/logs/backup.log 2>&1
 *
 * Two dump strategies, because shared hosting is inconsistent:
 *   1. mysqldump --single-transaction, when the binary is reachable and
 *      exec() is available. Preferred: it is consistent without locking, and
 *      the output is what every restore tool expects.
 *   2. A pure-PHP dump otherwise. Slower and more memory-hungry, but it works
 *      where exec() is disabled — which is common, and is exactly the
 *      situation in which "we have a backup script" would otherwise be false.
 *
 * The dump is written to storage/backups/, which sits under storage/'s
 * "Require all denied" .htaccess, so a backup can never be downloaded over
 * HTTP. Move copies offsite; a backup on the same disk as the database is
 * protection against mistakes, not against losing the disk.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';

$projectRoot = dirname(__DIR__, 3);

/**
 * Where backups land, in order of precedence: --out=, then APP_BACKUP_DIR from
 * config, then storage/backups inside the project.
 *
 * The in-project default is safe today — storage/ carries "Require all denied"
 * and a live fetch of the .tar.gz returns 403 — but it is guarded rather than
 * unreachable. One misconfigured vhost, or a move to Nginx where .htaccess is
 * ignored outright, and a full database dump becomes a single GET away.
 *
 * APP_BACKUP_DIR exists so production can put the files somewhere the web
 * server cannot serve at all, which is a different and much stronger claim
 * than "we told it not to". On Hostinger that means a sibling of public_html:
 *
 *     define('APP_BACKUP_DIR', '/home/USER/wef-backups');
 *
 * Left undefined, behaviour is exactly as before, so nothing breaks locally.
 */
$defaultOut = defined('APP_BACKUP_DIR') && APP_BACKUP_DIR !== ''
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

$dbOnly  = in_array('--db-only', $argv, true);
$keep    = max(1, (int) opt($argv, 'keep', '30'));
$outDir  = rtrim((string) opt($argv, 'out', $defaultOut), '/\\');
$stamp   = date('Ymd-His');

if (!is_dir($outDir) && !mkdir($outDir, 0700, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create backup directory: {$outDir}\n");
    exit(1);
}

echo "WEF backup — " . date('Y-m-d H:i:s') . "\n";
echo "  target: {$outDir}\n";

/* ---------------------------------------------------------------- database */

$sqlPath = "{$outDir}/wef-db-{$stamp}.sql";
$usedMysqldump = false;

$mysqldump = null;
foreach (['C:/xampp/mysql/bin/mysqldump.exe', '/usr/bin/mysqldump', 'mysqldump'] as $candidate) {
    if ($candidate === 'mysqldump' || is_file($candidate)) {
        $mysqldump = $candidate;
        break;
    }
}

$canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);

if ($mysqldump !== null && $canExec) {
    // Credentials go in a temporary defaults file, never on the command line —
    // argv is world-readable in the process list on a shared host.
    $cnf = tempnam(sys_get_temp_dir(), 'wefcnf');
    file_put_contents($cnf, sprintf(
        "[client]\nhost=%s\nuser=%s\npassword=\"%s\"\n",
        DB_HOST, DB_USER, str_replace('"', '\"', DB_PASS)
    ));
    @chmod($cnf, 0600);

    $cmd = sprintf(
        '%s --defaults-extra-file=%s --single-transaction --quick --routines --events '
        . '--default-character-set=utf8mb4 --add-drop-table %s 2>&1',
        escapeshellarg($mysqldump),
        escapeshellarg($cnf),
        escapeshellarg(DB_NAME)
    );

    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    @unlink($cnf);

    if ($code === 0 && $out !== []) {
        file_put_contents($sqlPath, implode("\n", $out) . "\n");
        $usedMysqldump = true;
    } else {
        fwrite(STDERR, "  mysqldump failed (exit {$code}), falling back to the PHP dumper\n");
        foreach (array_slice($out, 0, 3) as $line) { fwrite(STDERR, "    {$line}\n"); }
    }
}

if (!$usedMysqldump) {
    phpDump(getDb(), $sqlPath);
}

$sqlBytes = filesize($sqlPath);
printf("  database: %s  (%s, %s)\n", basename($sqlPath), humanBytes($sqlBytes),
    $usedMysqldump ? 'mysqldump' : 'PHP dumper');

// Compress if we can — these dumps are almost entirely text.
if (function_exists('gzopen')) {
    $gz = gzopen($sqlPath . '.gz', 'wb9');
    $in = fopen($sqlPath, 'rb');
    while (!feof($in)) { gzwrite($gz, (string) fread($in, 262144)); }
    fclose($in);
    gzclose($gz);
    if (filesize($sqlPath . '.gz') > 0) {
        unlink($sqlPath);
        $sqlPath .= '.gz';
        printf("  compressed: %s  (%s)\n", basename($sqlPath), humanBytes(filesize($sqlPath)));
    }
}

/* ----------------------------------------------------------------- uploads */

if (!$dbOnly) {
    $uploads = $projectRoot . '/admin/uploads';
    $zipPath = "{$outDir}/wef-uploads-{$stamp}.zip";

    if (!is_dir($uploads)) {
        echo "  uploads:  (no admin/uploads directory)\n";
    } else {
        // Three strategies, because the uploads are the irreplaceable half of
        // this backup and "the zip extension isn't installed" must not be the
        // reason they go unprotected. XAMPP here has no ext-zip at all, which
        // is exactly the case that would have silently skipped them.
        //
        //   1. ZipArchive          — most portable to open, when ext-zip exists.
        //   2. PharData (.tar.gz)  — ships with core PHP. Note this is NOT
        //                            blocked by phar.readonly: that setting
        //                            only restricts executable .phar files,
        //                            not tar/zip data archives.
        //   3. plain recursive copy — no compression, but a real second copy.
        $archived = false;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $n = 0;
                foreach (uploadFiles($uploads) as $abs => $rel) {
                    $zip->addFile($abs, $rel);
                    $n++;
                }
                $zip->close();
                printf("  uploads:  %s  (%d files, %s)\n", basename($zipPath), $n, humanBytes(filesize($zipPath)));
                $archived = true;
            }
        }

        if (!$archived && class_exists('PharData')) {
            $tarPath = "{$outDir}/wef-uploads-{$stamp}.tar";
            try {
                @unlink($tarPath);
                @unlink($tarPath . '.gz');
                $tar = new PharData($tarPath);
                $n = 0;
                foreach (uploadFiles($uploads) as $abs => $rel) {
                    $tar->addFile($abs, $rel);
                    $n++;
                }
                if ($n > 0) {
                    $tar->compress(Phar::GZ);

                    // Both the original and the object returned by compress()
                    // keep the .tar mapped, so it cannot be deleted until every
                    // handle is released. Without this the backup directory
                    // accumulates an uncompressed copy alongside every archive
                    // — roughly doubling what it costs to keep 30 days.
                    unset($tar);
                    gc_collect_cycles();
                    if (!@unlink($tarPath) && class_exists('Phar')) {
                        Phar::unlinkArchive($tarPath);
                    }

                    printf("  uploads:  %s  (%d files, %s)\n",
                        basename($tarPath . '.gz'), $n, humanBytes((int) @filesize($tarPath . '.gz')));
                    $archived = true;
                }
            } catch (Throwable $e) {
                fwrite(STDERR, "  PharData archive failed: " . $e->getMessage() . "\n");
            }
        }

        if (!$archived) {
            $copyDir = "{$outDir}/wef-uploads-{$stamp}";
            $n = 0;
            foreach (uploadFiles($uploads) as $abs => $rel) {
                $dest = "{$copyDir}/{$rel}";
                $destDir = dirname($dest);
                if (!is_dir($destDir)) { mkdir($destDir, 0700, true); }
                if (copy($abs, $dest)) { $n++; }
            }
            printf("  uploads:  %s/  (%d files, uncompressed copy — no zip or phar available)\n",
                basename($copyDir), $n);
        }
    }
}

/* --------------------------------------------------------------- retention */

$removed = 0;
$cutoff = time() - ($keep * 86400);
foreach (glob("{$outDir}/wef-*") ?: [] as $old) {
    if (filemtime($old) < $cutoff) { unlink($old); $removed++; }
}
printf("  retention: keeping %d days, removed %d expired file(s)\n", $keep, $removed);

echo "\nDone. Verify a restore periodically — an untested backup is a hypothesis.\n";
echo "  php admin/database/restore.php --file=" . basename($sqlPath) . " --into=wef_restore_test\n";

/* ------------------------------------------------------------------ helpers */

/**
 * Every real file under admin/uploads/, as absolutePath => pathRelativeToUploads.
 * One traversal shared by all three archive strategies, so they can never
 * disagree about what is included.
 *
 * @return array<string, string>
 */
function uploadFiles(string $uploads): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) { continue; }
        // .htaccess is what stops uploads executing as PHP — it belongs in the
        // archive, or a restore would come back missing its own protection.
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($uploads) + 1));
        $out[$file->getPathname()] = $rel;
    }
    return $out;
}

function humanBytes(int $b): string
{
    if ($b >= 1048576) { return round($b / 1048576, 1) . ' MB'; }
    if ($b >= 1024)    { return round($b / 1024, 1) . ' KB'; }
    return $b . ' B';
}

/**
 * Portable dump: schema via SHOW CREATE TABLE, data as batched INSERTs.
 * Wrapped in a transaction with consistent snapshot so the output matches a
 * single point in time even while the site is being used.
 */
function phpDump(PDO $db, string $path): void
{
    $fh = fopen($path, 'wb');
    if ($fh === false) {
        fwrite(STDERR, "Cannot write {$path}\n");
        exit(1);
    }

    fwrite($fh, "-- WEF database dump (PHP dumper)\n-- " . date('c') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();

    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $create = $db->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($fh, "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n\n");

        $stmt = $db->query("SELECT * FROM `{$t}`");
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // quote() through the live connection, so escaping matches the
            // server's charset rather than being hand-rolled.
            $vals = array_map(
                static fn($v) => $v === null ? 'NULL' : $db->quote((string) $v),
                array_values($row)
            );
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) {
                fwrite($fh, "INSERT INTO `{$t}` VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch !== []) {
            fwrite($fh, "INSERT INTO `{$t}` VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($fh, "\n");
    }

    $db->commit();
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}
