/* eslint-disable */
// Markup ported from website/public/admin/modules/donate-options/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const programs: Record<string, any> = d.programs; const optionName = d.optionName as (o: any) => string; const projectNote = d.projectNote as (o: any) => string;
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const options: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <div>
    <h1>Donate Options</h1>
    <p class="module-intro">The choices in the Donate page's "Program" dropdown, in the order below. The option named <code>general</code> is selected when the page opens.</p>
  </div>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Option</a>
  ` : ''}
</div>
${d.toolbar}
${(!options.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No options match that search.'
        : 'No options yet - the Donate page is listing "Where it\'s needed most" and every published project. Add the first option to take over.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all options on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Option', 'label', params)}
          <th>Linked project</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(options).map((o) => { const name = optionName(o); const note = projectNote(o); return `
          <tr${canReorder ? ' data-id="' + int(o.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(o.id)}"
                     data-bulk-item aria-label="Select ${e(name)}">
            </td>
            ${(canReorder) ? `${reorderCell(name)}` : ''}
            ${orderCell(o.display_order, dupeOrders)}
            <td>
              <div class="data-table-primary">${e(name)}</div>
              <div class="data-table-secondary">donate?program=${e(o.slug)}</div>
              ${(note !== '') ? `<div class="data-table-secondary">${e(note)}</div>` : ''}
            </td>
            <td>
              ${(o.program_id !== null && programs[o.program_id] !== undefined) ? `
                ${e(programs[o.program_id]['name'])}
              ` : `
                <span class="data-table-secondary">—</span>
              `}
            </td>
            <td>
              <span class="status-badge status-badge--${e(o.status)}">${e(ucfirst(o.status))}</span>
            </td>
            <td>${e(fmtDate(o.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(o.id)}" class="link-edit">Edit<span class="sr-only"> ${e(name)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(name)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(o.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(name)}</span></button>
              </form>
              ` : ''}
            </td>
          </tr>
        `; }).join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, total, params)}
`}
`;
}

export function formBody(ctx: Ctx, d: Record<string, any>): string {
  const programs: Record<string, any>[] = d.programs; const maxMb: number = d.maxMb;
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Donate Options</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="program_id">Linked project (optional)</label>
    <select id="program_id" name="program_id">
      <option value="">None - a separate option, such as "Where it's needed most"</option>
      ${Object.values(programs).map((p) => `
        <option value="${int(p.id)}"${String(v.program_id ?? '') === String(p.id) ? ' selected' : ''}>
          ${e(p.name)}${p.status !== 'active' ? ' (unpublished)' : ''}
        </option>
      `).join('')}
    </select>
    <p class="field-hint">A linked option uses the project's name, photo, description and numbers, unless you fill those in below. It is hidden while the project is unpublished.</p>
  </div>
  <div class="field">
    <label for="label">Label in the dropdown</label>
    <input type="text" id="label" name="label" value="${e(String(v.label ?? ''))}" maxlength="200" placeholder="Where it's needed most">
    <p class="field-hint">Required for an option without a linked project. For a linked one, leave blank to use the project's name.</p>
  </div>
  <div class="field">
    <label for="summary">Description (optional)</label>
    <textarea id="summary" name="summary" rows="3" maxlength="1000">${e(String(v.summary ?? ''))}</textarea>
    <p class="field-hint">Shown under the dropdown and on the photo card. Leave blank to use the linked project's summary.</p>
  </div>
  <div class="field">
    <label for="image">Photo (optional)</label>
    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
    <p class="field-help">JPG, PNG or WebP · max ${maxMb}MB · a landscape photo works best. Leave empty to use the linked project's photo (or the general photo).</p>
    ${(isEdit && Boolean(existing.image_path)) ? `
      <div class="current-image" id="current-image">
        <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
        <span>Current photo - upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}
    ` : ''}
  </div>
  <div class="field">
    <label for="slug">Name in the address (optional)</label>
    <input type="text" id="slug" name="slug" value="${e(String(v.slug ?? ''))}" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="made from the label">
    <p class="field-hint">Lowercase letters, numbers and dashes, as in <code>donate?program=<strong>bright-beginnings</strong></code>. Leave blank to make it from the project or the label. The project pages' Donate buttons use the project's own name here, so keep it for linked options. <code>general</code> is the option selected when the page opens.</p>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="active"${(v.status ?? '') === 'active' ? ' selected' : ''}>Active - shown in the dropdown</option>
        <option value="draft"${(v.status ?? '') === 'draft' ? ' selected' : ''}>Draft - hidden from the dropdown</option>
      </select>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Option'}</button>
  </div>
</form>
`;
}
