/** Port of admin/modules/sponsors/*.php (Honor Board: groups and their sponsors). */
import 'server-only';
import { e } from '../../html';
import { dbFetchAll, dbFetchColumn, dbFetchOne } from '../../db';
import { Redirect, csrfField, requireCapability, type Ctx, type PublicCtx } from '../context';
import { handleBulk, handleDelete, handleReorder, handleSave } from '../generic';
import { can, flashInputGet, flashSet } from '../session';
import {
  adminPage,
  bulkToolbar,
  duplicateOrders,
  fmtDate,
  listOrderLimit,
  listPagination,
  listParams,
  listSortHeader,
  listToolbar,
  listWhere,
  orderCell,
  orderHeader,
  reorderCell,
  reorderEnabled,
  reorderNotice,
  reorderTableAttributes,
  ucfirst,
} from '../ui';
import { STATUS_FILTER } from './simple';
import { int, type Routes } from './util';

const NAV = 'Honor Board / Sponsors';
const statusOptions = (v: Record<string, any>) =>
  `<option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft</option>
        <option value="active"${v.status === 'active' ? ' selected' : ''}>Active</option>`;

async function groupsIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const sortable = { default: 'g.display_order', title: 'g.title', sponsors: 'sponsor_count', status: 'g.status', updated: 'g.updated_at' };
  const params = listParams(ctx, sortable, 'default', ['status']);
  const where = listWhere(params, ['g.title', 'g.description'], { status: 'g.status' }, ['g.deleted_at IS NULL']);
  const total = Number(await dbFetchColumn('SELECT COUNT(*) FROM sponsor_groups g' + where.sql, where.params, 0));
  const groups = await dbFetchAll(
    `SELECT g.id, g.title, g.display_order, g.status, g.updated_at, COUNT(s.id) AS sponsor_count
     FROM sponsor_groups g LEFT JOIN sponsors s ON s.group_id = g.id` +
      where.sql +
      ' GROUP BY g.id, g.title, g.display_order, g.status, g.updated_at' +
      listOrderLimit(params, 'g.title'),
    where.params,
  );
  const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
  const dupeOrders = duplicateOrders(groups);
  const body = `
<div class="module-header">
  <h1>Honor Board / Sponsors</h1>
  ${can(ctx.session, 'content.create') ? `<a href="group-form.php" class="btn-primary">+ Add Sponsor Group</a>` : ''}
</div>
${listToolbar(ctx, params, STATUS_FILTER, 'Search groups…')}
${
  !groups.length
    ? `<div class="empty-state">${isFiltered ? 'No sponsor groups match that search.' : 'No sponsor groups yet. Add the first one to get started.'}</div>`
    : `
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Group', 'title', params)}
          ${listSortHeader(ctx, 'Sponsors', 'sponsors', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${groups
          .map(
            (group) => `
          <tr>
            ${orderCell(group.display_order, dupeOrders)}
            <td class="data-table-primary">${e(group.title)}</td>
            <td><a href="sponsors.php?group_id=${int(group.id)}" class="link-edit">${int(group.sponsor_count)} sponsor${int(group.sponsor_count) === 1 ? '' : 's'}</a></td>
            <td><span class="status-badge status-badge--${e(group.status)}">${e(ucfirst(group.status))}</span></td>
            <td>${e(fmtDate(group.updated_at))}</td>
            <td class="data-table-actions">
              <a href="group-form.php?id=${int(group.id)}" class="link-edit">Edit<span class="sr-only"> ${e(group.title)}</span></a>
              ${
                can(ctx.session, 'content.delete')
                  ? `<form method="post" action="group-delete.php" data-confirm="Delete &quot;${e(group.title)}&quot; and its ${int(group.sponsor_count)} sponsor(s)? They will move to Trash together, and restoring the group brings them back.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(group.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(group.title)}</span></button>
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
  return adminPage(ctx, { title: 'Honor Board / Sponsors', activeNav: NAV, body });
}

async function sponsorsList(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const groupId = ctx.query.has('group_id') ? int(ctx.query.get('group_id')) : 0;
  if (!groupId) {
    flashSet(ctx.session, 'error', 'No sponsor group specified.');
    throw new Redirect('index.php');
  }
  const group = await dbFetchOne('SELECT id, title FROM sponsor_groups WHERE id = :id LIMIT 1', { id: groupId });
  if (!group) {
    flashSet(ctx.session, 'error', 'That sponsor group could not be found.');
    throw new Redirect('index.php');
  }
  const sortable = { default: 'display_order', name: 'name', badge: 'badge_text', status: 'status', updated: 'updated_at' };
  const params = listParams(ctx, sortable, 'default', ['status']);
  const where = listWhere(params, ['name', 'badge_text'], { status: 'status' }, ['deleted_at IS NULL']);
  const whereSql = (where.sql === '' ? ' WHERE ' : where.sql + ' AND ') + 'group_id = :group_id';
  const bound = { ...where.params, group_id: groupId };
  const total = Number(await dbFetchColumn('SELECT COUNT(*) FROM sponsors' + whereSql, bound, 0));
  const sponsors = await dbFetchAll('SELECT id, name, badge_text, display_order, status, updated_at FROM sponsors' + whereSql + listOrderLimit(params, 'name'), bound);
  const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
  const canReorder = reorderEnabled(ctx, params);
  const dupeOrders = duplicateOrders(sponsors);
  const body = `
<a href="index.php" class="link-back">&larr; Back to Honor Board</a>
<div class="module-header">
  <h1>${e(group.title)}</h1>
  ${can(ctx.session, 'content.create') ? `<a href="sponsor-form.php?group_id=${groupId}" class="btn-primary">+ Add Sponsor</a>` : ''}
</div>
${listToolbar(ctx, params, STATUS_FILTER, 'Search sponsors…')}
${
  !sponsors.length
    ? `<div class="empty-state">${isFiltered ? 'No sponsors in this group match that search.' : 'No sponsors in this group yet. Add the first one to get started.'}</div>`
    : `
  <form method="post" action="sponsor-bulk.php" id="bulk-form">
    ${csrfField(ctx)}
    <input type="hidden" name="group_id" value="${groupId}">
  </form>
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder, 'sponsor-reorder.php')}>
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" data-bulk-all aria-label="Select all sponsors on this page"></th>
          ${canReorder ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Name', 'name', params)}
          ${listSortHeader(ctx, 'Badge', 'badge', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${sponsors
          .map(
            (sponsor) => `
          <tr${canReorder ? ` data-id="${int(sponsor.id)}" draggable="true"` : ''}>
            <td class="col-check"><input type="checkbox" name="ids[]" form="bulk-form" value="${int(sponsor.id)}" data-bulk-item aria-label="Select ${e(sponsor.name)}"></td>
            ${canReorder ? reorderCell(sponsor.name) : ''}
            ${orderCell(sponsor.display_order, dupeOrders)}
            <td class="data-table-primary">${e(sponsor.name)}</td>
            <td>${e(sponsor.badge_text ?? '—')}</td>
            <td><span class="status-badge status-badge--${e(sponsor.status)}">${e(ucfirst(sponsor.status))}</span></td>
            <td>${e(fmtDate(sponsor.updated_at))}</td>
            <td class="data-table-actions">
              <a href="sponsor-form.php?id=${int(sponsor.id)}" class="link-edit">Edit<span class="sr-only"> ${e(sponsor.name)}</span></a>
              ${
                can(ctx.session, 'content.delete')
                  ? `<form method="post" action="sponsor-delete.php" data-confirm="Delete &quot;${e(sponsor.name)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(sponsor.id)}">
                <input type="hidden" name="group_id" value="${groupId}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(sponsor.name)}</span></button>
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
  return adminPage(ctx, { title: String(group.title), activeNav: NAV, body });
}

async function loadForEdit(ctx: Ctx, table: string, notFound: string) {
  const id = ctx.query.has('id') ? int(ctx.query.get('id')) : null;
  let existing: Record<string, any> | null = null;
  if (id !== null) {
    existing = await dbFetchOne(`SELECT * FROM ${table} WHERE id = :id AND deleted_at IS NULL LIMIT 1`, { id });
    if (!existing) {
      flashSet(ctx.session, 'error', notFound);
      throw new Redirect('index.php');
    }
  }
  return { id, isEdit: id !== null, existing };
}

async function groupForm(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const { id, isEdit, existing } = await loadForEdit(ctx, 'sponsor_groups', 'That sponsor group could not be found.');
  const pageTitle = isEdit ? 'Edit Sponsor Group' : 'Add Sponsor Group';
  const v: Record<string, any> = flashInputGet(ctx.session) ?? existing ?? { title: '', description: '', display_order: 0, status: 'draft' };
  const body = `
<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Honor Board</a>
</div>
<form method="post" action="group-save.php" class="admin-form">
  ${csrfField(ctx)}
  ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ''}
  <div class="field">
    <label for="title">Group Title</label>
    <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="255" placeholder="e.g. Sponsors of Bright Beginnings: Early Childhood Learning Centers">
  </div>
  <div class="field">
    <label for="description">Description (optional)</label>
    <textarea id="description" name="description" rows="3">${e(v.description ?? '')}</textarea>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        ${statusOptions(v)}
      </select>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Group'}</button>
  </div>
</form>`;
  return adminPage(ctx, { title: pageTitle, activeNav: NAV, body });
}

async function sponsorForm(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const { id, isEdit, existing } = await loadForEdit(ctx, 'sponsors', 'That sponsor could not be found.');
  const defaultGroupId = isEdit ? int(existing!.group_id) : int(ctx.query.get('group_id') ?? 0);
  if (!isEdit && !defaultGroupId) {
    flashSet(ctx.session, 'error', 'No sponsor group specified.');
    throw new Redirect('index.php');
  }
  const pageTitle = isEdit ? 'Edit Sponsor' : 'Add Sponsor';
  const v: Record<string, any> = flashInputGet(ctx.session) ?? existing ?? { name: '', badge_text: '', group_id: defaultGroupId, display_order: 0, status: 'draft' };
  const groups = await dbFetchAll('SELECT id, title FROM sponsor_groups ORDER BY display_order, title');
  const backLink = 'sponsors.php?group_id=' + defaultGroupId;
  const body = `
<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="${e(backLink)}" class="link-back">&larr; Back to Sponsors</a>
</div>
<form method="post" action="sponsor-save.php" class="admin-form">
  ${csrfField(ctx)}
  ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ''}
  <div class="field">
    <label for="group_id">Sponsor Group</label>
    <select id="group_id" name="group_id" required>
      ${groups.map((g) => `<option value="${int(g.id)}"${int(v.group_id) === int(g.id) ? ' selected' : ''}>${e(g.title)}</option>`).join('')}
    </select>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="name">Sponsor Name</label>
      <input type="text" id="name" name="name" value="${e(v.name)}" required maxlength="255">
    </div>
    <div class="field">
      <label for="badge_text">Badge (optional)</label>
      <input type="text" id="badge_text" name="badge_text" value="${e(v.badge_text ?? '')}" maxlength="60" placeholder="e.g. 04 Centers">
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        ${statusOptions(v)}
      </select>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Add Sponsor'}</button>
  </div>
</form>`;
  return adminPage(ctx, { title: pageTitle, activeNav: NAV, body });
}

const groupBack = (ctx: PublicCtx) => {
  const g = int(ctx.post.group_id ?? 0);
  return g > 0 ? `sponsors.php?group_id=${g}` : 'index.php';
};

export const sponsorsRoutes: Routes = {
  'index.php': groupsIndex,
  'sponsors.php': sponsorsList,
  'group-form.php': groupForm,
  'sponsor-form.php': sponsorForm,
  'group-save.php': (ctx) => handleSave(ctx, 'sponsor-groups', { formPath: 'group-form.php' }),
  'sponsor-save.php': (ctx) =>
    handleSave(ctx, 'sponsors', { formPath: 'sponsor-form.php', afterSave: async (c) => `sponsors.php?group_id=${int(c.post.group_id)}` }),
  'group-delete.php': (ctx) => handleDelete(ctx, 'sponsor-groups'),
  'sponsor-delete.php': (ctx) => handleDelete(ctx, 'sponsors', groupBack(ctx)),
  'sponsor-bulk.php': (ctx) => handleBulk(ctx, 'sponsors', groupBack(ctx)),
  'sponsor-reorder.php': (ctx) => handleReorder(ctx, 'sponsors'),
};
