/** Port of admin/modules/trash/{index,restore,purge}.php */
import 'server-only';
import { e } from '../../html';
import { dbFetchOne } from '../../db';
import { logActivity } from '../activity';
import { purgeItems, restoreItems, trashedItems } from '../crud';
import { Redirect, csrfField, requireCapability, requireLogin, requirePostWithCsrf, type PublicCtx } from '../context';
import { RESOURCES } from '../resources';
import { trashSubmissions } from '../site-admin';
import { can, flashSet } from '../session';
import { adminPage, fmtDate, listPagination, listParams } from '../ui';
import { int, type Routes } from './util';

type TrashType = { table: string; labelColumn: string; module: string; viewCap: string; restoreCap: string; purgeCap: string };

function trashType(type: string): TrashType | null {
  if (type === 'submissions')
    return { table: 'form_submissions', labelColumn: 'name', module: 'submissions', viewCap: 'submissions.view', restoreCap: 'submissions.manage', purgeCap: 'submissions.manage' };
  const r = Object.prototype.hasOwnProperty.call(RESOURCES, type) ? RESOURCES[type] : null;
  if (!r || !r.softDelete) return null;
  return { table: r.table, labelColumn: r.labelColumn, module: r.module, viewCap: 'content.view', restoreCap: 'content.edit', purgeCap: 'content.delete' };
}

const time = (v: string) => new Date(v.replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }).toLowerCase();

async function index(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const params = listParams(ctx, { default: 'deleted_at' }, 'default');
  const all = (await trashedItems()).filter((i) => can(ctx.session, trashType(i.type)?.viewCap ?? 'content.view'));
  const total = all.length;
  const items = all.slice(params.offset, params.offset + params.perPage);
  const body = `
<div class="module-header">
  <h1>Trash</h1>
</div>
<div class="welcome-card">
  <p>
    Deleted content is kept here instead of being removed immediately.
    <strong>Restore</strong> puts an item back exactly as it was, including its photo.
    <strong>Delete permanently</strong> is the only action that actually removes anything —
    including the uploaded files, which is why it cannot be undone.
  </p>
</div>
${
  !items.length
    ? `<div class="empty-state">Trash is empty. Deleted content will appear here.</div>`
    : `
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr><th>Item</th><th>Type</th><th>Deleted</th><th><span class="sr-only">Actions</span></th></tr>
      </thead>
      <tbody>
        ${items
          .map((item) => {
            const t = trashType(item.type)!;
            return `
          <tr>
            <td class="data-table-primary">${e(item.label)}</td>
            <td>${e(item.typeLabel)}</td>
            <td>
              <div>${e(fmtDate(item.deleted_at))}</div>
              <div class="data-table-secondary">${e(time(item.deleted_at))}</div>
            </td>
            <td class="data-table-actions">
              ${
                can(ctx.session, t.restoreCap)
                  ? `<form method="post" action="restore.php">
                  ${csrfField(ctx)}
                  <input type="hidden" name="type" value="${e(item.type)}">
                  <input type="hidden" name="id" value="${int(item.id)}">
                  <button type="submit" class="link-edit">Restore<span class="sr-only"> ${e(item.label)}</span></button>
                </form>`
                  : ''
              }
              ${
                can(ctx.session, t.purgeCap)
                  ? `<form method="post" action="purge.php" data-confirm="Permanently delete &quot;${e(item.label)}&quot;? This removes the record and its uploaded files for good. It cannot be undone.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="type" value="${e(item.type)}">
                  <input type="hidden" name="id" value="${int(item.id)}">
                  <button type="submit" class="link-delete">Delete permanently<span class="sr-only"> ${e(item.label)}</span></button>
                </form>`
                  : ''
              }
            </td>
          </tr>`;
          })
          .join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}`
}`;
  return adminPage(ctx, { title: 'Trash', activeNav: 'Trash', body });
}

async function act(pctx: PublicCtx, mode: 'restore' | 'purge'): Promise<never> {
  const ctx = await requireLogin(pctx);
  requirePostWithCsrf(ctx);
  const type = String(ctx.post.type ?? '');
  const id = int(ctx.post.id ?? 0);
  const t = trashType(type);
  const fail = mode === 'restore' ? 'That item could not be restored.' : 'That item could not be deleted.';
  if (!t || id <= 0) {
    flashSet(ctx.session, 'error', fail);
    throw new Redirect('index.php');
  }
  await requireCapability(ctx, mode === 'restore' ? t.restoreCap : t.purgeCap);
  const row = await dbFetchOne(`SELECT ${t.labelColumn} AS label FROM ${t.table} WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1`, { id });
  if (!row) {
    flashSet(ctx.session, 'error', 'That item is no longer in Trash.');
    throw new Redirect('index.php');
  }
  const label = String(row.label ?? '');
  const actor = { userId: ctx.user.id, meta: ctx.meta, via: 'admin' as const };
  let n: number;
  if (type === 'submissions') {
    if (mode === 'restore') n = await trashSubmissions([id], actor, true);
    else {
      const { dbExecute } = await import('../../db');
      n = await dbExecute('DELETE FROM form_submissions WHERE id = :id AND deleted_at IS NOT NULL', { id });
      if (n) await logActivity(ctx.user.id, 'purge', 'submissions', label, ctx.meta);
    }
  } else {
    n = mode === 'restore' ? await restoreItems(RESOURCES[type], [id], actor) : await purgeItems(RESOURCES[type], [id], actor);
  }
  if (n > 0) {
    if (mode === 'restore')
      flashSet(
        ctx.session,
        'success',
        type === 'submissions' ? `Submission from "${label}" restored.` : `"${label}" restored. Check its status if it should not be public yet.`,
      );
    else flashSet(ctx.session, 'success', `"${label}" was permanently deleted.`);
  } else flashSet(ctx.session, 'error', fail);
  throw new Redirect('index.php');
}

export const trashRoutes: Routes = {
  'index.php': index,
  'restore.php': (ctx) => act(ctx, 'restore'),
  'purge.php': (ctx) => act(ctx, 'purge'),
};
