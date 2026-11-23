/* eslint-disable */
// Markup ported from website/public/admin/modules/hero-slides/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const slideTitle = d.slideTitle as (s: Record<string, any>) => string;
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const slides: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <div>
    <h1>Home Slideshow</h1>
    <p class="module-intro">The photos and headlines that rotate on the first screen of the home page. Active slides show in the order below; the first one loads first, so put the strongest photo at the top.</p>
  </div>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Slide</a>
  ` : ''}
</div>
${d.toolbar}
${(!slides.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No slides match that search.'
        : 'No slides yet - the home page is showing its built-in slides. Add the first one to take over.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('hero-slides')}
  <div class="listing-view" data-view-key="hero-slides">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all slides on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          <th><span class="sr-only">Photo</span></th>
          ${listSortHeader(ctx, 'Headline', 'title', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(slides).map((slide) => `
          <tr${canReorder ? ' data-id="' + int(slide.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(slide.id)}"
                     data-bulk-item aria-label="Select ${e(slideTitle(slide))}">
            </td>
            ${(canReorder) ? `${reorderCell(slideTitle(slide))}` : ''}
            ${orderCell(slide.display_order, dupeOrders)}
            <td class="col-thumb">
              ${(Boolean(slide.image_path)) ? `
                <img class="table-thumb table-thumb--wide" src="../../uploads/${e(slide.image_path)}" alt="" loading="lazy">
              ` : ''}
            </td>
            <td class="data-table-primary">
              ${e(slide.title_lead)} <em class="text-accent">${e(slide.title_highlight)}</em>
              ${(Boolean(slide.description)) ? `
                <div class="data-table-sub">${e(mb_strimwidth(slide.description, 0, 110, '…'))}</div>
              ` : ''}
            </td>
            <td>
              <span class="status-badge status-badge--${e(slide.status)}">${e(ucfirst(slide.status))}</span>
            </td>
            <td>${e(fmtDate(slide.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(slide.id)}" class="link-edit">Edit<span class="sr-only"> ${e(slideTitle(slide))}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete the &quot;${e(slideTitle(slide))}&quot; slide? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(slide.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(slideTitle(slide))}</span></button>
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
      ${Object.values(slides).map((slide) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(slideTitle(slide))}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(slide.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media">
            ${(Boolean(slide.image_path)) ? `
              <img src="../../uploads/${e(slide.image_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">${e(mb_substr(slide.title_highlight, 0, 1))}</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(slideTitle(slide))}</div>
            <div class="grid-card-meta">${e(mb_strimwidth(String(slide.description ?? ""), 0, 90, '…'))}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(slide.status)}">${e(ucfirst(slide.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(slide.id)}" class="link-edit">Edit<span class="sr-only"> ${e(slideTitle(slide))}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete the &quot;${e(slideTitle(slide))}&quot; slide? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(slide.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(slideTitle(slide))}</span></button>
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
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Home Slideshow</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <fieldset class="form-section">
    <legend>Headline</legend>
    <p class="field-hint">The headline is typed out in two parts: the first in white, the second in green italics - for example <strong>Early Childhood</strong> <em class="text-accent">Learning Centers (ECLC)</em>.</p>
    <div class="form-row-2">
      <div class="field">
        <label for="title_lead">First part (white)</label>
        <input type="text" id="title_lead" name="title_lead" value="${e(v.title_lead)}" required maxlength="120" placeholder="Early Childhood">
      </div>
      <div class="field">
        <label for="title_highlight">Second part (green italics)</label>
        <input type="text" id="title_highlight" name="title_highlight" value="${e(v.title_highlight)}" required maxlength="120" placeholder="Learning Centers (ECLC)">
      </div>
    </div>
    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3" maxlength="400">${e(v.description ?? '')}</textarea>
      <p class="field-hint">One or two sentences under the headline. Around 20-25 words fits every screen size.</p>
    </div>
  </fieldset>
  <fieldset class="form-section">
    <legend>Photo</legend>
    <div class="field">
      <label for="image">Background photo</label>
      <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('hero-slides')}>
      ${imageSpecHelp('hero-slides')}
      ${(isEdit && Boolean(existing.image_path)) ? `
        <div class="current-image current-image--wide" id="current-image">
          <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
          <span>Current photo - upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}
      ` : ''}
    </div>
    <div class="field">
      <label for="image_alt">Photo description (for screen readers)</label>
      <input type="text" id="image_alt" name="image_alt" value="${e(v.image_alt ?? '')}" maxlength="200" placeholder="Children at work in an early childhood classroom">
      <p class="field-hint">What the photo shows, in a few words. Leave blank to use the headline.</p>
    </div>
  </fieldset>
  <fieldset class="form-section">
    <legend>Publishing</legend>
    <div class="form-row-2">
      <div class="field">
        <label for="display_order">Display Order</label>
        <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
        <p class="field-hint">Lower numbers show first. You can also drag slides into order on the list.</p>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
          <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft - hidden from the website</option>
          <option value="active"${v.status === 'active' ? ' selected' : ''}>Active - shown on the website</option>
        </select>
      </div>
    </div>
  </fieldset>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Slide'}</button>
  </div>
</form>
`;
}
