<?php
/**
 * Error and exception bootstrap for the admin app.
 *
 * The problem this solves: getDb() sets PDO::ERRMODE_EXCEPTION but nothing
 * ever caught those exceptions, so any DB hiccup printed a full stack
 * trace — including the config path and query fragments — straight to the
 * browser. Here, detail always goes to a log file that Apache refuses to
 * serve, and the visitor always gets a short, non-specific message.
 *
 * WEF_DEBUG (includes/environment.php) flips detail back on-screen for local
 * work. It is the configured APP_DEBUG AND-ed with "is this host local", so
 * neither an older config.php nor a development config deployed to
 * production can put a trace on screen.
 */

// Resolves WEF_DEBUG — the configured APP_DEBUG AND-ed with "is this host
// local". Read WEF_DEBUG, never APP_DEBUG: a development config that reaches
// the live domain must not be able to turn stack traces back on for visitors.
require_once __DIR__ . '/../../../includes/environment.php';

const ADMIN_LOG_DIR = __DIR__ . '/../logs';

/**
 * Drop PHP's X-Powered-By version banner. Same reasoning as the public
 * bootstrap: `Header unset` in .htaccess does not reliably catch a header PHP
 * adds per-request, and this works on hosting where php.ini is off limits.
 */
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

ini_set('display_errors', WEF_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * Size cap and generation count for log rotation. Without this the log grew
 * without bound: on shared hosting it eventually hits the disk or inode quota,
 * file_put_contents starts failing, and diagnostics disappear at exactly the
 * moment something is going wrong.
 */
const ADMIN_LOG_MAX_BYTES = 5 * 1024 * 1024; // 5MB
const ADMIN_LOG_KEEP = 5;                    // .1 .. .5, oldest discarded

/**
 * Rotates $path when it exceeds the cap: .4 -> .5, .3 -> .4, ... , current -> .1.
 * Shared by both tiers' loggers via their own thin wrappers.
 */
function rotateLogFile(string $path, int $maxBytes, int $keep): void
{
    if (!is_file($path) || filesize($path) < $maxBytes) {
        return;
    }

    // Drop the oldest generation, then shift each one down a slot.
    $oldest = $path . '.' . $keep;
    if (is_file($oldest)) {
        @unlink($oldest);
    }
    for ($i = $keep - 1; $i >= 1; $i--) {
        if (is_file($path . '.' . $i)) {
            @rename($path . '.' . $i, $path . '.' . ($i + 1));
        }
    }
    @rename($path, $path . '.1');
}

/**
 * Appends a line to admin/logs/admin-error.log, rotating first if the file has
 * reached the cap. Falls back to PHP's own error_log if the directory can't be
 * created, so a logging failure never becomes the thing that breaks the request.
 */
function adminLog(string $level, string $message): void
{
    $line = sprintf(
        "[%s] %s: %s | %s %s | user=%s ip=%s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        $_SERVER['REQUEST_URI'] ?? '-',
        $_SESSION['user_id'] ?? '-',
        $_SERVER['REMOTE_ADDR'] ?? '-'
    );

    if (!is_dir(ADMIN_LOG_DIR) && !mkdir(ADMIN_LOG_DIR, 0755, true) && !is_dir(ADMIN_LOG_DIR)) {
        error_log('WEF admin: ' . $line);
        return;
    }

    $logPath = ADMIN_LOG_DIR . '/admin-error.log';
    rotateLogFile($logPath, ADMIN_LOG_MAX_BYTES, ADMIN_LOG_KEEP);

    if (@file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('WEF admin: ' . $line);
    }
}

/**
 * The generic failure page. Deliberately says nothing about what went
 * wrong — the reference is the timestamp, which an admin can match
 * against the log file.
 */
function renderFatalPage(): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $reference = date('Ymd-His');

    // "Go back" was a javascript: link, which the admin Content-Security-Policy
    // no longer runs. The dashboard is a safe place to land from any failure;
    // REQUEST_URI is the visitor-facing path, so this also holds when the
    // site is forwarded into public/ by the .htaccess above it.
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $adminAt = strpos($requestPath, '/admin/');
    $dashboardUrl = htmlspecialchars($adminAt === false ? 'dashboard.php' : substr($requestPath, 0, $adminAt) . '/admin/dashboard.php', ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something went wrong — WEF Admin</title>
<style>
  body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fafafa;
         color: #3a3f47; display: flex; align-items: center; justify-content: center;
         min-height: 100vh; margin: 0; padding: 24px; }
  .box { background: #fff; border: 1px solid #e7e9ec; border-radius: 16px; padding: 32px;
         max-width: 460px; box-shadow: 0 8px 24px rgba(18,20,23,.06); }
  h1 { font-size: 20px; margin: 0 0 12px; color: #121417; }
  p { margin: 0 0 12px; font-size: 14px; line-height: 1.6; }
  code { background: #f4f5f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  a { color: #0e611d; font-weight: 600; }
</style></head>
<body><div class="box">
  <h1>Something went wrong</h1>
  <p>The action couldn't be completed. Nothing was changed. Please try again.</p>
  <p>If it keeps happening, quote reference <code>{$reference}</code> to your administrator.</p>
  <p><a href="{$dashboardUrl}">&larr; Back to the dashboard</a></p>
</div></body></html>
HTML;
}

set_exception_handler(static function (Throwable $e): void {
    adminLog('EXCEPTION', sprintf(
        '%s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if (WEF_DEBUG) {
        http_response_code(500);
        echo '<pre style="padding:16px;font:13px/1.5 monospace;white-space:pre-wrap">';
        echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
        return;
    }

    renderFatalPage();
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Respects @-suppression and the current error_reporting mask.
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Warnings and notices are recorded but deliberately do NOT become
    // exceptions. Promoting them would turn conditions this app already
    // handles (a stray setcookie() after output, a finfo hiccup) into
    // fatal errors — a stricter app than the one that works today, which
    // is not an improvement. Only errors PHP itself treats as fatal-ish
    // are escalated so they reach the exception handler.
    if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    adminLog('PHP', sprintf('[%d] %s in %s:%d', $severity, $message, $file, $line));

    return true;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    adminLog('FATAL', sprintf('%s in %s:%d', $error['message'], $error['file'], $error['line']));

    if (!WEF_DEBUG) {
        renderFatalPage();
    }
});
