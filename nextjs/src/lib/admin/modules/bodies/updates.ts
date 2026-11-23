/* eslint-disable */
// Markup ported from website/public/admin/modules/updates/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const updates: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Project Updates</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Update</a>
  ` : ''}
</div>
${d.toolbar}
${(!updates.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No updates match that search.'
        : 'No project updates yet. Add the first one to get started.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all updates on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Image</span></th>
          ${listSortHeader(ctx, 'Title', 'title', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(updates).map((update) => `
          <tr${canReorder ? ' data-id="' + int(update.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(update.id)}"
                     data-bulk-item aria-label="Select ${e(update.title)}">
            </td>
            ${(canReorder) ? `${reorderCell(update.title)}` : ''}
            ${orderCell(update.display_order, dupeOrders)}
            <td>
              ${(update.image_path) ? `
                <img src="../../uploads/${e(update.image_path)}" alt="" class="data-table-avatar" loading="lazy">
              ` : `
                <span class="data-table-avatar data-table-avatar--empty" aria-hidden="true">—</span>
              `}
            </td>
            <td class="data-table-primary">${e(update.title)}</td>
            <td>
              <span class="status-badge status-badge--${e(update.status)}">${e(ucfirst(update.status))}</span>
            </td>
            <td>${e(fmtDate(update.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(update.id)}" class="link-edit">Edit<span class="sr-only"> ${e(update.title)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(update.title)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(update.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(update.title)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Project Updates</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="form-row-2">
    <div class="field">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="160" placeholder="e.g. New School Opening in Chitral">
    </div>
    <div class="field">
      <label for="slug">URL Slug (leave blank to auto-generate)</label>
      <input type="text" id="slug" name="slug" value="${e(v.slug)}" maxlength="160" placeholder="e.g. new-school-opening">
    </div>
  </div>
  <div class="field">
    <label for="content">Content</label>
    <textarea id="content" name="content" rows="6" required>${e(v.content ?? '')}</textarea>
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
    <label for="image">Cover Image (optional)</label>
    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('programs')}>
    ${imageSpecHelp('programs')}
    ${(isEdit && Boolean(existing.image_path)) ? `
      <div class="current-image" id="current-image">
        <img src="../../uploads/${e(existing.image_path)}" alt="Current image">
        <span>Current image — upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current image')}
    ` : ''}
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Update'}</button>
  </div>
</form>
`;
}
