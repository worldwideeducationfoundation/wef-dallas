<?php
/**
 * Where Constant Contact sends the browser back to. This is the URL registered
 * as the app's Redirect URI, and the value of CC_REDIRECT_URI.
 *
 * WHY THIS FILE DOES ALMOST NOTHING, AND WHY IT MUST NOT DO MORE
 * ---------------------------------------------------------------
 * The admin session cookie is set with SameSite=Strict (see initSession() in
 * admin/includes/auth.php). Strict means the browser does not send that cookie
 * on ANY cross-site request — including a top-level navigation like this one,
 * which arrives from constantcontact.com. So at this exact moment the request
 * has no session, no signed-in user, and no CSRF token, no matter who started
 * the flow thirty seconds ago.
 *
 * Two things follow, and both are load-bearing:
 *
 *   1. This file must not include bootstrap.php or otherwise call
 *      initSession(). Starting a session here would find no cookie, mint a
 *      brand new empty one, and send it back — replacing the administrator's
 *      real session cookie and logging them out mid-flow. That is why this file
 *      includes nothing at all and escapes with htmlspecialchars() by hand
 *      rather than reaching for e().
 *
 *   2. The authorisation code cannot be redeemed from here, because redeeming
 *      it means writing to the database and that needs a capability check on a
 *      real user. So this page re-posts the parameters to exchange.php. That
 *      POST is issued by a document already on our own origin, which makes it
 *      same-site — and same-site is exactly the condition under which the
 *      Strict cookie IS sent. exchange.php therefore runs with the full
 *      session, and can check the capability and the state properly.
 *
 * The alternative — relaxing the session cookie to SameSite=Lax so this page
 * could work directly — would weaken CSRF protection across all 56 admin
 * screens to make one screen simpler. This trade goes the other way.
 *
 * A form POST rather than a redirect to exchange.php?code=... keeps the
 * single-use authorisation code out of a second URL, out of browser history,
 * and out of any Referer header the next page might send.
 */

$code = isset($_GET['code']) ? (string) $_GET['code'] : '';
$state = isset($_GET['state']) ? (string) $_GET['state'] : '';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
$errorDescription = isset($_GET['error_description']) ? (string) $_GET['error_description'] : '';

/** Everything below is attacker-controllable query input, so it is escaped. */
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// This page is a mechanical hop, never a destination worth indexing or caching.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Finishing the Constant Contact connection…</title>
<style>
  body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fbfaf8;
         color: #3a3f47; display: flex; align-items: center; justify-content: center;
         min-height: 100vh; margin: 0; padding: 24px; }
  .box { background: #fff; border: 1px solid #e7e9ec; border-radius: 16px; padding: 32px;
         max-width: 460px; box-shadow: 0 8px 24px rgba(18,20,23,.06); text-align: center; }
  h1 { font-size: 18px; margin: 0 0 12px; color: #121417; }
  p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
  button { font: inherit; font-weight: 600; background: #0e611d; color: #fff; border: 0;
           border-radius: 8px; padding: 10px 18px; cursor: pointer; }
</style>
</head>
<body>
  <div class="box">
    <h1>Finishing the Constant Contact connection…</h1>
    <p>One moment — returning you to the admin panel.</p>

    <!-- Submitted immediately by the script below. The button is the fallback
         for anyone with JavaScript blocked: the flow still completes, it just
         takes one click. -->
    <form id="cc-exchange" method="post" action="exchange.php">
      <input type="hidden" name="code" value="<?= $esc($code) ?>">
      <input type="hidden" name="state" value="<?= $esc($state) ?>">
      <input type="hidden" name="error" value="<?= $esc($error) ?>">
      <input type="hidden" name="error_description" value="<?= $esc($errorDescription) ?>">
      <button type="submit">Continue</button>
    </form>
  </div>
  <script>document.getElementById('cc-exchange').submit();</script>
</body>
</html>
