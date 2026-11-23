/* eslint-disable */
// Markup ported from website/public/admin/modules/reports/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const reports: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Annual Reports</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Report</a>
  ` : ''}
</div>
${d.toolbar}
${(!reports.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No reports match that search.'
        : 'No annual reports yet. Add the first one to get started.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all reports on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Cover</span></th>
          ${listSortHeader(ctx, 'Title', 'title', params)}
          <th>PDF</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(reports).map((report) => `
          <tr${canReorder ? ' data-id="' + int(report.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(report.id)}"
                     data-bulk-item aria-label="Select ${e(report.title)}">
            </td>
            ${(canReorder) ? `${reorderCell(report.title)}` : ''}
            ${orderCell(report.display_order, dupeOrders)}
            <td>
              ${(report.cover_image_path) ? `
                <img src="../../uploads/${e(report.cover_image_path)}" alt="" class="data-table-avatar" loading="lazy">
              ` : `
                <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
              `}
            </td>
            <td class="data-table-primary">${e(report.title)}</td>
            <td>
              ${(report.pdf_path) ? `
                <a class="link-edit" href="../../uploads/${e(report.pdf_path)}" target="_blank" rel="noopener">Attached</a>
              ` : `
                <span class="status-badge status-badge--draft">Missing</span>
              `}
            </td>
            <td>
              <span class="status-badge status-badge--${e(report.status)}">${e(ucfirst(report.status))}</span>
            </td>
            <td>${e(fmtDate(report.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(report.id)}" class="link-edit">Edit<span class="sr-only"> ${e(report.title)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(report.title)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(report.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(report.title)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Annual Reports</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="160" placeholder="e.g. Annual Report 2024 – 2025">
  </div>
  <div class="field">
    <label for="description">Description (optional)</label>
    <textarea id="description" name="description" rows="3" maxlength="2000">${e(v.description ?? '')}</textarea>
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
  <div class="form-row-2">
    <div class="field">
      <label for="cover_image">Cover Image (optional)</label>
      <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('reports')}>
      ${imageSpecHelp('reports')}
      ${(isEdit && Boolean(existing.cover_image_path)) ? `
        <div class="current-image" id="current-cover-image">
          <img src="../../uploads/${e(existing.cover_image_path)}" alt="Current cover">
          <span>Current cover — upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('cover_image', existing.cover_image_path, 'current-cover-image', 'Remove current cover image')}
      ` : ''}
    </div>
    <div class="field">
      <label for="pdf">Report PDF${isEdit ? ' (optional)' : ''}</label>
      <input type="file" id="pdf" name="pdf" accept=".pdf"${isEdit ? '' : ' required'}>
      ${documentSpecHelp()}
      ${(isEdit && Boolean(existing.pdf_path)) ? `
        <div class="current-image">
          <span>Current file: ${e(basename(existing.pdf_path))} — upload a new file to replace it.</span>
        </div>
      ` : ''}
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Report'}</button>
  </div>
</form>
`;
}
