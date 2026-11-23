/** Ports of admin/dashboard.php and admin/account.php */
import 'server-only';
import { e } from '../../html';
import { dbExecute, dbFetchAll, dbFetchOne } from '../../db';
import { adminLog, logActivity } from '../activity';
import { Redirect, csrfField, requireLogin, requirePostWithCsrf, type PublicCtx } from '../context';
import { hashPassword, passwordPolicyError, PASSWORD_MIN_LENGTH, verifyPassword } from '../site-admin';
import { can, flashSet, ROLE_LABELS } from '../session';
import { adminPage } from '../ui';
import { phpDate, type Routes } from './util';

const titleCase = (v: unknown) =>
  String(v ?? '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());

function timeAgo(ts: unknown): string {
  const seconds = Math.floor((Date.now() - new Date(String(ts).replace(' ', 'T')).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  for (const [limit, unit, divisor] of [
    [3600, 'minute', 60],
    [86400, 'hour', 3600],
    [2592000, 'day', 86400],
  ] as const) {
    if (seconds < limit) {
      const n = Math.floor(seconds / divisor);
      return `${n} ${unit}${n === 1 ? '' : 's'} ago`;
    }
  }
  return phpDate('M j, Y', ts);
}

async function dashboard(pctx: PublicCtx) {
  const ctx = await requireLogin(pctx);
  const rows = await dbFetchAll(`
    SELECT 'programs' AS k, COUNT(*) AS total, SUM(status = 'active') AS live FROM programs WHERE deleted_at IS NULL
    UNION ALL SELECT 'updates', COUNT(*), SUM(status = 'active') FROM project_updates WHERE deleted_at IS NULL
    UNION ALL SELECT 'team', COUNT(*), SUM(status = 'active') FROM team_members WHERE deleted_at IS NULL
    UNION ALL SELECT 'sponsors', COUNT(*), SUM(status = 'active') FROM sponsors WHERE deleted_at IS NULL
    UNION ALL SELECT 'gallery', COUNT(*), SUM(status = 'active') FROM gallery_items WHERE deleted_at IS NULL
    UNION ALL SELECT 'reports', COUNT(*), SUM(status = 'active') FROM annual_reports WHERE deleted_at IS NULL
    UNION ALL SELECT 'users', COUNT(*), SUM(status = 'active') FROM admin_users
    UNION ALL SELECT 'submissions', COUNT(*), SUM(status = 'new') FROM form_submissions WHERE deleted_at IS NULL`);
  const stats: Record<string, { total: number; live: number }> = {};
  for (const r of rows) stats[r.k] = { total: Math.trunc(Number(r.total) || 0), live: Math.trunc(Number(r.live) || 0) };
  const cards = [
    { key: 'programs', label: 'Programs', href: 'modules/programs/index.php', cap: 'content.view' },
    { key: 'updates', label: 'Project Updates', href: 'modules/updates/index.php', cap: 'content.view' },
    { key: 'team', label: 'Team Members', href: 'modules/team/index.php', cap: 'content.view' },
    { key: 'sponsors', label: 'Sponsors', href: 'modules/sponsors/index.php', cap: 'content.view' },
    { key: 'gallery', label: 'Gallery Photos', href: 'modules/gallery/index.php', cap: 'content.view' },
    { key: 'reports', label: 'Annual Reports', href: 'modules/reports/index.php', cap: 'content.view' },
    { key: 'users', label: 'Admin Users', href: 'modules/users/index.php', cap: 'users.manage' },
    { key: 'submissions', label: 'Form Submissions', href: 'modules/submissions/index.php', cap: 'submissions.view' },
  ].filter((c) => can(ctx.session, c.cap));
  const unread = can(ctx.session, 'submissions.view') ? (stats.submissions?.live ?? 0) : 0;
  let draftTotal = 0;
  for (const k of ['programs', 'updates', 'team', 'sponsors', 'gallery', 'reports']) draftTotal += (stats[k]?.total ?? 0) - (stats[k]?.live ?? 0);
  const canActivity = can(ctx.session, 'activity.view');
  const recent = canActivity
    ? await dbFetchAll(
        `SELECT l.action, l.module, l.details, l.created_at, u.name AS user_name FROM activity_logs l
         LEFT JOIN admin_users u ON u.id = l.user_id ORDER BY l.created_at DESC, l.id DESC LIMIT 8`,
      )
    : [];
  const body = `
<h1 class="sr-only">Dashboard</h1>
<div class="welcome-card">
  <h2>Welcome back, ${e(ctx.user.name.split(' ')[0])}.</h2>
  <p>${
    draftTotal > 0
      ? `You have <strong>${draftTotal}</strong> item${draftTotal === 1 ? '' : 's'} still in draft — they won't appear on the public site until set to Active.`
      : 'Everything is published — no content is sitting in draft.'
  }</p>
  ${
    unread > 0
      ? `<p><strong>${unread}</strong> unread form submission${unread === 1 ? '' : 's'} from the Contact and Volunteer pages — <a href="modules/submissions/index.php?status=new">read them now</a>.</p>`
      : ''
  }
</div>
<div class="stat-grid">
  ${cards
    .map((card) => {
      const s = stats[card.key] ?? { total: 0, live: 0 };
      const meta =
        card.key === 'users'
          ? `${s.live} active`
          : card.key === 'submissions'
            ? `${s.live} new &middot; ${s.total - s.live} handled`
            : `${s.live} live &middot; ${s.total - s.live} draft`;
      return `<a class="stat-card" href="${e(card.href)}">
    <span class="stat-card-value">${s.total}</span>
    <span class="stat-card-label">${e(card.label)}</span>
    <span class="stat-card-meta">${meta}</span>
  </a>`;
    })
    .join('')}
</div>
${
  canActivity
    ? `<div class="panel">
  <div class="panel-header">
    <h2>Recent Activity</h2>
    <a href="modules/activity/index.php" class="link-edit">View all</a>
  </div>
  ${
    !recent.length
      ? `<div class="empty-state">No activity recorded yet.</div>`
      : `<ul class="activity-feed">${recent
          .map(
            (entry) => `
    <li>
      <span class="status-badge status-badge--${e(entry.action)}">${e(titleCase(entry.action))}</span>
      <span class="activity-feed-text">
        <strong>${e(entry.user_name ?? 'Deleted user')}</strong>
        ${e(titleCase(entry.module))}
        ${entry.details !== null ? `— ${e(entry.details)}` : ''}
      </span>
      <time class="activity-feed-time" datetime="${e(entry.created_at)}">${e(timeAgo(entry.created_at))}</time>
    </li>`,
          )
          .join('')}</ul>`
  }
</div>`
    : ''
}`;
  return adminPage(ctx, { title: 'Dashboard', activeNav: 'Dashboard', body });
}

async function account(pctx: PublicCtx) {
  const ctx = await requireLogin(pctx);
  const user = ctx.user;
  if (ctx.method === 'POST') {
    requirePostWithCsrf(ctx, 'account.php');
    const current = ctx.post.current_password ?? '';
    const next = ctx.post.new_password ?? '';
    const confirm = ctx.post.confirm_password ?? '';
    const errors: string[] = [];
    const row = await dbFetchOne('SELECT password_hash FROM admin_users WHERE id = :id LIMIT 1', { id: user.id });
    if (!row || !(await verifyPassword(current, String(row.password_hash)))) errors.push('Your current password is not correct.');
    if (next !== confirm) errors.push('The new password and its confirmation do not match.');
    if (next !== '' && current !== '' && next === current) errors.push('The new password must be different from your current one.');
    if (!errors.length || next !== '') {
      const p = passwordPolicyError(next, user.email, user.name);
      if (p) errors.push(p);
    }
    if (errors.length) {
      flashSet(ctx.session, 'error', errors.join(' '));
      adminLog('WARN', 'failed self-service password change for user id ' + user.id);
      throw new Redirect('account.php');
    }
    await dbExecute('UPDATE admin_users SET password_hash = :hash WHERE id = :id', { hash: await hashPassword(next), id: user.id });
    ctx.session.regenerate();
    await logActivity(user.id, 'password_change', 'account', null, ctx.meta);
    flashSet(ctx.session, 'success', 'Your password has been changed.');
    throw new Redirect('account.php');
  }
  const body = `
<div class="module-header">
  <h1>Your Account</h1>
</div>
<div class="welcome-card">
  <p>
    Signed in as <strong>${e(user.name)}</strong>
    (${e(user.email)}) &mdash;
    ${e(ROLE_LABELS[user.role] ?? user.role)}.
  </p>
  <p>
    Your name, email address and role can only be changed by a Super Admin.
    Your password is yours to change here, and nobody else needs to know it.
  </p>
</div>
<form method="post" action="account.php" class="admin-form" autocomplete="off">
  ${csrfField(ctx)}
  <div class="field">
    <label for="current_password">Current password</label>
    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="new_password">New password</label>
    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="${PASSWORD_MIN_LENGTH}" aria-describedby="password-help">
    <p class="field-help" id="password-help">
      At least ${PASSWORD_MIN_LENGTH} characters. Length matters more than
      symbols &mdash; three or four unrelated words make a strong password that is
      easy to remember. It must not contain your name or email address.
    </p>
  </div>
  <div class="field">
    <label for="confirm_password">Confirm new password</label>
    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required minlength="${PASSWORD_MIN_LENGTH}">
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">Change password</button>
    <a href="dashboard.php" class="link-back">Cancel</a>
  </div>
</form>`;
  return adminPage(ctx, { title: 'Your Account', activeNav: '', body });
}

export const homeRoutes: Routes = { 'dashboard.php': dashboard, 'account.php': account };
