/** Ports of admin/modules/{activity,users,settings}/*.php */
import 'server-only';
import { e } from '../../html';
import { dbExecute, dbFetchAll, dbFetchColumn, dbFetchOne } from '../../db';
import { donateUrl } from '../../content';
import { logActivity } from '../activity';
import { Redirect, csrfField, requireCapability, requirePostWithCsrf, type PublicCtx } from '../context';
import { saveSettings, saveUser } from '../site-admin';
import { flashInputGet, flashInputSet, flashSet, ROLE_LABELS } from '../session';
import { adminPage, fmtDate, listOrderLimit, listPagination, listParams, listSortHeader, listToolbar, listWhere, ucfirst } from '../ui';
import { int, type Routes } from './util';

const titleCase = (v: unknown) =>
  String(v ?? '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
const time = (v: unknown) =>
  new Date(String(v ?? '').replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }).toLowerCase();

/* -------------------------------- activity -------------------------------- */

async function activityIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'activity.view', '../../dashboard.php');
  const sortable = { when: 'l.created_at', user: 'u.name', action: 'l.action', module: 'l.module' };
  const params = listParams(ctx, sortable, 'when', ['module', 'action'], 30);
  if (!ctx.query.has('dir')) params.dir = 'DESC';
  const where = listWhere(params, ['u.name', 'u.email', 'l.details', 'l.ip_address'], { module: 'l.module', action: 'l.action' });
  const baseSql = ' FROM activity_logs l LEFT JOIN admin_users u ON u.id = l.user_id' + where.sql;
  const total = Number(await dbFetchColumn('SELECT COUNT(*)' + baseSql, where.params, 0));
  const entries = await dbFetchAll(
    'SELECT l.id, l.action, l.module, l.details, l.ip_address, l.created_at, u.name AS user_name, u.email AS user_email' + baseSql + listOrderLimit(params, 'l.id DESC'),
    where.params,
  );
  const modules = (await dbFetchAll('SELECT DISTINCT module FROM activity_logs ORDER BY module')).map((r) => String(r.module));
  const actions = (await dbFetchAll('SELECT DISTINCT action FROM activity_logs ORDER BY action')).map((r) => String(r.action));
  const body = `
<div class="module-header">
  <h1>Activity Log</h1>
</div>
${listToolbar(
  ctx,
  params,
  {
    module: { label: 'Modules', options: Object.fromEntries(modules.map((m) => [m, titleCase(m)])) },
    action: { label: 'Actions', options: Object.fromEntries(actions.map((a) => [a, titleCase(a)])) },
  },
  'Search user, details, or IP…',
)}
${
  !entries.length
    ? `<div class="empty-state">${params.q !== '' || Object.keys(params.filters).length ? 'No activity matches that search.' : 'No activity recorded yet.'}</div>`
    : `
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          ${listSortHeader(ctx, 'When', 'when', params)}
          ${listSortHeader(ctx, 'User', 'user', params)}
          ${listSortHeader(ctx, 'Action', 'action', params)}
          ${listSortHeader(ctx, 'Module', 'module', params)}
          <th>Details</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        ${entries
          .map(
            (entry) => `
          <tr>
            <td>
              <div class="data-table-primary">${e(fmtDate(entry.created_at))}</div>
              <div class="data-table-secondary">${e(time(entry.created_at))}</div>
            </td>
            <td>${
              entry.user_name !== null
                ? `<div class="data-table-primary">${e(entry.user_name)}</div><div class="data-table-secondary">${e(entry.user_email)}</div>`
                : `<span class="data-table-secondary">Deleted user</span>`
            }</td>
            <td><span class="status-badge status-badge--${e(entry.action)}">${e(titleCase(entry.action))}</span></td>
            <td>${e(titleCase(entry.module))}</td>
            <td>${entry.details !== null ? e(entry.details) : '<span class="data-table-secondary">—</span>'}</td>
            <td class="data-table-secondary">${e(entry.ip_address)}</td>
          </tr>`,
          )
          .join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}`
}`;
  return adminPage(ctx, { title: 'Activity Log', activeNav: 'Activity Log', body });
}

/* ---------------------------------- users --------------------------------- */

async function usersIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'users.manage', '../../dashboard.php');
  const sortable = { name: 'name', email: 'email', role: 'role', status: 'status', last_login: 'last_login_at' };
  const params = listParams(ctx, sortable, 'name', ['role', 'status']);
  const where = listWhere(params, ['name', 'email'], { role: 'role', status: 'status' });
  const total = Number(await dbFetchColumn('SELECT COUNT(*) FROM admin_users' + where.sql, where.params, 0));
  const users = await dbFetchAll('SELECT id, name, email, role, status, last_login_at FROM admin_users' + where.sql + listOrderLimit(params, 'id'), where.params);
  const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
  const me = ctx.user;
  const body = `
<div class="module-header">
  <h1>Users &amp; Roles</h1>
  <a href="form.php" class="btn-primary">+ Add User</a>
</div>
${listToolbar(ctx, params, { role: { label: 'Roles', options: ROLE_LABELS }, status: { label: 'Statuses', options: { active: 'Active', disabled: 'Disabled' } } }, 'Search name or email…')}
${
  !users.length
    ? `<div class="empty-state">${isFiltered ? 'No users match that search.' : 'No admin users found.'}</div>`
    : `
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          ${listSortHeader(ctx, 'Name', 'name', params)}
          ${listSortHeader(ctx, 'Email', 'email', params)}
          ${listSortHeader(ctx, 'Role', 'role', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Last Login', 'last_login', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${users
          .map(
            (row) => `
          <tr>
            <td class="data-table-primary">${e(row.name)}${int(row.id) === me.id ? ' <span class="data-table-secondary">(you)</span>' : ''}</td>
            <td>${e(row.email)}</td>
            <td>${e(ROLE_LABELS[row.role] ?? titleCase(row.role))}</td>
            <td><span class="status-badge status-badge--${row.status === 'active' ? 'active' : 'draft'}">${e(ucfirst(row.status))}</span></td>
            <td>${row.last_login_at ? e(fmtDate(row.last_login_at)) : '—'}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(row.id)}" class="link-edit">Edit<span class="sr-only"> ${e(row.name)}</span></a>
              ${
                int(row.id) !== me.id
                  ? `<form method="post" action="delete.php" data-confirm="Delete the account &quot;${e(row.name)}&quot;? Admin accounts are not kept in Trash — this cannot be undone.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(row.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(row.name)}</span></button>
                </form>`
                  : ''
              }
            </td>
          </tr>`,
          )
          .join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}`
}`;
  return adminPage(ctx, { title: 'Users & Roles', activeNav: 'Users & Roles', body });
}

async function usersForm(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'users.manage', '../../dashboard.php');
  const id = ctx.query.has('id') ? int(ctx.query.get('id')) : null;
  const isEdit = id !== null;
  let existing: Record<string, any> | null = null;
  if (isEdit) {
    existing = await dbFetchOne('SELECT * FROM admin_users WHERE id = :id LIMIT 1', { id });
    if (!existing) {
      flashSet(ctx.session, 'error', 'That user could not be found.');
      throw new Redirect('index.php');
    }
  }
  const pageTitle = isEdit ? 'Edit User' : 'Add User';
  const v: Record<string, any> = flashInputGet(ctx.session) ?? existing ?? { name: '', email: '', role: 'editor', status: 'active' };
  const sel = (a: unknown, b: string) => (a === b ? ' selected' : '');
  const body = `
<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Users</a>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ''}
  <div class="form-row-2">
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="${e(v.name)}" required maxlength="120">
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="${e(v.email)}" required maxlength="190">
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="editor"${sel(v.role, 'editor')}>Editor</option>
        <option value="administrator"${sel(v.role, 'administrator')}>Administrator</option>
        <option value="super_admin"${sel(v.role, 'super_admin')}>Super Admin</option>
      </select>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="active"${sel(v.status, 'active')}>Active</option>
        <option value="disabled"${sel(v.status, 'disabled')}>Disabled</option>
      </select>
    </div>
  </div>
  <div class="field">
    <label for="password">Password${isEdit ? ' (leave blank to keep current password)' : ''}</label>
    <input type="password" id="password" name="password" autocomplete="new-password"${isEdit ? '' : ' required'}>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create User'}</button>
  </div>
</form>`;
  return adminPage(ctx, { title: pageTitle, activeNav: 'Users & Roles', body });
}

async function usersSave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'users.manage');
  requirePostWithCsrf(ctx);
  const id = ctx.post.id !== undefined && ctx.post.id !== '' ? int(ctx.post.id) : null;
  const back = id !== null ? `form.php?id=${id}` : 'form.php';
  const res = await saveUser(id, ctx.post, { userId: ctx.user.id, meta: ctx.meta, via: 'admin' });
  if (!res.ok) {
    const { password: _pw, csrf_token: _t, ...input } = ctx.post;
    flashInputSet(ctx.session, { ...input, id });
    flashSet(
      ctx.session,
      'error',
      res.errors
        .map((m) => (m.startsWith('This is the last active Super Admin') ? 'You are the last active Super Admin — you cannot change your own role or status away from that.' : m))
        .join(' '),
    );
    throw new Redirect(back);
  }
  flashSet(ctx.session, 'success', res.message);
  throw new Redirect('index.php');
}

async function usersDelete(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'users.manage');
  requirePostWithCsrf(ctx);
  const id = ctx.post.id ? int(ctx.post.id) : null;
  if (id) {
    if (id === ctx.user.id) {
      flashSet(ctx.session, 'error', 'You cannot delete your own account while logged in as it.');
      throw new Redirect('index.php');
    }
    const target = await dbFetchOne('SELECT name, email, role, status FROM admin_users WHERE id = :id LIMIT 1', { id });
    if (!target) {
      flashSet(ctx.session, 'error', 'That user could not be found.');
      throw new Redirect('index.php');
    }
    if (target.role === 'super_admin' && target.status === 'active') {
      const supers = Number(await dbFetchColumn("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'", [], 0));
      if (supers <= 1) {
        flashSet(ctx.session, 'error', 'This is the last active Super Admin account and cannot be deleted.');
        throw new Redirect('index.php');
      }
    }
    await dbExecute('DELETE FROM admin_users WHERE id = :id', { id });
    await logActivity(ctx.user.id, 'delete', 'users', String(target.email), ctx.meta);
    flashSet(ctx.session, 'success', 'User deleted.');
  }
  throw new Redirect('index.php');
}

/* -------------------------------- settings -------------------------------- */

async function settingsIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'settings.manage', '../../dashboard.php');
  let existing = await dbFetchOne('SELECT * FROM site_settings WHERE id = 1');
  if (!existing) {
    await dbExecute('INSERT INTO site_settings (id) VALUES (1)');
    existing = await dbFetchOne('SELECT * FROM site_settings WHERE id = 1');
  }
  const v: Record<string, any> = flashInputGet(ctx.session) ?? existing ?? {};
  const url = (id: string, label: string) =>
    `<div class="field">
        <label for="${id}">${label}</label>
        <input type="url" id="${id}" name="${id}" value="${e(v[id] ?? '')}" maxlength="255">
      </div>`;
  const body = `
<div class="module-header">
  <h1>Settings</h1>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  <fieldset class="form-section">
    <legend>Contact details</legend>
    <p class="field-hint">Shown in the website footer, on the Contact and Volunteer pages, and in the home page contact section.</p>
    <div class="form-row-2">
      <div class="field">
        <label for="contact_email">Contact Email</label>
        <input type="email" id="contact_email" name="contact_email" value="${e(v.contact_email ?? '')}" maxlength="190">
      </div>
      <div class="field">
        <label for="contact_phone">Contact Phone</label>
        <input type="text" id="contact_phone" name="contact_phone" value="${e(v.contact_phone ?? '')}" maxlength="60">
      </div>
    </div>
    <div class="field">
      <label for="contact_address">Contact Address</label>
      <input type="text" id="contact_address" name="contact_address" value="${e(v.contact_address ?? '')}" maxlength="255">
    </div>
  </fieldset>
  <fieldset class="form-section">
    <legend>Donations</legend>
    <div class="field">
      <label for="donate_url">Donate link</label>
      <input type="url" id="donate_url" name="donate_url" value="${e(v.donate_url ?? '')}" maxlength="255" placeholder="${e(await donateUrl())}">
      <p class="field-hint">The checkout link the Donate page's "Continue" button opens. The "Donate Now" buttons in the header and on the home page open the Donate page, which then continues here. A Donorbox campaign link (https://donorbox.org/your-campaign) opens its donation form on the Donate page, with the chosen amount filled in. Leave blank to use the Bright Beginnings campaign on Donorbox.</p>
    </div>
  </fieldset>
  <fieldset class="form-section">
    <legend>Social media</legend>
    <p class="field-hint">The icons in the website footer. Leave a field blank to hide that icon.</p>
    <div class="form-row-2">
      ${url('facebook_url', 'Facebook URL')}
      ${url('twitter_url', 'X (Twitter) URL')}
    </div>
    <div class="form-row-2">
      ${url('instagram_url', 'Instagram URL')}
      ${url('linkedin_url', 'LinkedIn URL')}
    </div>
    ${url('youtube_url', 'YouTube URL')}
  </fieldset>
  <div class="form-actions">
    <button type="submit" class="btn-submit">Save Settings</button>
  </div>
</form>`;
  return adminPage(ctx, { title: 'Settings', activeNav: 'Settings', body });
}

async function settingsSave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'settings.manage');
  requirePostWithCsrf(ctx);
  const res = await saveSettings(ctx.post, { userId: ctx.user.id, meta: ctx.meta, via: 'admin' });
  if (!res.ok) {
    flashInputSet(ctx.session, ctx.post);
    flashSet(ctx.session, 'error', res.errors.join(' '));
  } else flashSet(ctx.session, 'success', 'Settings saved.');
  throw new Redirect('index.php');
}

export const activityRoutes: Routes = { 'index.php': activityIndex };
export const usersRoutes: Routes = { 'index.php': usersIndex, 'form.php': usersForm, 'save.php': usersSave, 'delete.php': usersDelete };
export const settingsRoutes: Routes = { 'index.php': settingsIndex, 'save.php': settingsSave };
