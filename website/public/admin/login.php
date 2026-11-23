<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

initSession();

if (currentUser() !== null) {
    redirect('dashboard.php');
}

$error = null;
$notice = null;

// Set by requireLogin() when a session was ended server-side rather than
// by the user clicking Log Out — without this the user is bounced to a
// bare login form with no explanation of why.
$reasonMessages = [
    'idle' => 'You were signed out after a period of inactivity. Please sign in again.',
    'expired' => 'Your session reached its maximum length. Please sign in again.',
    'disabled' => 'This account has been disabled. Contact a Super Admin if you believe this is a mistake.',
    'removed' => 'This account no longer exists.',
];
if (isset($_GET['reason']) && isset($reasonMessages[$_GET['reason']])) {
    $notice = $reasonMessages[$_GET['reason']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $result = attemptLogin($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['ok']) {
            redirect('dashboard.php');
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Sign In — WEF Admin</title>
<!-- Self-hosted DM Sans. This page builds its own <head> rather than using
     layout-header.php, so the font link has to be repeated here. -->
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <div class="login-brand">
        <img src="../assets/images/logo.png" alt="WEF Logo" width="64" height="64" class="login-brand-logo">
        <h1>Worldwide Education Foundation</h1>
        <p>Admin Panel</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php elseif ($notice): ?>
        <div class="alert alert-notice" role="status"><?= e($notice) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate>
        <?= csrfField() ?>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" autocomplete="username" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
      </form>

      <p class="login-footnote">Internal use only — Worldwide Education Foundation</p>
    </div>
  </div>
  <script src="assets/js/admin.js" defer></script>
</body>
</html>
