<?php
/**
 * Creates an admin account from the command line.
 *
 * Replaces the old admin/setup-first-user.php, which had to be edited to
 * hold a plaintext password, uploaded to the live server, visited in a
 * browser, and then remembered-to-be-deleted. This lives in admin/database/
 * instead — a directory Apache already refuses to serve (see the .htaccess
 * beside this file), so there is no web-reachable account-creation
 * endpoint on the server at any point, and nothing to clean up afterwards.
 *
 * Usage, from the project root:
 *   php admin/database/create-admin.php "Full Name" email@example.org [role]
 *
 * role defaults to super_admin and must be one of the roles in
 * ROLE_CAPABILITIES (see admin/includes/auth.php).
 *
 * The password is prompted for rather than passed as an argument, so it
 * never lands in shell history or the process list.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$name = trim((string) ($argv[1] ?? ''));
$email = strtolower(trim((string) ($argv[2] ?? '')));
$role = (string) ($argv[3] ?? 'super_admin');

if ($name === '' || $email === '') {
    fwrite(STDERR, "Usage: php admin/database/create-admin.php \"Full Name\" email@example.org [role]\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Not a valid email address: {$email}\n");
    exit(1);
}
if (!isset(ROLE_CAPABILITIES[$role])) {
    fwrite(STDERR, "Unknown role '{$role}'. Valid roles: " . implode(', ', array_keys(ROLE_CAPABILITIES)) . "\n");
    exit(1);
}

$exists = getDb()->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
$exists->execute(['email' => $email]);
if ($exists->fetch()) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}

// Hiding the input relies on stty, which does not exist on Windows. The
// 2>/dev/null there meant the failure was silent, so on the XAMPP development
// machine the password was typed in full view and left in the scrollback while
// the prompt claimed it was hidden. Now it says which one you are getting.
$canHideInput = PHP_OS_FAMILY !== 'Windows';

if ($canHideInput) {
    echo "Password for {$email} (min " . PASSWORD_MIN_LENGTH . " characters, input hidden): ";
    shell_exec('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    shell_exec('stty echo 2>/dev/null');
    echo "\n";
} else {
    fwrite(STDERR, "WARNING: this platform cannot hide terminal input — the password\n");
    fwrite(STDERR, "         you type will be visible and will stay in the scrollback.\n");
    fwrite(STDERR, "         Clear the terminal afterwards, or run this on the server.\n\n");
    echo "Password for {$email} (min " . PASSWORD_MIN_LENGTH . " characters): ";
    $password = trim((string) fgets(STDIN));
}

// Same rules the Users module enforces — one shared function, so the CLI and
// the web form cannot drift apart.
$policyError = passwordPolicyError($password, $email, $name);
if ($policyError !== null) {
    fwrite(STDERR, $policyError . "\n");
    exit(1);
}

$stmt = getDb()->prepare(
    'INSERT INTO admin_users (name, email, password_hash, role, status)
     VALUES (:name, :email, :hash, :role, :status)'
);
$stmt->execute([
    'name' => $name,
    'email' => $email,
    'hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
    'status' => 'active',
]);

echo "Created {$role} account for {$email}. Sign in at admin/login.php\n";
