/** Port of admin/modules/team/*.php */
import 'server-only';
import { e } from '../../html';
import { dbFetchAll, dbFetchColumn, dbFetchOne } from '../../db';
import { Redirect, csrfField, requireCapability } from '../context';
import { handleBulk, handleDelete, handleReorder, handleSave } from '../generic';
import { TEAM_SECTIONS } from '../resources';
import { can, flashInputGet, flashSet } from '../session';
import {
  adminPage,
  bulkToolbar,
  duplicateOrders,
  fmtDate,
  imageSpecAttributes,
  imageSpecHelp,
  listOrderLimit,
  listPagination,
  listParams,
  listSortHeader,
  listToolbar,
  listWhere,
  orderCell,
  orderHeader,
  removeFileCheckbox,
  reorderCell,
  reorderEnabled,
  reorderNotice,
  reorderTableAttributes,
  ucfirst,
  viewToggle,
} from '../ui';
import { int, type Routes } from './util';

export const teamRoutes: Routes = {
  'index.php': async (pctx) => {
    const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
    const sortable = { default: 'display_order', name: 'name', role: 'role', section: 'section', status: 'status', updated: 'updated_at' };
    const params = listParams(ctx, sortable, 'default', ['status', 'section']);
    const where = listWhere(params, ['name', 'role', 'bio'], { status: 'status', section: 'section' }, ['deleted_at IS NULL']);
    const total = Number(await dbFetchColumn('SELECT COUNT(*) FROM team_members' + where.sql, where.params, 0));
    const members = await dbFetchAll(
      'SELECT id, name, role, section, image_path, display_order, status, updated_at FROM team_members' + where.sql + listOrderLimit(params, 'name'),
      where.params,
    );
    const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
    const canReorder = reorderEnabled(ctx, params);
    const dupeOrders = duplicateOrders(members);
    const canDelete = can(ctx.session, 'content.delete');
    const deleteForm = (m: Record<string, any>) =>
      canDelete
        ? `<form method="post" action="delete.php" data-confirm="Delete &quot;${e(m.name)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(m.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(m.name)}</span></button>
              </form>`
        : '';
    const body = `
<div class="module-header">
  <h1>Team</h1>
  ${can(ctx.session, 'content.create') ? `<a href="form.php" class="btn-primary">+ Add Team Member</a>` : ''}
</div>
${listToolbar(
  ctx,
  params,
  {
    section: { label: 'Sections', options: Object.fromEntries(TEAM_SECTIONS.map((x) => [x, x])) },
    status: { label: 'Statuses', options: { active: 'Active', draft: 'Draft' } },
  },
  'Search name, role, or bio…',
)}
${
  !members.length
    ? `<div class="empty-state">${isFiltered ? 'No team members match that search.' : 'No team members yet. Add the first one to get started.'}</div>`
    : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('team')}
  <div class="listing-view" data-view-key="team">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" data-bulk-all aria-label="Select all team members on this page"></th>
          ${canReorder ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Photo</span></th>
          ${listSortHeader(ctx, 'Name', 'name', params)}
          ${listSortHeader(ctx, 'Role', 'role', params)}
          ${listSortHeader(ctx, 'Section', 'section', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${members
          .map(
            (m) => `
          <tr${canReorder ? ` data-id="${int(m.id)}" draggable="true"` : ''}>
            <td class="col-check"><input type="checkbox" name="ids[]" form="bulk-form" value="${int(m.id)}" data-bulk-item aria-label="Select ${e(m.name)}"></td>
            ${canReorder ? reorderCell(m.name) : ''}
            ${orderCell(m.display_order, dupeOrders)}
            <td>${
              m.image_path
                ? `<img src="../../uploads/${e(m.image_path)}" alt="" class="data-table-avatar" loading="lazy">`
                : `<span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">${e(String(m.name).charAt(0).toUpperCase())}</span>`
            }</td>
            <td class="data-table-primary">${e(m.name)}</td>
            <td>${e(m.role)}</td>
            <td class="data-table-secondary">${e(m.section)}</td>
            <td><span class="status-badge status-badge--${e(m.status)}">${e(ucfirst(m.status))}</span></td>
            <td>${e(fmtDate(m.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(m.id)}" class="link-edit">Edit<span class="sr-only"> ${e(m.name)}</span></a>
              ${deleteForm(m)}
            </td>
          </tr>`,
          )
          .join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}
  </div>
  <div class="view-panel view-panel--grid">
    <div class="grid-view-cards">
      ${members
        .map(
          (m) => `
        <div class="grid-card">
          <label class="grid-card-select"><span class="sr-only">Select ${e(m.name)}</span><input type="checkbox" name="ids[]" form="bulk-form" value="${int(m.id)}" data-bulk-item></label>
          <div class="grid-card-media">${
            m.image_path
              ? `<img src="../../uploads/${e(m.image_path)}" alt="" loading="lazy">`
              : `<div class="grid-card-media--empty" aria-hidden="true">${e(String(m.name).charAt(0).toUpperCase())}</div>`
          }</div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(m.name)}</div>
            <div class="grid-card-meta">${e(m.role)} &middot; ${e(m.section)}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(m.status)}">${e(ucfirst(m.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(m.id)}" class="link-edit">Edit<span class="sr-only"> ${e(m.name)}</span></a>
                ${deleteForm(m)}
              </div>
            </div>
          </div>
        </div>`,
        )
        .join('')}
    </div>
  </div>
  </div>`
}`;
    return adminPage(ctx, { title: 'Team', activeNav: 'Team', body });
  },

  'form.php': async (pctx) => {
    const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
    const id = ctx.query.has('id') ? Math.trunc(Number(ctx.query.get('id'))) || 0 : null;
    const isEdit = id !== null;
    let existing: Record<string, any> | null = null;
    if (isEdit) {
      existing = await dbFetchOne('SELECT * FROM team_members WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id });
      if (!existing) {
        flashSet(ctx.session, 'error', 'That team member could not be found.');
        throw new Redirect('index.php');
      }
    }
    const pageTitle = isEdit ? 'Edit Team Member' : 'Add Team Member';
    const v: Record<string, any> = flashInputGet(ctx.session) ?? existing ?? { name: '', role: '', bio: '', display_order: 0, status: 'draft' };
    const body = `
<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Team</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${isEdit ? `<input type="hidden" name="id" value="${id}">` : ''}
  <div class="form-row-2">
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="${e(v.name)}" required maxlength="160">
    </div>
    <div class="field">
      <label for="role">Role / Title</label>
      <input type="text" id="role" name="role" value="${e(v.role)}" required maxlength="160">
    </div>
  </div>
  <div class="field">
    <label for="section">Section</label>
    <select id="section" name="section" required>
      ${TEAM_SECTIONS.map((s) => `<option value="${e(s)}"${(v.section ?? TEAM_SECTIONS[0]) === s ? ' selected' : ''}>${e(s)}</option>`).join('')}
    </select>
    <p class="field-help">Which heading this person appears under on the public Our Team page.</p>
  </div>
  <div class="field">
    <label for="bio">Bio (optional)</label>
    <textarea id="bio" name="bio" rows="8" maxlength="4000">${e(v.bio ?? '')}</textarea>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft</option>
        <option value="active"${v.status === 'active' ? ' selected' : ''}>Active</option>
      </select>
    </div>
  </div>
  <div class="field">
    <label for="image">Photo</label>
    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('team')}>
    ${imageSpecHelp('team')}
    ${
      isEdit && existing?.image_path
        ? `<div class="current-image" id="current-image">
        <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
        <span>Current photo — upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}`
        : ''
    }
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Add Team Member'}</button>
  </div>
</form>`;
    return adminPage(ctx, { title: pageTitle, activeNav: 'Team', body });
  },

  'save.php': (ctx) => handleSave(ctx, 'team'),
  'delete.php': (ctx) => handleDelete(ctx, 'team'),
  'bulk.php': (ctx) => handleBulk(ctx, 'team'),
  'reorder.php': (ctx) => handleReorder(ctx, 'team'),
};

