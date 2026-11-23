<?php
/**
 * Error handling for the PUBLIC site.
 *
 * The gap this closes: admin/includes/errors.php gives the CMS a proper
 * handler, but nothing equivalent existed on the visitor-facing side. Public
 * pages go straight to includes/content.php -> database.php, where getDb()
 * sets PDO::ERRMODE_EXCEPTION with nobody catching it. Observed live during
 * the Phase 1 audit: with MySQL stopped, six pages died mid-render and printed
 * a full PDOException trace to the browser -- including the DB username, the
 * host, and absolute server paths -- with the page's <body> left unclosed.
 *
 * Now: detail goes to a log Apache refuses to serve, and the visitor gets a
 * short, generic page. WEF_DEBUG (see includes/environment.php) puts detail
 * back on screen for local work. It is the configured APP_DEBUG AND-ed with
 * "is this host local", so a development config deployed to the live domain
 * still cannot leak a trace to a visitor.
 *
 * Deliberately separate from admin/includes/errors.php rather than shared:
 * the two want different log destinations and different pages, and the admin
 * one must keep winning for admin requests. This file is required only by
 * includes/content.php and includes/content-blocks.php, which no admin script
 * ever loads, so the two handlers cannot fight over the same request.
 */

// config.php may not have been loaded yet if a page requires this first.
$configPath = __DIR__ . '/../config/config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

// Resolves WEF_DEBUG — the configured flag AND-ed with "is this host local".
// Read WEF_DEBUG, never APP_DEBUG: the latter is only an input to it, and on
// its own would happily print stack traces on the live domain.
require_once __DIR__ . '/environment.php';

const PUBLIC_LOG_DIR = __DIR__ . '/../storage/logs';

/**
 * PHP adds X-Powered-By with its exact version whenever expose_php is On,
 * which hands an attacker the PHP build for free. `Header unset` in .htaccess
 * does not reliably remove it because PHP adds it per-request, so it is
 * dropped here instead — this also works on hosting where php.ini is not
 * editable. (The Server header is set by Apache itself and needs
 * `ServerTokens Prod` in httpd.conf; it cannot be removed from here.)
 */
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

ini_set('display_errors', WEF_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Pages switched off in admin -> Pages On/Off: links to them become plain
// text. Started first so it is the outermost buffer and sees the whole page.
require_once __DIR__ . '/page-visibility.php';
startHiddenPageLinkFilter();

// Buffer the page so a failure part-way through can discard what was already
// emitted and send a clean error document instead of appending a trace to a
// half-built page. PHP flushes this automatically at shutdown, so a page that
// renders normally is byte-for-byte unchanged.
if (!WEF_DEBUG) {
    ob_start();
}

/**
 * Appends a line to storage/logs/site-error.log. storage/ already carries a
 * "Require all denied" .htaccess, so the log is never downloadable. Falls back
 * to PHP's own error_log if the directory can't be created, so a logging
 * failure never becomes the thing that breaks the page.
 */
function publicLog(string $level, string $message): void
{
    $line = sprintf(
        "[%s] %s: %s | %s %s | ip=%s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        $_SERVER['REQUEST_URI'] ?? '-',
        $_SERVER['REMOTE_ADDR'] ?? '-'
    );

    if (!is_dir(PUBLIC_LOG_DIR) && !mkdir(PUBLIC_LOG_DIR, 0755, true) && !is_dir(PUBLIC_LOG_DIR)) {
        error_log('WEF site: ' . $line);
        return;
    }

    $logPath = PUBLIC_LOG_DIR . '/site-error.log';

    // Rotate at 5MB, keeping 5 generations. A public-facing log is the one
    // most likely to run away — a single broken page hit by a crawler can
    // write thousands of identical lines — and an unbounded file on shared
    // hosting eventually exhausts the disk quota.
    if (is_file($logPath) && filesize($logPath) >= 5 * 1024 * 1024) {
        $keep = 5;
        if (is_file($logPath . '.' . $keep)) {
            @unlink($logPath . '.' . $keep);
        }
        for ($i = $keep - 1; $i >= 1; $i--) {
            if (is_file($logPath . '.' . $i)) {
                @rename($logPath . '.' . $i, $logPath . '.' . ($i + 1));
            }
        }
        @rename($logPath, $logPath . '.1');
    }

    if (@file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('WEF site: ' . $line);
    }
}

/**
 * The visitor-facing failure page. Says nothing about what went wrong. Sends
 * 503 rather than 500 because the realistic cause is a database that is
 * temporarily down, and 503 is the status that tells crawlers to come back
 * later instead of dropping the URL from the index.
 *
 * Written as a complete document: the previous behaviour dumped a trace into a
 * half-rendered page, so anything emitted before the failure is discarded here
 * via the output buffer rather than left on screen.
 */
function renderPublicErrorPage(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Temporarily unavailable — Worldwide Education Foundation</title>
<style>
  body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fbfaf8;
         color: #3a3f47; display: flex; align-items: center; justify-content: center;
         min-height: 100vh; margin: 0; padding: 24px; }
  .box { background: #fff; border: 1px solid #e7e9ec; border-radius: 16px; padding: 32px;
         max-width: 460px; box-shadow: 0 8px 24px rgba(18,20,23,.06); }
  h1 { font-size: 20px; margin: 0 0 12px; color: #121417; }
  p { margin: 0 0 12px; font-size: 15px; line-height: 1.6; }
  a { color: #0e611d; font-weight: 600; }
</style></head>
<body><div class="box">
  <h1>This page is temporarily unavailable</h1>
  <p>We're having a brief technical problem and are looking into it. Please try again in a few minutes.</p>
  <p>If you need to reach us in the meantime, email
     <a href="mailto:info@wefdallas.org">info@wefdallas.org</a>.</p>
</div></body></html>
HTML;
}

set_exception_handler(static function (Throwable $e): void {
    publicLog('EXCEPTION', sprintf(
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

    renderPublicErrorPage();
});

/**
 * Warnings and notices are recorded but deliberately not promoted to
 * exceptions -- same reasoning as the admin handler. Turning a notice into a
 * fatal would make the public site stricter than the one that works today,
 * which is not an improvement.
 */
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    publicLog('PHP', sprintf('[%d] %s in %s:%d', $severity, $message, $file, $line));

    return true;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    publicLog('FATAL', sprintf('%s in %s:%d', $error['message'], $error['file'], $error['line']));

    if (!WEF_DEBUG) {
        renderPublicErrorPage();
    }
});
