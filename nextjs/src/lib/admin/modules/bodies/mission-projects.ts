/* eslint-disable */
// Markup ported from website/public/admin/modules/mission-projects/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const projects: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Mission Projects</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Project</a>
  ` : ''}
</div>
<p class="field-hint">The "WEF At A Glance" table on the Our Mission page — WHAT / WHERE / WHO / IMPACT / NOTES.</p>
${d.toolbar}
${(!projects.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No mission projects match that search.'
        : 'No mission projects yet. Add the first one to get started.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all mission projects on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Project', 'title', params)}
          <th>Launched</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(projects).map((project) => `
          <tr${canReorder ? ' data-id="' + int(project.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(project.id)}"
                     data-bulk-item aria-label="Select ${e(project.what_header)}">
            </td>
            ${(canReorder) ? `${reorderCell(project.what_header)}` : ''}
            ${orderCell(project.display_order, dupeOrders)}
            <td class="data-table-primary">
              ${e(project.what_header)}
              ${(Boolean(project.what_sub)) ? `<br><span class="field-hint" style="margin-top:0;">${e(project.what_sub)}</span>` : ''}
            </td>
            <td>${e(project.launch_year ?? '')}</td>
            <td>
              <span class="status-badge status-badge--${e(project.status)}">${e(ucfirst(project.status))}</span>
            </td>
            <td>${e(fmtDate(project.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(project.id)}" class="link-edit">Edit<span class="sr-only"> ${e(project.what_header)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(project.what_header)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(project.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(project.what_header)}</span></button>
              </form>
              ` : ''}
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}
`}
`;
}

export function formBody(ctx: Ctx, d: Record<string, any>): string {
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Mission Projects</a>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="what_header">Project name (WHAT column heading)</label>
    <input type="text" id="what_header" name="what_header" value="${e(v.what_header)}" required maxlength="200">
  </div>
  <div class="field">
    <label for="what_sub">Subtitle (shown under the project name)</label>
    <input type="text" id="what_sub" name="what_sub" value="${e(v.what_sub ?? '')}" maxlength="400">
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="launch_year">Launch year</label>
      <input type="text" id="launch_year" name="launch_year" value="${e(v.launch_year ?? '')}" maxlength="20" placeholder="e.g. 2025">
      <p class="field-hint">Rendered on the page as "(Launched in ${e(v.launch_year || '2025')})" — enter just the year.</p>
    </div>
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
  </div>
  <div class="field">
    <label for="where_text">WHERE (one bullet per line)</label>
    <textarea id="where_text" name="where_text" rows="3">${e(v.where_text ?? '')}</textarea>
  </div>
  <div class="field">
    <label for="who_text">WHO (one bullet per line)</label>
    <textarea id="who_text" name="who_text" rows="3">${e(v.who_text ?? '')}</textarea>
  </div>
  <div class="field">
    <label for="impact_text">IMPACT (one bullet per line)</label>
    <textarea id="impact_text" name="impact_text" rows="3">${e(v.impact_text ?? '')}</textarea>
  </div>
  <div class="field">
    <label for="notes_text">NOTES (one bullet per line)</label>
    <textarea id="notes_text" name="notes_text" rows="3">${e(v.notes_text ?? '')}</textarea>
  </div>
  <div class="field">
    <label for="notes_highlight">NOTES highlight box (optional)</label>
    <textarea id="notes_highlight" name="notes_highlight" rows="2">${e(v.notes_highlight ?? '')}</textarea>
    <p class="field-hint">A bordered callout under the NOTES bullets — usually an investment or funding figure. Leave blank if this project doesn't have one.</p>
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" required>
      <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft</option>
      <option value="active"${v.status === 'active' ? ' selected' : ''}>Active</option>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Mission Project'}</button>
  </div>
</form>
`;
}
