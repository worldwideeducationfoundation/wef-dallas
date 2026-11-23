/* eslint-disable */
// Markup ported from website/public/admin/modules/impact-milestones/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const milestones: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Impact Goals Roadmap</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Milestone</a>
  ` : ''}
</div>
<p class="field-hint">The 5-point roadmap chart on the Impact Goals page. Only the first 5 active milestones, in this order, appear on the chart — its layout is a fixed 5-point curve, not an arbitrary-length list.</p>
${d.toolbar}
${(!milestones.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No milestones match that search.'
        : 'No milestones yet. Add the first one to get started.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all milestones on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Date', 'date', params)}
          <th>Centers</th>
          <th>Students</th>
          <th>Teachers</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(milestones).map((milestone) => `
          <tr${canReorder ? ' data-id="' + int(milestone.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(milestone.id)}"
                     data-bulk-item aria-label="Select ${e(milestone.milestone_date)}">
            </td>
            ${(canReorder) ? `${reorderCell(milestone.milestone_date)}` : ''}
            ${orderCell(milestone.display_order, dupeOrders)}
            <td class="data-table-primary">${e(milestone.milestone_date)}</td>
            <td>${number_format(int(milestone.centers_target))}</td>
            <td>${number_format(int(milestone.students_target))}</td>
            <td>${number_format(int(milestone.teachers_target))}</td>
            <td>
              <span class="status-badge status-badge--${e(milestone.status)}">${e(ucfirst(milestone.status))}</span>
            </td>
            <td>${e(fmtDate(milestone.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(milestone.id)}" class="link-edit">Edit<span class="sr-only"> ${e(milestone.milestone_date)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete the &quot;${e(milestone.milestone_date)}&quot; milestone? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(milestone.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(milestone.milestone_date)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Impact Goals Roadmap</a>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="milestone_date">Target date (shown as typed, e.g. "May 2027")</label>
    <input type="text" id="milestone_date" name="milestone_date" value="${e(v.milestone_date)}" required maxlength="40">
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="centers_target">Learning centers (cumulative target)</label>
      <input type="number" id="centers_target" name="centers_target" value="${e(String(v.centers_target ?? 0))}" min="0" step="1" required>
    </div>
    <div class="field">
      <label for="students_target">Students impacted (cumulative target)</label>
      <input type="number" id="students_target" name="students_target" value="${e(String(v.students_target ?? 0))}" min="0" step="1" required>
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="teachers_target">Teachers/entrepreneurs trained (cumulative target)</label>
      <input type="number" id="teachers_target" name="teachers_target" value="${e(String(v.teachers_target ?? 0))}" min="0" step="1" required>
    </div>
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
      <p class="field-hint">Only the first 5 active milestones, in this order, appear on the chart.</p>
    </div>
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" required>
      <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft</option>
      <option value="active"${v.status === 'active' ? ' selected' : ''}>Active</option>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Milestone'}</button>
  </div>
</form>
`;
}
