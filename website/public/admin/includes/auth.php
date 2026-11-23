<?php
/**
 * Session bootstrap + authentication. Rate limiting is keyed on the
 * submitted email (via login_attempts), not the admin_users row, so a
 * login attempt against an email that doesn't exist is throttled too —
 * otherwise the login form's timing/behavior would leak which emails
 * are registered.
 */

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/functions.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;
// Per-IP ceiling, counted across all emails that address has tried. Higher
// than the per-email limit on purpose — see isIpRateLimited().
const MAX_LOGIN_ATTEMPTS_PER_IP = 20;
// Failing against this many distinct accounts from one address inside the
// window is the shape of password spraying, not of a forgotten password.
const IP_SPRAY_EMAIL_THRESHOLD = 5;

// Minimum password length, shared by the Users module and
// admin/database/create-admin.php so the two can never disagree. Length is the
// only requirement: NIST SP 800-63B advises against composition rules
// (forced symbols/digits), which push people toward predictable substitutions,
// and toward a longer floor plus a check against known-common passwords.
const PASSWORD_MIN_LENGTH = 12;

/**
 * Exact-match deny list of passwords that clear the length floor but are still
 * among the first things any credential-stuffing list tries. Kept short and
 * inline on purpose: a full breach corpus belongs behind an API (e.g. the
 * Pwned Passwords k-anonymity range endpoint), which would mean an outbound
 * HTTPS call from the login path and is a deliberate later decision, not
 * something to slip in here.
 */
const PASSWORD_DENY_LIST = [
    'password1234', 'passw0rd1234', 'administrator', 'iloveyou1234',
    'qwertyuiop12', 'letmein12345', 'welcome12345', 'trustno1234',
    'monkey123456', 'football1234', 'baseball1234', 'dragon123456',
    'sunshine1234', 'princess1234', 'starwars1234', 'whatever1234',
    '123456789012', '111111111111', '000000000000', 'abcdefghijkl',
    'worldwideeducation', 'wefdallas1234', 'wefadmin1234',
];

// Session lifetime. The cookie itself is still a session cookie
// (lifetime => 0), but expiry is enforced server-side too so a stolen or
// left-open session doesn't stay valid indefinitely.
const SESSION_IDLE_SECONDS = 3600;        // 1 hour without a request
const SESSION_ABSOLUTE_SECONDS = 43200;   // 12 hours since sign-in, regardless of activity
// How often a live session re-reads its own role/status from the DB.
// Without this, disabling or demoting a user has no effect until they
// choose to log out — the session would keep serving the role it was
// created with.
const SESSION_REVALIDATE_SECONDS = 300;

/**
 * Capability model. Roles map to capabilities, and every entry point
 * checks a capability rather than naming roles inline — so changing what
 * an "editor" may do is a one-line edit here, not a hunt through 30 files.
 *
 * super_admin keeps the wildcard, which is why adding this changes
 * nothing for the accounts that exist today (both are super_admin).
 */
const ROLE_CAPABILITIES = [
    'super_admin' => ['*'],
    'administrator' => [
        'content.view', 'content.create', 'content.edit', 'content.delete',
        'media.view', 'media.upload', 'media.delete',
        'settings.manage', 'activity.view',
        'submissions.view', 'submissions.manage',
        'newsletter.manage',
    ],
    // Deliberately no submissions capability. Form submissions are not site
    // content: they are names, email addresses and messages belonging to
    // members of the public who wrote to WEF, not to anyone who happens to be
    // able to edit a programme page. Reading them is a separate decision from
    // publishing, so it is a separate capability rather than a side effect of
    // content.view — grant it by making someone an administrator.
    'editor' => [
        'content.view', 'content.create', 'content.edit',
        'media.view', 'media.upload',
    ],
];

/**
 * Every capability the app is allowed to ask about.
 *
 * Why this exists: `users.manage` is enforced by all four files in
 * modules/users/, but appeared in no role's list — it worked only because
 * super_admin holds the '*' wildcard. That meant the capability set was open,
 * and a typo like requireCapability('user.manage') would silently still pass
 * for super_admin while denying everyone else, with no error raised anywhere.
 * The failure was invisible in exactly the case that matters, because the
 * person testing it is usually the super admin.
 *
 * 'users.manage' is now listed here and granted explicitly below, so the
 * intent is written down rather than inferred from a wildcard.
 */
const ALL_CAPABILITIES = [
    'content.view', 'content.create', 'content.edit', 'content.delete',
    // media.upload - add files in the Media Manager and replace a file in
    // place (every page using it shows the new one). An edit, so editors
    // hold it; deleting stays with media.delete.
    'media.view', 'media.upload', 'media.delete',
    'settings.manage', 'activity.view', 'users.manage',
    // newsletter.manage — connect, disconnect and re-point the Constant
    // Contact integration. Separate from settings.manage because it is not a
    // site setting: it authorises this website to write into WEF's mailing
    // list, and whoever holds it can redirect every future subscriber to a
    // list of their choosing. Granted to administrators, withheld from editors
    // for the same reason submissions are.
    'newsletter.manage',
    // submissions.view  — open the Form Submissions list and read a message.
    // submissions.manage — change a status, save a note, move one to Trash.
    // Split because "read what the public sent us" and "act on it on WEF's
    // behalf" are different levels of trust, and the read half is the one
    // worth being able to grant on its own.
    'submissions.view', 'submissions.manage',
];

const ROLE_LABELS = [
    'super_admin' => 'Super Admin',
    'administrator' => 'Administrator',
    'editor' => 'Editor',
];

/**
 * True when the request actually arrived over TLS.
 *
 * Checks the forwarded header as well as $_SERVER['HTTPS'] because shared
 * hosts like Hostinger commonly terminate TLS at a proxy and hand the request
 * to PHP over plain HTTP — in that setup $_SERVER['HTTPS'] is unset even
 * though the browser connection is encrypted, and trusting it alone would
 * leave the session cookie without its Secure flag on a live HTTPS site.
 */
function requestIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }

    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function initSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Secure is now derived from the request rather than read from config
    // alone. Previously it was driven only by APP_FORCE_HTTPS_COOKIE, which is
    // false in the config that ships — so if that value were not flipped by
    // hand during the Hostinger deploy, the admin session cookie would have
    // travelled over plaintext HTTP with no warning anywhere. The constant is
    // still honoured, but only to force the flag ON; it can no longer be the
    // reason it is off.
    $secure = requestIsHttps() || (defined('APP_FORCE_HTTPS_COOKIE') && APP_FORCE_HTTPS_COOKIE);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => $secure,
    ]);

    // Session ids come from the id itself, never from a URL, so a copied link
    // can never carry a session. Off by default in modern PHP, set explicitly
    // so a stray php.ini cannot re-enable it.
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Reject a client-supplied id that the server never issued.
    ini_set('session.use_strict_mode', '1');

    session_start();
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

/**
 * Relative path back to admin/ from whatever script is running, so
 * redirects issued from shared code (requireLogin, requireCapability)
 * resolve correctly whether the caller is admin/dashboard.php or
 * admin/modules/programs/save.php. Previously requireLogin() always
 * redirected to a bare 'login.php', which from a module page resolved to
 * admin/modules/programs/login.php — a 404.
 */
function adminBase(): string
{
    static $base = null;

    if ($base === null) {
        $adminRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
        $scriptPath = (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $scriptDir = $scriptPath !== '' ? str_replace('\\', '/', dirname($scriptPath)) : $adminRoot;

        $depth = 0;
        if ($adminRoot !== '' && $scriptDir !== $adminRoot && str_starts_with($scriptDir . '/', $adminRoot . '/')) {
            $relative = trim(substr($scriptDir, strlen($adminRoot)), '/');
            $depth = $relative === '' ? 0 : substr_count($relative, '/') + 1;
        }

        $base = str_repeat('../', $depth);
    }

    return $base;
}

/**
 * Enforces idle + absolute session expiry and keeps the session's cached
 * role/status honest. Returns a reason string when the session was ended,
 * null when it's still good.
 */
function enforceSessionLifetime(): ?string
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $now = time();

    if (isset($_SESSION['auth_started_at']) && $now - (int) $_SESSION['auth_started_at'] > SESSION_ABSOLUTE_SECONDS) {
        logoutUser();
        return 'expired';
    }

    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > SESSION_IDLE_SECONDS) {
        logoutUser();
        return 'idle';
    }

    $_SESSION['last_activity'] = $now;

    $checkedAt = (int) ($_SESSION['revalidated_at'] ?? 0);
    if ($now - $checkedAt >= SESSION_REVALIDATE_SECONDS) {
        $stmt = getDb()->prepare('SELECT name, email, role, status FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $fresh = $stmt->fetch();

        if (!$fresh) {
            clearSession();
            return 'removed';
        }

        if ($fresh['status'] !== 'active') {
            logoutUser();
            return 'disabled';
        }

        // Pick up name/email/role edits made by another admin mid-session.
        $_SESSION['user_name'] = $fresh['name'];
        $_SESSION['user_email'] = $fresh['email'];
        $_SESSION['user_role'] = $fresh['role'];
        $_SESSION['revalidated_at'] = $now;
    }

    return null;
}

function requireLogin(): void
{
    $endedReason = enforceSessionLifetime();

    if ($endedReason !== null || currentUser() === null) {
        redirect(adminBase() . 'login.php' . ($endedReason !== null ? '?reason=' . $endedReason : ''));
    }
}

/**
 * @return list<string>
 */
function roleCapabilities(?string $role): array
{
    return ROLE_CAPABILITIES[$role ?? ''] ?? [];
}

/**
 * Capability check for the signed-in user. Used both to guard entry
 * points (via requireCapability) and to hide UI the user can't act on —
 * one source of truth for both, so a hidden button and a blocked request
 * can never disagree.
 */
function can(string $capability): bool
{
    // A capability that is not in ALL_CAPABILITIES is a typo, not a denial.
    // Loud in development, and recorded rather than fatal in production —
    // throwing here would turn a mistyped guard into a white screen on a live
    // page, which is a worse outcome than the over-permissive check it
    // replaces. Either way it stops being invisible.
    if (!in_array($capability, ALL_CAPABILITIES, true)) {
        if (defined('WEF_DEBUG') && WEF_DEBUG) {
            throw new InvalidArgumentException(
                "Unknown capability '{$capability}'. Add it to ALL_CAPABILITIES in admin/includes/auth.php."
            );
        }
        adminLog('WARN', "unknown capability checked: '{$capability}' — denying");
        return false;
    }

    $user = currentUser();
    if ($user === null) {
        return false;
    }

    $capabilities = roleCapabilities($user['role']);

    return in_array('*', $capabilities, true) || in_array($capability, $capabilities, true);
}

/**
 * Guards an entry point. $redirectTo is relative to the calling script —
 * defaults to the module's own index.php, which is right for save/delete
 * handlers (the user may view the list, just not perform this action).
 * Pages the user may not view at all should pass a path to the dashboard.
 */
function requireCapability(string $capability, string $redirectTo = 'index.php'): void
{
    requireLogin();

    if (!can($capability)) {
        flashSet('error', 'You do not have permission to do that.');
        redirect($redirectTo);
    }
}

function isRateLimited(string $email): bool
{
    $stmt = getDb()->prepare(
        'SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE email = :email AND success = 0
           AND created_at > (NOW() - INTERVAL :minutes MINUTE)'
    );
    $stmt->execute(['email' => $email, 'minutes' => LOGIN_LOCKOUT_MINUTES]);
    $row = $stmt->fetch();

    return (int) $row['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Per-IP throttle, counted across every email that address has tried.
 *
 * The email-keyed limit above stops someone hammering one account, but on its
 * own it left password spraying completely unthrottled: an attacker could try
 * one common password against an unlimited number of email addresses, or walk
 * a password list across several accounts, and never trip a limit. The
 * login_attempts table already recorded ip_address on every attempt and
 * idx_login_attempts_ip_time was created for exactly this query — the index
 * existed, but nothing read it.
 *
 * The ceiling is deliberately higher than the per-email one. A whole office
 * behind one NAT address shares an IP here, so this has to be loose enough
 * that a few colleagues mistyping passwords is not an outage, while still
 * being far below the volume a spraying attempt needs.
 */
function isIpRateLimited(): bool
{
    $stmt = getDb()->prepare(
        'SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE ip_address = :ip AND success = 0
           AND created_at > (NOW() - INTERVAL :minutes MINUTE)'
    );
    $stmt->execute(['ip' => clientIp(), 'minutes' => LOGIN_LOCKOUT_MINUTES]);
    $row = $stmt->fetch();

    return (int) $row['attempts'] >= MAX_LOGIN_ATTEMPTS_PER_IP;
}

/**
 * Distinct email addresses this IP has failed against inside the window. A
 * single address failing repeatedly is someone who forgot their password;
 * one address failing against many accounts is spraying, and worth a log line
 * even before the ceiling is reached.
 */
function ipDistinctEmailFailures(): int
{
    $stmt = getDb()->prepare(
        'SELECT COUNT(DISTINCT email) FROM login_attempts
         WHERE ip_address = :ip AND success = 0
           AND created_at > (NOW() - INTERVAL :minutes MINUTE)'
    );
    $stmt->execute(['ip' => clientIp(), 'minutes' => LOGIN_LOCKOUT_MINUTES]);

    return (int) $stmt->fetchColumn();
}

function recordLoginAttempt(string $email, bool $success): void
{
    $stmt = getDb()->prepare(
        'INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, :success)'
    );
    $stmt->execute([
        'email' => $email,
        'ip' => clientIp(),
        'success' => $success ? 1 : 0,
    ]);
}

/**
 * @return array{ok: bool, error: ?string}
 */
function attemptLogin(string $email, string $password): array
{
    $email = trim(strtolower($email));
    $genericError = 'Invalid email or password.';

    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => $genericError];
    }

    // Both ceilings are checked before the password is verified, and both
    // return the same wording — a distinct "your IP is blocked" message would
    // tell an attacker which limit they hit and how to pace around it.
    if (isRateLimited($email) || isIpRateLimited()) {
        adminLog('WARN', sprintf(
            'login throttled: email=%s ip=%s distinct_emails_tried=%d',
            $email,
            clientIp(),
            ipDistinctEmailFailures()
        ));

        return [
            'ok' => false,
            'error' => "Too many failed attempts. Try again in a few minutes.",
        ];
    }

    $stmt = getDb()->prepare(
        'SELECT id, name, email, password_hash, role, status FROM admin_users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // Always run password_verify(), even for an unknown email, against a
    // throwaway hash at the same cost as real ones. Skipping it made a miss
    // return measurably faster than a wrong password, which let anyone probe
    // which email addresses have admin accounts.
    $passwordOk = password_verify(
        $password,
        $user['password_hash'] ?? '$2y$10$Vh6ptC4AAwq4tKOdyGovWewG7Dd2aEGurDhizI.fQEV3GLp6UYh06'
    );

    if (!$user || $user['status'] !== 'active' || !$passwordOk) {
        recordLoginAttempt($email, false);

        // Surfaces a spray in progress while it is still under the ceiling.
        // Nothing here changes what the visitor is told.
        $distinct = ipDistinctEmailFailures();
        if ($distinct >= IP_SPRAY_EMAIL_THRESHOLD) {
            adminLog('WARN', sprintf(
                'possible password spraying: ip=%s failed against %d distinct emails in %d min',
                clientIp(),
                $distinct,
                LOGIN_LOCKOUT_MINUTES
            ));
        }

        return ['ok' => false, 'error' => $genericError];
    }

    recordLoginAttempt($email, true);

    // Keep the stored hash current with PHP's default algorithm and cost. A
    // hash created years ago otherwise stays at whatever cost was current
    // then, forever — this is the one moment the plaintext is available to
    // upgrade it, and it costs one UPDATE on an already-successful login.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = getDb()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
        $rehash->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
        adminLog('INFO', 'password hash upgraded for user id ' . (int) $user['id']);
    }

    $upd = getDb()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $upd->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['auth_started_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['revalidated_at'] = time();

    logActivity((int) $user['id'], 'login', 'auth');

    return ['ok' => true, 'error' => null];
}

/**
 * The password rules, in one place. Returns an error string, or null when the
 * password is acceptable.
 *
 * Previously the only rule was `strlen($password) < 8`, duplicated between
 * modules/users/save.php and database/create-admin.php. Two problems with
 * that: `strlen` counts bytes, so a passphrase with any accented or non-Latin
 * character was measured longer than it really is and could pass at fewer than
 * 8 actual characters; and "password" / "12345678" were both accepted for an
 * account with full CMS control.
 *
 * Deliberately not doing composition rules (must contain a symbol, a digit, a
 * capital). They measurably push people toward P@ssw0rd1-shaped passwords
 * without adding real entropy, and NIST SP 800-63B recommends against them.
 * Length plus a known-bad list is the better trade.
 */
function passwordPolicyError(string $password, string $email = '', string $name = ''): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }

    // Long but trivially guessable strings still need rejecting.
    $normalised = strtolower(trim($password));

    if (preg_match('/^(.)\1+$/u', $normalised)) {
        return 'Password cannot be the same character repeated.';
    }

    if (str_contains($normalised, 'password') || str_contains($normalised, 'qwerty')) {
        return 'Password must not contain "password" or "qwerty".';
    }

    // Sequential runs, e.g. 123456789012 or abcdefghijkl.
    if (preg_match('/(?:0123456789|abcdefghij|1234567890)/', $normalised)) {
        return 'Password must not be a sequence of consecutive characters.';
    }

    foreach (PASSWORD_DENY_LIST as $bad) {
        if ($normalised === $bad) {
            return 'That password is too common. Choose something less predictable.';
        }
    }

    // A password containing the account's own email local-part or name is
    // guessable by anyone who knows who the account belongs to.
    $localPart = strtolower(trim(explode('@', $email)[0] ?? ''));
    if ($localPart !== '' && mb_strlen($localPart) >= 4 && str_contains($normalised, $localPart)) {
        return 'Password must not contain your email address.';
    }

    foreach (preg_split('/\s+/', strtolower(trim($name))) ?: [] as $part) {
        if (mb_strlen($part) >= 4 && str_contains($normalised, $part)) {
            return 'Password must not contain your name.';
        }
    }

    return null;
}

function logoutUser(): void
{
    $user = currentUser();
    if ($user) {
        logActivity($user['id'], 'logout', 'auth');
    }

    clearSession();
}

/**
 * Tears down the session without writing an activity row. Split out from
 * logoutUser() for the case where the signed-in account no longer exists
 * — activity_logs.user_id is a FK to admin_users, so logging a "logout"
 * for a deleted user violates it and would take down the request with a
 * fatal error instead of redirecting to the login page.
 */
function clearSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
