<?php
/**
 * Small, boring helpers used everywhere else. No framework magic.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Writes an audit row. Never throws: this is bookkeeping that runs
 * alongside real work (saves, deletes, logout), and a failure to record
 * the note must not roll back or crash the action it was describing.
 * Failures go to the error log instead so they're still visible.
 *
 * $details is truncated to the column width rather than letting MySQL
 * reject the row in strict mode.
 */
function logActivity(?int $userId, string $action, string $module = 'auth', ?string $details = null): void
{
    try {
        $stmt = getDb()->prepare(
            'INSERT INTO activity_logs (user_id, action, module, details, ip_address, user_agent)
             VALUES (:user_id, :action, :module, :details, :ip, :agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'details' => $details === null ? null : mb_substr($details, 0, 255),
            'ip' => clientIp(),
            'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        adminLog('WARN', 'activity log write failed: ' . $e->getMessage());
    }
}

/**
 * Human-readable file size. Lives here rather than in the Media module
 * that first needed it — it was declared at file scope inside
 * modules/media/index.php, which meant any second page that wanted it
 * had to either duplicate it or risk a redeclare fatal.
 */
function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

/**
 * Post/Redirect/Get flash messages: set before a redirect, read (and
 * cleared) on the very next request. $type is 'success' or 'error' —
 * matches the .alert-success / .alert-error classes already in admin.css.
 */
function flashSet(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Carries submitted form values across a redirect after a validation
 * failure, so the form can repopulate what the user typed instead of
 * making them start over. Paired with flashSet('error', ...) for the
 * message itself.
 */
function flashInputSet(array $data): void
{
    $_SESSION['flash_input'] = $data;
}

function flashInputGet(): ?array
{
    if (empty($_SESSION['flash_input'])) {
        return null;
    }
    $input = $_SESSION['flash_input'];
    unset($_SESSION['flash_input']);
    return $input;
}
