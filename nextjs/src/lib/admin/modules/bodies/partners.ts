/* eslint-disable */
// Markup ported from website/public/admin/modules/partners/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const partners: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <div>
    <h1>Partner Logos</h1>
    <p class="module-intro">The logos that scroll under "Our Partners" on the home page, in the order below.</p>
  </div>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Partner</a>
  ` : ''}
</div>
${d.toolbar}
${(!partners.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No partners match that search.'
        : 'No partners yet - the home page is showing its built-in logos. Add the first one to take over.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('partners')}
  <div class="listing-view" data-view-key="partners">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all partners on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Logo</span></th>
          ${listSortHeader(ctx, 'Name', 'name', params)}
          <th>Link</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(partners).map((partner) => `
          <tr${canReorder ? ' data-id="' + int(partner.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(partner.id)}"
                     data-bulk-item aria-label="Select ${e(partner.name)}">
            </td>
            ${(canReorder) ? `${reorderCell(partner.name)}` : ''}
            ${orderCell(partner.display_order, dupeOrders)}
            <td class="col-thumb">
              ${(Boolean(partner.logo_path)) ? `
                <img class="table-thumb table-thumb--logo" src="../../uploads/${e(partner.logo_path)}" alt="" loading="lazy">
              ` : ''}
            </td>
            <td class="data-table-primary">${e(partner.name)}</td>
            <td class="data-table-url">${e(partner.link_url ?? '')}</td>
            <td>
              <span class="status-badge status-badge--${e(partner.status)}">${e(ucfirst(partner.status))}</span>
            </td>
            <td>${e(fmtDate(partner.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(partner.id)}" class="link-edit">Edit<span class="sr-only"> ${e(partner.name)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(partner.name)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(partner.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(partner.name)}</span></button>
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
      ${Object.values(partners).map((partner) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(partner.name)}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(partner.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media grid-card-media--logo">
            ${(Boolean(partner.logo_path)) ? `
              <img src="../../uploads/${e(partner.logo_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">${e(mb_substr(partner.name, 0, 1))}</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(partner.name)}</div>
            <div class="grid-card-meta">${e(partner.link_url ?? '')}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(partner.status)}">${e(ucfirst(partner.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(partner.id)}" class="link-edit">Edit<span class="sr-only"> ${e(partner.name)}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete &quot;${e(partner.name)}&quot;? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(partner.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(partner.name)}</span></button>
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
  const maxMb: number = d.maxMb;
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Partner Logos</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="name">Partner name</label>
    <input type="text" id="name" name="name" value="${e(v.name)}" required maxlength="200" placeholder="University of Central Asia (UCA)">
    <p class="field-hint">Also read out to screen-reader users in place of the logo.</p>
  </div>
  <div class="field">
    <label for="logo">Logo</label>
    <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
    <p class="field-help">PNG with a transparent background works best · JPG or WebP also accepted · max ${maxMb}MB<br>Any shape - logos are scaled to the same height in the strip.</p>
    ${(isEdit && Boolean(existing.logo_path)) ? `
      <div class="current-image current-image--logo" id="current-logo">
        <img src="../../uploads/${e(existing.logo_path)}" alt="Current logo">
        <span>Current logo - upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('logo', existing.logo_path, 'current-logo', 'Remove current logo')}
    ` : ''}
  </div>
  <div class="field">
    <label for="link_url">Website link (optional)</label>
    <input type="url" id="link_url" name="link_url" value="${e(v.link_url ?? '')}" maxlength="255" placeholder="https://…">
    <p class="field-hint">If set, the logo opens this site in a new tab.</p>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft - hidden from the website</option>
        <option value="active"${v.status === 'active' ? ' selected' : ''}>Active - shown on the website</option>
      </select>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Partner'}</button>
  </div>
</form>
`;
}
