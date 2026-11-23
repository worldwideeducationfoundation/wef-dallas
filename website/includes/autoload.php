<?php
/**
 * PSR-4 autoloader for new namespaced code.
 *
 * Why this exists, and why it is 20 lines rather than Composer:
 *
 * The audit recommended PSR-4 autoloading. The honest position is that the
 * codebase has zero classes today — every helper is a global function, loaded
 * by require_once, and that style is consistent and unusually well documented.
 * Converting it would be weeks of churn for no behavioural gain, and pulling in
 * Composer would add a vendor/ directory, a lockfile and an update cadence to a
 * project that currently has no third-party dependencies at all. That absence
 * is a genuine security property worth keeping.
 *
 * What was actually missing was not an autoloader but the OPTION of one: there
 * was no way to write a namespaced class without also inventing the loading
 * mechanism, so nobody would. This removes that barrier and nothing else. It
 * adds no dependency, changes no existing file, and costs one require in
 * whatever wants it.
 *
 * Usage — create app/Domain/Submission.php:
 *
 *     namespace App\Domain;
 *     final class Submission { ... }
 *
 * and after requiring this file, `new App\Domain\Submission()` resolves to
 * app/Domain/Submission.php. Existing procedural helpers are untouched and
 * keep working exactly as they do now; the two styles coexist deliberately so
 * migration can be gradual, or never happen at all if it is never needed.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    // Not ours — let any other registered autoloader try.
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    // realpath + prefix check: a class name is a string, and a crafted one
    // containing traversal must never be able to require a file outside app/.
    $resolved = realpath($file);
    $root = realpath($baseDir);

    if ($resolved === false || $root === false || !str_starts_with($resolved, $root)) {
        return;
    }

    require $resolved;
});
