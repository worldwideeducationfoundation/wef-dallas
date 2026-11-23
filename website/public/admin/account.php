<?php
/**
 * "Your account" — change your own password.
 *
 * The gap this closes: there was no way to change your own password. The only
 * route was asking a Super Admin to set one for you through the Users module,
 * which means somebody else chooses your password and then sends it to you
 * over chat or email. That is how shared, reused and written-down passwords
 * happen, and it made the 12-character policy added in Phase 2 mostly
 * theoretical — nobody rotates a password they cannot change.
 *
 * Deliberately NOT in modules/users/: that module is guarded by
 * `users.manage`, which only Super Admins hold. This page is for the signed-in
 * user's own account, so every role can reach it and it can only ever act on
 * currentUser()['id'] — the account id is never read from the request.
 */

require_once __DIR__ . '/includes/bootstrap.php';

requireLogin();

$user = currentUser();
$pageTitle = 'Your Account';
$activeNav = '';
$linkBase = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePostWithCsrf('account.php');

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $errors = [];

    // Re-authenticate. Without this, anyone who finds an unlocked machine with
    // a live session could lock the real owner out of their own account.
    $row = dbFetchOne('SELECT password_hash FROM admin_users WHERE id = :id LIMIT 1', ['id' => $user['id']]);

    if ($row === null || !password_verify($current, $row['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }

    if ($new !== $confirm) {
        $errors[] = 'The new password and its confirmation do not match.';
    }

    if ($new !== '' && $current !== '' && $new === $current) {
        $errors[] = 'The new password must be different from your current one.';
    }

    // Same rules as the Users module and create-admin.php — one shared function.
    if ($errors === [] || $new !== '') {
        $policyError = passwordPolicyError($new, $user['email'], $user['name']);
        if ($policyError !== null) {
            $errors[] = $policyError;
        }
    }

    if ($errors !== []) {
        // Deliberately no flashInputSet here: repopulating a password field
        // would put the value back in the HTML of the next response.
        flashSet('error', implode(' ', $errors));
        adminLog('WARN', 'failed self-service password change for user id ' . (int) $user['id']);
        redirect('account.php');
    }

    dbExecute(
        'UPDATE admin_users SET password_hash = :hash WHERE id = :id',
        ['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]
    );

    // New session id on a credential change, so any other session that had
    // this one's cookie is left holding an id the server no longer honours.
    session_regenerate_id(true);

    logActivity($user['id'], 'password_change', 'account');
    flashSet('success', 'Your password has been changed.');
    redirect('account.php');
}

require __DIR__ . '/includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Your Account</h1>
        </div>

        <div class="welcome-card">
          <p>
            Signed in as <strong><?= e($user['name']) ?></strong>
            (<?= e($user['email']) ?>) &mdash;
            <?= e(ROLE_LABELS[$user['role']] ?? $user['role']) ?>.
          </p>
          <p>
            Your name, email address and role can only be changed by a Super Admin.
            Your password is yours to change here, and nobody else needs to know it.
          </p>
        </div>

        <!-- Classes match the other admin forms (admin-form / field /
             field-help / btn-submit) so this page needs no new CSS and picks
             up the existing responsive and dark-mode rules for free. -->
        <form method="post" action="account.php" class="admin-form" autocomplete="off">
          <?= csrfField() ?>

          <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
          </div>

          <div class="field">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" required
                   minlength="<?= (int) PASSWORD_MIN_LENGTH ?>"
                   aria-describedby="password-help">
            <p class="field-help" id="password-help">
              At least <?= (int) PASSWORD_MIN_LENGTH ?> characters. Length matters more than
              symbols &mdash; three or four unrelated words make a strong password that is
              easy to remember. It must not contain your name or email address.
            </p>
          </div>

          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" required
                   minlength="<?= (int) PASSWORD_MIN_LENGTH ?>">
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Change password</button>
            <a href="dashboard.php" class="link-back">Cancel</a>
          </div>
        </form>
<?php require __DIR__ . '/includes/layout-footer.php'; ?>
