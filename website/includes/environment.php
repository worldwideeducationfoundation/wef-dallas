<?php
/**
 * Decides whether this request is running in a development environment, and
 * uses that to make APP_DEBUG fail safe.
 *
 * The problem: APP_DEBUG lives in config/config.php, which is gitignored and
 * copied by hand onto each machine. The copy that exists has APP_DEBUG = true.
 * Nothing checked whether that was appropriate for the host it was running on,
 * so a deploy that carried the development config to Hostinger would print
 * full stack traces — file paths, query fragments, the DB username — to any
 * visitor who managed to trigger an error. The safety of the live site
 * depended on somebody remembering to edit one line.
 *
 * Now APP_DEBUG can only be true when the request is also demonstrably local.
 * Setting it in config has no effect in production; forgetting to unset it is
 * no longer a disclosure. Local development is unaffected.
 *
 * Include this BEFORE anything reads APP_DEBUG.
 */

/**
 * Local development hosts. Anything not on this list is treated as production,
 * so the default for an unrecognised host is the safe one.
 */
const DEV_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

function isDevEnvironment(): bool
{
    // CLI (migrations, create-admin, prune-logs) is always treated as trusted:
    // it is only reachable by someone with shell access on the box.
    if (PHP_SAPI === 'cli') {
        return true;
    }

    // The Host header alone is not proof: the client sends it, so a request to
    // the live server with "Host: localhost" would otherwise switch on
    // on-screen errors wherever the site is the default virtual host. The
    // connection itself must also come from this machine.
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
        return false;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    // Strip a port, so localhost:8000 matches localhost.
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    if (in_array($host, DEV_HOSTS, true)) {
        return true;
    }

    // *.local and *.test are the conventional development TLDs.
    if (preg_match('/\.(local|test|localhost)$/', $host)) {
        return true;
    }

    return false;
}

/**
 * WEF_DEBUG is the effective debug flag and the only one any code should read.
 *
 * Accepts either config spelling so an existing config.php keeps working:
 *   APP_DEBUG_CONFIGURED  — the current name
 *   APP_DEBUG             — the legacy name, still honoured
 *
 * Whichever is found, the host gate is applied on top. The consequence worth
 * stating plainly: on the live domain WEF_DEBUG is false no matter what the
 * config says.
 */
if (!defined('WEF_DEBUG')) {
    $wefConfiguredDebug = false;

    if (defined('APP_DEBUG_CONFIGURED')) {
        $wefConfiguredDebug = (bool) APP_DEBUG_CONFIGURED;
    } elseif (defined('APP_DEBUG')) {
        $wefConfiguredDebug = (bool) APP_DEBUG;
    }

    define('WEF_DEBUG', $wefConfiguredDebug && isDevEnvironment());
    unset($wefConfiguredDebug);
}
