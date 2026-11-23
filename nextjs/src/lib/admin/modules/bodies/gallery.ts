/* eslint-disable */
// Markup ported from website/public/admin/modules/gallery/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const items: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Gallery</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Photo</a>
  ` : ''}
</div>
${d.toolbar}
${(!items.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No photos match that search.'
        : 'No gallery photos yet. Add the first one to get started.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('gallery')}
  <div class="listing-view" data-view-key="gallery">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all photos on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Photo</span></th>
          ${listSortHeader(ctx, 'Caption', 'caption', params)}
          <th>Project</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(items).map((item) => `
          <tr${canReorder ? ' data-id="' + int(item.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(item.id)}"
                     data-bulk-item aria-label="Select ${e(item.caption)}">
            </td>
            ${(canReorder) ? `${reorderCell(item.caption)}` : ''}
            ${orderCell(item.display_order, dupeOrders)}
            <td>
              ${(item.image_path) ? `
                <img src="../../uploads/${e(item.image_path)}" alt="" class="data-table-avatar" loading="lazy">
              ` : `
                <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
              `}
            </td>
            <td class="data-table-primary">${e(item.caption)}</td>
            <td>${e(item.project_name ?? 'General')}</td>
            <td>
              <span class="status-badge status-badge--${e(item.status)}">${e(ucfirst(item.status))}</span>
            </td>
            <td>${e(fmtDate(item.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(item.id)}" class="link-edit">Edit<span class="sr-only"> ${e(item.caption)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(item.caption)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(item.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(item.caption)}</span></button>
              </form>
              ` : ''}
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}
  </div>
  <div class="view-panel view-panel--grid">
    <div class="grid-view-cards">
      ${Object.values(items).map((item) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(item.caption)}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(item.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media">
            ${(Boolean(item.image_path)) ? `
              <img src="../../uploads/${e(item.image_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">—</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(item.caption)}</div>
            <div class="grid-card-meta">${e(item.project_name ?? 'General')}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(item.status)}">${e(ucfirst(item.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(item.id)}" class="link-edit">Edit<span class="sr-only"> ${e(item.caption)}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete &quot;${e(item.caption)}&quot;? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(item.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(item.caption)}</span></button>
                </form>
                ` : ''}
              </div>
            </div>
          </div>
        </div>
      `).join('')}
    </div>
  </div>
  </div>
`}
`;
}

export function formBody(ctx: Ctx, d: Record<string, any>): string {
  const projects: Record<string, any>[] = d.projects;
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Gallery</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="caption">Caption</label>
    <input type="text" id="caption" name="caption" value="${e(v.caption)}" required maxlength="160" placeholder="e.g. Graduation Day">
  </div>
  <div class="field">
    <label for="program_id">Project</label>
    <select id="program_id" name="program_id">
      <option value="">General (no specific project)</option>
      ${Object.values(projects).map((project) => `
        <option value="${int(project.id)}"${int(v.program_id ?? 0) === int(project.id) ? ' selected' : ''}>${e(project.name)}</option>
      `).join('')}
    </select>
    <p class="field-hint">The Gallery page shows the project's name on the photo and lets visitors filter by project.</p>
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
    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${isEdit ? '' : ' required'}${imageSpecAttributes('gallery')}>
    ${imageSpecHelp('gallery')}
    ${(isEdit && Boolean(existing.image_path)) ? `
      <div class="current-image" id="current-image">
        <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
        <span>Current photo — upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}
    ` : ''}
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Add Photo'}</button>
  </div>
</form>
`;
}
