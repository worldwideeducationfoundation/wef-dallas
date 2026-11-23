/* eslint-disable */
// Markup ported from website/public/admin/modules/success-stories/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const stories: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Success Stories</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Success Story</a>
  ` : ''}
</div>
${d.toolbar}
${(!stories.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No success stories match that search.'
        : 'No success stories yet. Add the first one to get started.'}
  </div>
` : `
  <form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>
  ${viewToggle('success-stories')}
  <div class="listing-view" data-view-key="success-stories">
  <div class="view-panel view-panel--list">
  ${bulkToolbar(ctx)}
  ${reorderNotice(ctx, params)}
  <p class="reorder-status" data-reorder-status role="status" aria-live="polite"></p>
  <div class="table-scroll">
    <table class="data-table"${reorderTableAttributes(ctx, canReorder)}>
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" data-bulk-all aria-label="Select all success stories on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Title', 'title', params)}
          <th>Tag</th>
          <th>Date</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(stories).map((story) => `
          <tr${canReorder ? ' data-id="' + int(story.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(story.id)}"
                     data-bulk-item aria-label="Select ${e(story.title)}">
            </td>
            ${(canReorder) ? `${reorderCell(story.title)}` : ''}
            ${orderCell(story.display_order, dupeOrders)}
            <td class="data-table-primary">${e(story.title)}</td>
            <td>${e(story.tag ?? '')}</td>
            <td>${e(story.story_date ?? '')}</td>
            <td>
              <span class="status-badge status-badge--${e(story.status)}">${e(ucfirst(story.status))}</span>
            </td>
            <td>${e(fmtDate(story.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(story.id)}" class="link-edit">Edit<span class="sr-only"> ${e(story.title)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(story.title)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(story.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(story.title)}</span></button>
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
      ${Object.values(stories).map((story) => `
        <div class="grid-card">
          <label class="grid-card-select">
            <span class="sr-only">Select ${e(story.title)}</span>
            <input type="checkbox" name="ids[]" form="bulk-form" value="${int(story.id)}" data-bulk-item>
          </label>
          <div class="grid-card-media">
            ${(Boolean(story.image_path)) ? `
              <img src="../../uploads/${e(story.image_path)}" alt="" loading="lazy">
            ` : `
              <div class="grid-card-media--empty" aria-hidden="true">${e(mb_substr(story.title, 0, 1))}</div>
            `}
          </div>
          <div class="grid-card-body">
            <div class="grid-card-title">${e(story.title)}</div>
            <div class="grid-card-meta">${e(story.tag ?? '')}${Boolean(story.story_date) ? ' &middot; ' + e(story.story_date) : ''}</div>
            <div class="grid-card-footer">
              <span class="status-badge status-badge--${e(story.status)}">${e(ucfirst(story.status))}</span>
              <div class="data-table-actions">
                <a href="form.php?id=${int(story.id)}" class="link-edit">Edit<span class="sr-only"> ${e(story.title)}</span></a>
                ${(can(ctx.session, 'content.delete')) ? `
                <form method="post" action="delete.php" data-confirm="Delete &quot;${e(story.title)}&quot;? It will move to Trash, where you can restore it.">
                  ${csrfField(ctx)}
                  <input type="hidden" name="id" value="${int(story.id)}">
                  <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(story.title)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Success Stories</a>
</div>
<form method="post" action="save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="200">
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="tag">Tag (shown on the card, e.g. "SUCCESS STORIES")</label>
      <input type="text" id="tag" name="tag" value="${e(v.tag ?? '')}" maxlength="40">
    </div>
    <div class="field">
      <label for="story_date">Date (shown as typed, e.g. "May 6, 2026")</label>
      <input type="text" id="story_date" name="story_date" value="${e(v.story_date ?? '')}" maxlength="40">
    </div>
  </div>
  <div class="field">
    <label for="link_url">Read More link (optional)</label>
    <input type="url" id="link_url" name="link_url" value="${e(v.link_url ?? '')}" maxlength="255" placeholder="https://…">
    <p class="field-hint">No detail page exists for stories on this site yet, so this can only point somewhere external (a blog post, a PDF, a news article). Leave blank and the card renders without a "Read More" link.</p>
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
    <label for="image">Photo (optional — a placeholder box shows if left empty)</label>
    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes('completed-projects')}>
    ${imageSpecHelp('completed-projects')}
    ${(isEdit && Boolean(existing.image_path)) ? `
      <div class="current-image" id="current-image">
        <img src="../../uploads/${e(existing.image_path)}" alt="Current photo">
        <span>Current photo — upload a new file to replace it.</span>
      </div>
      ${removeFileCheckbox('image', existing.image_path, 'current-image', 'Remove current photo')}
    ` : ''}
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Success Story'}</button>
  </div>
</form>
`;
}
