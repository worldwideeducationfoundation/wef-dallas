/**
 * Admin page chrome and shared widgets, producing the same markup as
 * admin/includes/{layout-header,layout-footer,listing,bulk,reorder,media-specs,upload}.php.
 */
import 'server-only';
import { e } from '../html';
import { dbFetchColumn } from '../db';
import { submissionUnreadCount } from '../forms';
import { RESOURCES } from './resources';
import { can, flashGet, ROLE_LABELS } from './session';
import { csrfField, type Ctx, type PublicCtx } from './context';
import { UPLOAD_DOCUMENT_MAX_BYTES, UPLOAD_IMAGE_MAX_BYTES } from './uploads';

type NavItem = { label: string; href: string; cap: string | null; group: string };

const NAV_ITEMS: NavItem[] = [
  { label: 'Dashboard', href: 'dashboard.php', cap: null, group: '' },
  { label: 'Home Slideshow', href: 'modules/hero-slides/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Page Content', href: 'modules/pages/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Pages On/Off', href: 'modules/page-visibility/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Donate Options', href: 'modules/donate-options/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Partner Logos', href: 'modules/partners/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Project Updates', href: 'modules/updates/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Gallery', href: 'modules/gallery/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Success Stories', href: 'modules/success-stories/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Video Gallery', href: 'modules/video-gallery/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Map Centers', href: 'modules/map-centers/index.php', cap: 'content.view', group: 'Website content' },
  { label: 'Programs', href: 'modules/programs/index.php', cap: 'content.view', group: 'Projects & impact' },
  { label: 'Completed Projects', href: 'modules/completed-projects/index.php', cap: 'content.view', group: 'Projects & impact' },
  { label: 'Mission Projects', href: 'modules/mission-projects/index.php', cap: 'content.view', group: 'Projects & impact' },
  { label: 'Impact Goals Roadmap', href: 'modules/impact-milestones/index.php', cap: 'content.view', group: 'Projects & impact' },
  { label: 'Annual Reports', href: 'modules/reports/index.php', cap: 'content.view', group: 'Projects & impact' },
  { label: 'Team', href: 'modules/team/index.php', cap: 'content.view', group: 'People' },
  { label: 'Honor Board / Sponsors', href: 'modules/sponsors/index.php', cap: 'content.view', group: 'People' },
  { label: 'Form Submissions', href: 'modules/submissions/index.php', cap: 'submissions.view', group: 'Messages' },
  { label: 'Newsletter', href: 'modules/newsletter/index.php', cap: 'newsletter.manage', group: 'Messages' },
  { label: 'Media Manager', href: 'modules/media/index.php', cap: 'media.view', group: 'Files' },
  { label: 'Trash', href: 'modules/trash/index.php', cap: 'content.view', group: 'Files' },
  { label: 'Activity Log', href: 'modules/activity/index.php', cap: 'activity.view', group: 'Administration' },
  { label: 'Users & Roles', href: 'modules/users/index.php', cap: 'users.manage', group: 'Administration' },
  { label: 'Settings', href: 'modules/settings/index.php', cap: 'settings.manage', group: 'Administration' },
];

export async function trashCount(): Promise<number> {
  let total = 0;
  for (const r of Object.values(RESOURCES)) {
    if (!r.softDelete) continue;
    total += Number(await dbFetchColumn(`SELECT COUNT(*) FROM ${r.table} WHERE deleted_at IS NOT NULL`, [], 0));
  }
  total += Number(await dbFetchColumn('SELECT COUNT(*) FROM form_submissions WHERE deleted_at IS NOT NULL', [], 0));
  return total;
}

export type PageOptions = { title: string; activeNav: string; body: string; extraStyles?: string[]; extraScripts?: string[] };

/** layout-header.php + body + layout-footer.php */
export async function adminPage(ctx: Ctx, o: PageOptions): Promise<string> {
  const { linkBase: b, user, session } = ctx;
  const items = NAV_ITEMS.filter((i) => i.cap === null || can(session, i.cap));
  let nav = '';
  let group = '';
  for (const item of items) {
    if (item.group !== group) {
      group = item.group;
      nav += `<div class="app-nav-group">${e(group)}</div>`;
    }
    const active = item.label === o.activeNav;
    let count = '';
    if (item.label === 'Trash') {
      const n = await trashCount();
      if (n > 0) count = `<span class="app-nav-count">${n}</span>`;
    }
    if (item.label === 'Form Submissions') {
      const n = await submissionUnreadCount();
      if (n > 0) count = `<span class="app-nav-count app-nav-count--alert">${n}</span>`;
    }
    nav += `<a href="${e(b + item.href)}" class="app-nav-item${active ? ' is-active' : ''}"${active ? ' aria-current="page"' : ''}><span>${e(item.label)}</span>${count}</a>`;
  }
  const flash = flashGet(session);
  const roleLabel = ROLE_LABELS[user.role] ?? user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>${e(o.title)} — WEF Admin</title>
<link rel="stylesheet" href="${e(b)}assets/css/fonts.css">
<link rel="stylesheet" href="${e(b)}assets/css/admin.css">
${(o.extraStyles ?? []).map((s) => `<link rel="stylesheet" href="${e(b + s)}">`).join('\n')}
<script nonce="${e(ctx.nonce)}">try{if(localStorage.getItem('admin-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="app-shell">
    <div class="app-sidebar-backdrop" data-nav-close hidden></div>
    <aside class="app-sidebar" id="app-sidebar">
      <div class="app-sidebar-brand">
        <img src="${e(b)}../assets/images/logo.png" alt="WEF Logo" width="36" height="36" class="app-sidebar-brand-logo">
        <span>Admin Panel</span>
      </div>
      <nav class="app-nav" aria-label="Admin sections">${nav}</nav>
    </aside>
    <div class="app-main">
      <header class="app-topbar">
        <div class="app-topbar-left">
          <button type="button" class="nav-toggle" data-nav-toggle aria-controls="app-sidebar" aria-expanded="false">
            <span class="sr-only">Toggle navigation</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
          </button>
        </div>
        <div class="app-topbar-right">
          <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
            <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
            <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display: none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          </button>
          <a class="app-user" href="${e(b)}account.php" aria-label="Your account settings for ${e(user.name)}">
            <div class="app-user-avatar" aria-hidden="true">${e(user.name.charAt(0).toUpperCase())}</div>
            <div>
              <div class="app-user-name">${e(user.name)}</div>
              <div class="app-user-role">${e(roleLabel)}</div>
            </div>
          </a>
        </div>
        <form method="post" action="${e(b)}logout.php" class="logout-form">
          ${csrfField(ctx)}
          <button type="submit" class="btn-logout">Log Out</button>
        </form>
      </header>
      <main class="app-content" id="main-content">
        ${flash ? `<div class="alert alert-${e(flash.type)}" role="${flash.type === 'error' ? 'alert' : 'status'}">${e(flash.message)}</div>` : ''}
${o.body}
      </main>
    </div>
  </div>
  <script src="${e(b)}assets/js/admin.js" defer></script>
  ${(o.extraScripts ?? []).map((s) => `<script src="${e(b + s)}" defer></script>`).join('\n  ')}
</body>
</html>`;
}

/* ------------------------------- listing ------------------------------- */

export const LIST_PER_PAGE = 100;

export type ListParams = {
  q: string;
  sort: string;
  sortSql: string;
  dir: 'ASC' | 'DESC';
  page: number;
  perPage: number;
  offset: number;
  filters: Record<string, string>;
};

export function listParams(ctx: PublicCtx, sortable: Record<string, string>, defaultSort: string, filterKeys: string[] = [], perPage = LIST_PER_PAGE): ListParams {
  const q = ctx.query;
  let sort = q.get('sort') ?? defaultSort;
  if (!(sort in sortable)) sort = defaultSort;
  const dir = (q.get('dir') ?? 'asc').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
  const page = Math.max(1, Number.parseInt(q.get('page') ?? '1', 10) || 1);
  const filters: Record<string, string> = {};
  for (const k of filterKeys) {
    const v = (q.get(k) ?? '').trim();
    if (v !== '') filters[k] = v;
  }
  return { q: (q.get('q') ?? '').trim(), sort, sortSql: sortable[sort], dir, page, perPage, offset: (page - 1) * perPage, filters };
}

export function listWhere(p: ListParams, searchColumns: string[], filterColumns: Record<string, string> = {}, always: string[] = []) {
  const clauses = [...always];
  const bound: Record<string, unknown> = {};
  if (p.q !== '' && searchColumns.length) {
    clauses.push('(' + searchColumns.map((c, i) => `${c} LIKE :q${i}`).join(' OR ') + ')');
    searchColumns.forEach((_, i) => (bound['q' + i] = '%' + p.q.replace(/[%_\\]/g, (m) => '\\' + m) + '%'));
  }
  for (const [k, v] of Object.entries(p.filters)) {
    if (!filterColumns[k]) continue;
    clauses.push(`${filterColumns[k]} = :f_${k}`);
    bound['f_' + k] = v;
  }
  return { sql: clauses.length ? ' WHERE ' + clauses.join(' AND ') : '', params: bound };
}

export function listOrderLimit(p: ListParams, tieBreaker = ''): string {
  return ` ORDER BY ${p.sortSql} ${p.dir}${tieBreaker ? ', ' + tieBreaker : ''} LIMIT ${Math.trunc(p.perPage)} OFFSET ${Math.trunc(p.offset)}`;
}

/** PHP http_build_query over $_GET with overrides. */
export function listUrl(ctx: PublicCtx, overrides: Record<string, string | number | null> = {}): string {
  const q = new URLSearchParams(ctx.query);
  for (const [k, v] of Object.entries(overrides)) {
    if (v === null || v === '') q.delete(k);
    else q.set(k, String(v));
  }
  for (const [k, v] of [...q.entries()]) if (v === '') q.delete(k);
  const s = q.toString();
  return s === '' ? '?' : '?' + s;
}

export function listSortHeader(ctx: PublicCtx, label: string, key: string, p: ListParams): string {
  const active = p.sort === key;
  const nextDir = active && p.dir === 'ASC' ? 'desc' : 'asc';
  const arrow = active ? (p.dir === 'ASC' ? ' ▲' : ' ▼') : '';
  const aria = active ? (p.dir === 'ASC' ? 'ascending' : 'descending') : 'none';
  return `<th aria-sort="${aria}"><a class="list-sort${active ? ' is-active' : ''}" href="${e(listUrl(ctx, { sort: key, dir: nextDir, page: null }))}">${e(label)}${arrow}</a></th>`;
}

export function listToolbar(ctx: PublicCtx, p: ListParams, filterOptions: Record<string, { label: string; options: Record<string, string> }> = {}, placeholder = 'Search…'): string {
  let h = '<form method="get" class="list-toolbar" role="search">';
  h += `<input type="hidden" name="sort" value="${e(p.sort)}"><input type="hidden" name="dir" value="${e(p.dir.toLowerCase())}">`;
  if (ctx.query.has('group_id')) h += `<input type="hidden" name="group_id" value="${Math.trunc(Number(ctx.query.get('group_id')) || 0)}">`;
  h += `<div class="list-search"><label class="sr-only" for="list-q">${e(placeholder)}</label><input type="search" id="list-q" name="q" value="${e(p.q)}" placeholder="${e(placeholder)}"></div>`;
  for (const [key, cfg] of Object.entries(filterOptions)) {
    const current = p.filters[key] ?? '';
    h += `<div class="list-filter"><label class="sr-only" for="list-${e(key)}">${e(cfg.label)}</label><select id="list-${e(key)}" name="${e(key)}" data-list-autosubmit><option value="">All ${e(cfg.label)}</option>`;
    for (const [v, l] of Object.entries(cfg.options)) h += `<option value="${e(v)}"${v === current ? ' selected' : ''}>${e(l)}</option>`;
    h += '</select></div>';
  }
  h += '<button type="submit" class="btn-filter">Search</button>';
  if (p.q !== '' || Object.keys(p.filters).length) {
    const clear: Record<string, null> = { q: null, page: null };
    for (const k of Object.keys(filterOptions)) clear[k] = null;
    h += `<a class="list-clear" href="${e(listUrl(ctx, clear))}">Clear</a>`;
  }
  return h + '</form>';
}

export function viewToggle(moduleKey: string): string {
  return (
    `<div class="view-toggle" role="group" aria-label="Choose view" data-view-toggle="${e(moduleKey)}">` +
    '<button type="button" class="view-toggle-btn" data-view-set="list" aria-pressed="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><line x1="4" y1="5" x2="16" y2="5"/><line x1="4" y1="10" x2="16" y2="10"/><line x1="4" y1="15" x2="16" y2="15"/></svg><span>List</span></button>' +
    '<button type="button" class="view-toggle-btn" data-view-set="grid" aria-pressed="false"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/></svg><span>Grid</span></button>' +
    '</div>'
  );
}

export function listPagination(ctx: PublicCtx, total: number, p: ListParams): string {
  const pages = Math.ceil(total / Math.max(1, p.perPage));
  if (pages <= 1) return '';
  const page = Math.min(p.page, pages);
  const from = p.offset + 1;
  const to = Math.min(p.offset + p.perPage, total);
  let h = `<nav class="list-pagination" aria-label="Pagination"><span class="list-pagination-status">Showing ${from}–${to} of ${total}</span><span class="list-pagination-links">`;
  h += page > 1 ? `<a href="${e(listUrl(ctx, { page: page - 1 }))}" rel="prev">&larr; Prev</a>` : '<span class="is-disabled">&larr; Prev</span>';
  h += `<span class="list-pagination-page">Page ${page} of ${pages}</span>`;
  h += page < pages ? `<a href="${e(listUrl(ctx, { page: page + 1 }))}" rel="next">Next &rarr;</a>` : '<span class="is-disabled">Next &rarr;</span>';
  return h + '</span></nav>';
}

export function bulkToolbar(ctx: Ctx): string {
  const canEdit = can(ctx.session, 'content.edit');
  const canDelete = can(ctx.session, 'content.delete');
  if (!canEdit && !canDelete) return '';
  let options = '';
  if (canEdit) options += '<option value="publish">Publish (set Active)</option><option value="draft">Move to Draft</option>';
  if (canDelete) options += '<option value="delete">Move to Trash</option>';
  return (
    '<div class="bulk-bar" data-bulk-bar hidden><span class="bulk-bar-count" data-bulk-count aria-live="polite"></span>' +
    '<label class="sr-only" for="bulk-action">Bulk action</label><select id="bulk-action" name="bulk_action" form="bulk-form" required>' +
    `<option value="">Choose an action…</option>${options}</select><button type="submit" form="bulk-form" class="btn-filter">Apply</button></div>`
  );
}

export function reorderEnabled(ctx: Ctx, p: ListParams, scopeFilters: string[] = []): boolean {
  if (!can(ctx.session, 'content.edit') || p.sort !== 'default' || p.dir !== 'ASC' || p.q !== '') return false;
  for (const k of scopeFilters) if (!(k in p.filters)) return false;
  const keys = Object.keys(p.filters);
  return (keys.length === scopeFilters.length && keys.every((k, i) => k === scopeFilters[i])) || (scopeFilters.length === 0 && keys.length === 0);
}

export function orderHeader(ctx: PublicCtx, p: ListParams): string {
  return listSortHeader(ctx, 'Order', 'default', p);
}

export function orderCell(order: unknown, duplicates: string[] = [], group = ''): string {
  const o = Math.trunc(Number(order) || 0);
  const dup = duplicates.includes(`${group}|${o}`);
  return (
    `<td class="col-order"><span class="order-number${dup ? ' is-duplicate' : ''}"${dup ? ' title="Another item here has the same order number, so their positions are decided by name instead."' : ''}>${o}</span>` +
    (dup ? '<span class="sr-only"> — shares this order number with another item</span>' : '') +
    '</td>'
  );
}

export function duplicateOrders(rows: Record<string, any>[], groupColumn: string | null = null): string[] {
  const counts = new Map<string, number>();
  for (const r of rows) {
    const k = `${groupColumn === null ? '' : String(r[groupColumn] ?? '')}|${Math.trunc(Number(r.display_order) || 0)}`;
    counts.set(k, (counts.get(k) ?? 0) + 1);
  }
  return [...counts].filter(([, n]) => n > 1).map(([k]) => k);
}

export function reorderCell(label: string): string {
  return `<td class="col-drag"><span class="drag-handle" aria-hidden="true">⠿</span><span class="move-buttons"><button type="button" data-move="up" aria-label="Move ${e(label)} up">▲</button><button type="button" data-move="down" aria-label="Move ${e(label)} down">▼</button></span></td>`;
}

export function reorderTableAttributes(ctx: PublicCtx, enabled: boolean, endpoint = 'reorder.php'): string {
  if (!enabled) return '';
  return ` data-reorder-url="${e(endpoint)}" data-reorder-token="${csrfFieldValue(ctx)}"`;
}

function csrfFieldValue(ctx: PublicCtx): string {
  return e(/value="([^"]+)"/.exec(csrfField(ctx))![1]);
}

export function reorderNotice(ctx: Ctx, p: ListParams): string {
  if (!can(ctx.session, 'content.edit') || reorderEnabled(ctx, p)) return '';
  let bare = ctx.url.pathname.split('/').pop() ?? '';
  if (ctx.query.has('group_id')) bare += '?group_id=' + Math.trunc(Number(ctx.query.get('group_id')) || 0);
  return `<p class="reorder-notice">Drag-to-reorder is available when the list is unfiltered and in its default order. <a href="${e(bare)}">Reset the view</a> to rearrange items.</p>`;
}

/* ----------------------------- media specs ----------------------------- */

export const IMAGE_SPECS: Record<string, { label: string; width: number; height: number; ratio: string; note: string }> = {
  programs: { label: 'Program photo', width: 1200, height: 800, ratio: '3:2', note: 'Used as the project card image and the program page banner. Landscape.' },
  'page-header': {
    label: 'Page header image',
    width: 1920,
    height: 640,
    ratio: '3:1',
    note: 'Shown behind the green banner at the top of the page, under a green tint so the white title stays readable. Wide and fairly even photos work best; the middle is kept when the banner is cropped on phones.',
  },
  'purpose-background': {
    label: 'Core Purpose background',
    width: 1920,
    height: 560,
    ratio: '24:7',
    note: 'Shown behind the "Our Core Purpose" banner on the home page, under a green tint so the white headline stays readable. A calm, wide photo works best; the middle is kept on phones.',
  },
  'hero-slides': {
    label: 'Slideshow photo',
    width: 1920,
    height: 1080,
    ratio: '16:9',
    note: 'Fills the whole first screen of the home page behind the headline, which sits on the left - keep the subject centre or right.',
  },
  team: { label: 'Team photo', width: 600, height: 600, ratio: '1:1', note: 'Shown as a circular portrait. Centre the face — the corners get cropped off by the circle.' },
  gallery: { label: 'Gallery photo', width: 1200, height: 800, ratio: '3:2', note: 'Shown in the gallery grid and the lightbox. Landscape.' },
  reports: { label: 'Report cover', width: 800, height: 1035, ratio: '≈A4 portrait', note: 'The cover thumbnail of the PDF. Portrait, roughly A4 proportions.' },
  'completed-projects': {
    label: 'Project photo',
    width: 1200,
    height: 800,
    ratio: '3:2',
    note: 'Used as the listing card image, the detail page banner, and each gallery photo. Landscape.',
  },
  'completed-projects-map': { label: 'Map image', width: 1200, height: 900, ratio: '4:3', note: 'A static map screenshot shown beside the project gallery, not an interactive map.' },
};

export function imageSpecHelp(key: string): string {
  const s = IMAGE_SPECS[key];
  if (!s) return '';
  return `<p class="field-help"><strong>${s.width} × ${s.height} px</strong> (${e(s.ratio)}) · JPG, PNG or WebP · max ${Math.trunc(UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024))}MB<br>${e(s.note)} Larger images are fine — you can crop and resize after choosing a file.</p>`;
}

export function imageSpecAttributes(key: string): string {
  const s = IMAGE_SPECS[key];
  if (!s) return '';
  return ` data-crop-width="${s.width}" data-crop-height="${s.height}" data-crop-label="${e(s.label)}"`;
}

export function documentSpecHelp(): string {
  return `<p class="field-help">PDF only · max ${Math.trunc(UPLOAD_DOCUMENT_MAX_BYTES / (1024 * 1024))}MB. This is the file visitors download, so keep it under ~10MB where possible.</p>`;
}

export function removeFileCheckbox(field: string, current: string | null | undefined, targetId: string, label = 'Remove current photo'): string {
  if (!current) return '';
  return `<label class="remove-file"><input type="checkbox" name="remove_${e(field)}" value="1" data-remove-toggle="${e(targetId)}"><span>${e(label)}</span></label>`;
}

/* ------------------------------- helpers ------------------------------- */

/** PHP date('M j, Y', strtotime(x)) */
export function fmtDate(v: unknown): string {
  const d = new Date(String(v ?? '').replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export const ucfirst = (s: unknown) => {
  const t = String(s ?? '');
  return t.charAt(0).toUpperCase() + t.slice(1);
};

export function statusBadge(status: unknown): string {
  return `<span class="status-badge status-badge--${e(status)}">${e(ucfirst(status))}</span>`;
}

export function selected(cond: boolean): string {
  return cond ? ' selected' : '';
}
