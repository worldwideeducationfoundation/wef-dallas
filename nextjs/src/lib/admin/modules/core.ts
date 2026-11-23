/** Port of admin/{index,login,logout}.php */
import 'server-only';
import { e } from '../../html';
import { Redirect, csrfField, requirePostWithCsrf, requireLogin, type PublicCtx } from '../context';
import { attemptLogin, csrfVerify, currentUser, logoutUser } from '../session';
import type { Routes } from './util';

const REASONS: Record<string, string> = {
  idle: 'You were signed out after a period of inactivity. Please sign in again.',
  expired: 'Your session reached its maximum length. Please sign in again.',
  disabled: 'This account has been disabled. Contact a Super Admin if you believe this is a mistake.',
  removed: 'This account no longer exists.',
};

async function login(ctx: PublicCtx): Promise<string> {
  if (currentUser(ctx.session) !== null) throw new Redirect('dashboard.php');
  let error: string | null = null;
  let notice: string | null = null;
  const reason = ctx.query.get('reason');
  if (reason && REASONS[reason]) notice = REASONS[reason];
  if (ctx.method === 'POST') {
    if (!csrfVerify(ctx.session, ctx.post.csrf_token)) error = 'Your session expired. Please try again.';
    else {
      const result = await attemptLogin(ctx.session, ctx.post.email ?? '', ctx.post.password ?? '', ctx.meta);
      if (result.ok) throw new Redirect('dashboard.php');
      error = result.error;
    }
  }
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Sign In — WEF Admin</title>
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
      ${error ? `<div class="alert alert-error" role="alert">${e(error)}</div>` : notice ? `<div class="alert alert-notice" role="status">${e(notice)}</div>` : ''}
      <form method="post" action="login.php" novalidate>
        ${csrfField(ctx)}
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
</html>`;
}

export const coreRoutes: Routes = {
  '': async (ctx) => {
    throw new Redirect(currentUser(ctx.session) !== null ? 'admin/dashboard.php' : 'admin/login.php');
  },
  'index.php': async (ctx) => {
    throw new Redirect(currentUser(ctx.session) !== null ? 'dashboard.php' : 'login.php');
  },
  'login.php': login,
  'logout.php': async (pctx) => {
    const ctx = await requireLogin(pctx);
    requirePostWithCsrf(ctx, 'dashboard.php');
    await logoutUser(ctx.session, ctx.meta);
    throw new Redirect('login.php');
  },
};
