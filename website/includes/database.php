<?php
/**
 * PDO connection helper. Every query in this app goes through
 * getDb()->prepare(...) — no raw string interpolation into SQL, anywhere.
 */

$configPath = __DIR__ . '/../config/config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

function getDb(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    if (defined('DISABLE_DATABASE') && DISABLE_DATABASE) {
        $attempted = true;
        return null;
    }

    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        $attempted = true;
        return null;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
        );

        $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 1,
        ]);
    } catch (Throwable $e) {
        // When MySQL is absent or offline, fail gracefully to fallback mode
        $pdo = null;
    }

    $attempted = true;
    return $pdo;
}
