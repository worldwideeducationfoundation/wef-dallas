/* eslint-disable */
// Markup ported from website/public/admin/modules/programs/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const categoryLabels: Record<string, string> = d.categoryLabels;
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const programs: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Programs</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Program</a>
  ` : ''}
</div>
${d.toolbar}
${(!programs.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No programs match that search.'
        : 'No programs yet. Add the first one to get started.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('programs')}
  <div class="listing-view" data-view-key="programs">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${(!canReorder && can(ctx.session, 'content.edit') && params.sort === 'default' && params.q === '' && params.filters['category'] === undefined) ? `
    <p class="reorder-notice">Choose a single category above to drag programs into order within it.</p>
  ` : `
    ${reorderNotice(ctx, params)}
  `}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all programs on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Name', 'name', params)}
          ${listSortHeader(ctx, 'Category', 'category', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(programs).map((program) => `
          <tr${canReorder ? ' data-id="' + int(program.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(program.id)}"
                     data-bulk-item aria-label="Select ${e(program.name)}">
            </td>
            ${(canReorder) ? `${reorderCell(program.name)}` : ''}
            ${orderCell(program.display_order, dupeOrders, program.category)}
            <td>
              <div class="data-table-primary">${e(program.name)}</div>
              <div class="data-table-secondary">${e(program.slug)}</div>
            </td>
            <td>${e(categoryLabels[program.category] ?? program.category)}</td>
            <td>
              <span class="status-badge status-badge--${e(program.status)}">${e(ucfirst(program.status))}</span>
            </td>
            <td>${e(fmtDate(program.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(program.id)}" class="link-edit">Edit<span class="sr-only"> ${e(program.name)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(program.name)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(program.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(program.name)}</span></button>
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
      ${Object.values(programs).map((program) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(program.name)}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(program.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media">
            ${(Boolean(program.image_path)) ? `
              <img src="../../uploads/${e(program.image_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">${e(mb_substr(program.name, 0, 1))}</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(program.name)}</div>
            <div class="grid-card-meta">${e(categoryLabels[program.category] ?? program.category)}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(program.status)}">${e(ucfirst(program.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(program.id)}" class="link-edit">Edit<span class="sr-only"> ${e(program.name)}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete &quot;${e(program.name)}&quot;? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(program.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(program.name)}</span></button>
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
  const categories: Record<string, string> = d.categories; const projectPhotos: Record<string, any>[] = d.projectPhotos;
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Programs</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="form-row-2">
    <div class="field">
      <label for="name">Program Name</label>
      <input type="text" id="name" name="name" value="${e(v.name)}" required maxlength="160">
    </div>
    <div class="field">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" value="${e(v.slug)}" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="category">Category</label>
      <select id="category" name="category" required>
        ${Object.entries(categories).map(([value, label]) => `
          <option value="${e(value)}"${v.category === value ? ' selected' : ''}>${e(label)}</option>
        `).join('')}
      </select>
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
    <label for="summary">Summary (used on project cards)</label>
    <textarea id="summary" name="summary" rows="2" required maxlength="500">${e(v.summary)}</textarea>
  </div>
  <div class="field">
    <label for="description">Full Description (used on the program page)</label>
    <textarea id="description" name="description" rows="8" required maxlength="8000">${e(v.description)}</textarea>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="badge_text">Badge Text</label>
      <input type="text" id="badge_text" name="badge_text" value="${e(v.badge_text ?? '')}" maxlength="40" placeholder="e.g. Since 2025">
    </div>
    <div class="field">
      <label for="location_text">Location</label>
      <input type="text" id="location_text" name="location_text" value="${e(v.location_text ?? '')}" maxlength="120" placeholder="e.g. Upper Chitral">
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="year_launched">Year Launched</label>
      <input type="number" id="year_launched" name="year_launched" value="${e(String(v.year_launched ?? ''))}" min="1900" max="2100" placeholder="e.g. 2015">
    </div>
  </div>
  <fieldset class="stat-fieldset">
    <legend>Stats (shown as the three tiles on the project card)</legend>
    <div class="form-row-3">
      <div class="field">
        <label for="stat1_value">Stat 1 Value</label>
        <input type="text" id="stat1_value" name="stat1_value" value="${e(v.stat1_value ?? '')}" maxlength="40">
      </div>
      <div class="field">
        <label for="stat1_label">Stat 1 Label</label>
        <input type="text" id="stat1_label" name="stat1_label" value="${e(v.stat1_label ?? '')}" maxlength="60">
      </div>
    </div>
    <div class="form-row-3">
      <div class="field">
        <label for="stat2_value">Stat 2 Value</label>
        <input type="text" id="stat2_value" name="stat2_value" value="${e(v.stat2_value ?? '')}" maxlength="40">
      </div>
      <div class="field">
        <label for="stat2_label">Stat 2 Label</label>
        <input type="text" id="stat2_label" name="stat2_label" value="${e(v.stat2_label ?? '')}" maxlength="60">
      </div>
    </div>
    <div class="form-row-3">
      <div class="field">
        <label for="stat3_value">Stat 3 Value</label>
        <input type="text" id="stat3_value" name="stat3_value" value="${e(v.stat3_value ?? '')}" maxlength="40">
      </div>
      <div class="field">
        <label for="stat3_label">Stat 3 Label</label>
        <input type="text" id="stat3_label" name="stat3_label" value="${e(v.stat3_label ?? '')}" maxlength="60">
      </div>
    </div>
  </fieldset>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="image">Photo</label>
      <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('programs')}>
      ${imageSpecHelp('programs')}
      ${(isEdit && Boolean(existing.image_path)) ? `
        <div class="current-image" id="current-image">
          <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
          <span>Current photo — upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}
      ` : ''}
    </div>
  </div>
  <fieldset class="form-section">
    <legend>Before &amp; after (optional)</legend>
    <p class="field-hint">Two photos of the same place, taken before and after WEF's work. When both are set, the project page shows a slider visitors drag to compare them. Leave empty if the project has nothing to compare.</p>
    <div class="form-row-2">
      ${Object.entries({'before': 'Before photo', 'after': 'After photo'}).map(([side, sideLabel]) => { const sidePath = existing?.[side + '_image_path'] ?? null; return `
        <div class="field">
          <label for="${side}_image">${e(sideLabel)}</label>
          <input type="file" id="${side}_image" name="${side}_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('programs')}>
          ${(isEdit && Boolean(sidePath)) ? `
            <div class="current-image" id="current-${side}-image">
              <img src="../../uploads/${e(sidePath)}" alt="Current ${e(sideLabel.toLowerCase())}">
              <span>Upload a new file to replace it.</span>
            </div>
            ${removeFileCheckbox(side + '_image', sidePath, 'current-' + side + '-image', 'Remove ' + sideLabel.toLowerCase())}
          ` : ''}
        </div>
      `; }).join('')}
    </div>
  </fieldset>
  <fieldset class="form-section">
    <legend>Project photos</legend>
    <p class="field-hint">These illustrate the project page: one photo beside each section of text, in the order below, and all of them in the photo carousel at the foot of the page. A section without a photo shows a colour panel instead. Gallery photos filed under this project are used after these.</p>
    ${(projectPhotos.length) ? `
      <div class="project-photo-list">
        ${Object.values(projectPhotos).map((photo) => { const pid = int(photo.id); return `
          <div class="project-photo" id="project-photo-${pid}">
            <img src="../../uploads/${e(photo.image_path)}" alt="" class="project-photo-thumb" loading="lazy">
            <div class="project-photo-fields">
              <div class="field">
                <label for="photo-caption-${pid}">Caption</label>
                <input type="text" id="photo-caption-${pid}" name="photos[${pid}][caption]" value="${e(String(photo.caption))}" maxlength="160" placeholder="e.g. Morning circle time in Booni">
              </div>
              <div class="form-row-2">
                <div class="field">
                  <label for="photo-order-${pid}">Order</label>
                  <input type="number" id="photo-order-${pid}" name="photos[${pid}][order]" value="${int(photo.display_order)}" step="1">
                </div>
                <div class="field">
                  <label for="photo-replace-${pid}">Replace with</label>
                  <input type="file" id="photo-replace-${pid}" name="photo_replace_${pid}" accept=".jpg,.jpeg,.png,.webp">
                </div>
              </div>
              <label class="remove-file">
                <input type="checkbox" name="photos[${pid}][remove]" value="1" data-remove-toggle="project-photo-${pid}">
                <span>Remove this photo</span>
              </label>
            </div>
          </div>
        `; }).join('')}
      </div>
    ` : ''}
    <div class="field">
      <label for="new_photos">${projectPhotos.length ? 'Add more photos' : 'Add photos'}</label>
      <input type="file" id="new_photos" name="new_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
      <p class="field-hint">Choose several at once (up to 12 per save, 5MB each). Captions can be added after saving.</p>
    </div>
  </fieldset>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Program'}</button>
  </div>
</form>
`;
}
