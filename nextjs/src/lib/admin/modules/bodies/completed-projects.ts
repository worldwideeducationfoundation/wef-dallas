/* eslint-disable */
// Markup ported from website/public/admin/modules/completed-projects/{index,form}.php (draft-converted, reviewed).
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
  <h1>Completed Projects</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Completed Project</a>
  ` : ''}
</div>
${d.toolbar}
${(!projects.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No completed projects match that search.'
        : 'No completed projects yet. Add the first one to get started.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('completed-projects')}
  <div class="listing-view" data-view-key="completed-projects">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${(!canReorder && can(ctx.session, 'content.edit') && params.sort === 'default' && params.q === '' && params.filters['category'] === undefined) ? `
    <p class="reorder-notice">Choose a single category above to drag projects into order within it.</p>
  ` : `
    ${reorderNotice(ctx, params)}
  `}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all completed projects on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Title', 'title', params)}
          ${listSortHeader(ctx, 'Category', 'category', params)}
          <th>Year</th>
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
                     data-bulk-item aria-label="Select ${e(project.title)}">
            </td>
            ${(canReorder) ? `${reorderCell(project.title)}` : ''}
            ${orderCell(project.display_order, dupeOrders, project.category)}
            <td>
              <div class="data-table-primary">${e(project.title)}</div>
              <div class="data-table-secondary">${e(project.slug)}</div>
            </td>
            <td>${e(project.category ?? '')}</td>
            <td>${e(project.completion_year ?? '')}</td>
            <td>
              <span class="status-badge status-badge--${e(project.status)}">${e(ucfirst(project.status))}</span>
            </td>
            <td>${e(fmtDate(project.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(project.id)}" class="link-edit">Edit<span class="sr-only"> ${e(project.title)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(project.title)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(project.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(project.title)}</span></button>
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
      ${Object.values(projects).map((project) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(project.title)}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(project.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media">
            ${(Boolean(project.image_path)) ? `
              <img src="../../uploads/${e(project.image_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">${e(mb_substr(project.title, 0, 1))}</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(project.title)}</div>
            <div class="grid-card-meta">${e(project.category ?? '')}${Boolean(project.completion_year) ? ' &middot; ' + e(project.completion_year) : ''}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(project.status)}">${e(ucfirst(project.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(project.id)}" class="link-edit">Edit<span class="sr-only"> ${e(project.title)}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete &quot;${e(project.title)}&quot;? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(project.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(project.title)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Completed Projects</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="form-row-2">
    <div class="field">
      <label for="title">Project Title</label>
      <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="200">
    </div>
    <div class="field">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" value="${e(v.slug)}" required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="category">Category</label>
      <input type="text" id="category" name="category" value="${e(v.category ?? '')}" maxlength="80" placeholder="e.g. Scholarships, Infrastructure">
      <p class="field-hint">Free text — reuse an existing category (shown as a filter on the list page) unless this project genuinely needs a new one.</p>
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
      <label for="location">Location</label>
      <input type="text" id="location" name="location" value="${e(v.location ?? '')}" maxlength="160" placeholder="e.g. Khorog, Tajikistan">
    </div>
    <div class="field">
      <label for="completion_year">Completion Year</label>
      <input type="text" id="completion_year" name="completion_year" value="${e(v.completion_year ?? '')}" maxlength="20" placeholder="e.g. 2020, or &quot;Active&quot;">
    </div>
  </div>
  <div class="field">
    <label for="summary">Summary (used on the project card)</label>
    <textarea id="summary" name="summary" rows="2" required maxlength="500">${e(v.summary)}</textarea>
  </div>
  <div class="field">
    <label for="description">Full Description (used on the project page)</label>
    <textarea id="description" name="description" rows="10" required maxlength="20000">${e(v.description)}</textarea>
    <p class="field-hint">Start a line with <code>### </code> for a subheading. Everything else renders as written.</p>
  </div>
  <fieldset class="stat-fieldset">
    <legend>Stats (shown as the "Measurable Impact" tiles)</legend>
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
  <fieldset class="stat-fieldset">
    <legend>Photos</legend>
    <div class="field">
      <label for="image">Main Photo (required — card image and page banner)</label>
      <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('completed-projects')}${isEdit ? '' : ' required'}>
      ${imageSpecHelp('completed-projects')}
      ${(isEdit && Boolean(existing.image_path)) ? `
        <div class="current-image" id="current-image">
          <img src="../../uploads/${e(existing.image_path)}" alt="Current main photo">
          <span>Current photo — upload a new file to replace it.</span>
        </div>
      ` : ''}
    </div>
    <div class="field">
      <label for="secondary_image">Second Photo (optional — falls back to the main photo)</label>
      <input type="file" id="secondary_image" name="secondary_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('completed-projects')}>
      ${(isEdit && Boolean(existing.secondary_image_path)) ? `
        <div class="current-image" id="current-secondary-image">
          <img src="../../uploads/${e(existing.secondary_image_path)}" alt="Current second photo">
          <span>Current photo — upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('secondary_image', existing.secondary_image_path, 'current-secondary-image', 'Remove current second photo')}
      ` : ''}
    </div>
    <div class="field">
      <label for="tertiary_image">Third Photo (optional)</label>
      <input type="file" id="tertiary_image" name="tertiary_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('completed-projects')}>
      ${(isEdit && Boolean(existing.tertiary_image_path)) ? `
        <div class="current-image" id="current-tertiary-image">
          <img src="../../uploads/${e(existing.tertiary_image_path)}" alt="Current third photo">
          <span>Current photo — upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('tertiary_image', existing.tertiary_image_path, 'current-tertiary-image', 'Remove current third photo')}
      ` : ''}
    </div>
    <div class="field">
      <label for="map_image">Map Image (optional — a static map screenshot, not an interactive map)</label>
      <input type="file" id="map_image" name="map_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('completed-projects-map')}>
      ${imageSpecHelp('completed-projects-map')}
      ${(isEdit && Boolean(existing.map_image_path)) ? `
        <div class="current-image" id="current-map-image">
          <img src="../../uploads/${e(existing.map_image_path)}" alt="Current map image">
          <span>Current image — upload a new file to replace it.</span>
        </div>
        ${removeFileCheckbox('map_image', existing.map_image_path, 'current-map-image', 'Remove current map image')}
      ` : ''}
    </div>
    <div class="field">
      <label for="video_url">Video URL (optional — a YouTube link)</label>
      <input type="url" id="video_url" name="video_url" value="${e(v.video_url ?? '')}" maxlength="255" placeholder="https://www.youtube.com/embed/...">
      <p class="field-hint">Paste a youtube.com or youtube-nocookie.com link. The embed URL from YouTube's own Share &rarr; Embed option works best.</p>
    </div>
  </fieldset>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Display Order</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Completed Project'}</button>
  </div>
</form>
`;
}
