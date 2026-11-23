/* eslint-disable */
// Markup ported from website/public/admin/modules/video-gallery/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const videos: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Video Gallery</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Video</a>
  ` : ''}
</div>
<div class="welcome-card">
  <p>Shown on the Video Gallery page and, capped to the first 2, on the homepage's "Featured Videos" section — both read this same list.</p>
</div>
${d.toolbar}
${(!videos.length) ? `
  <div class="empty-state">
    ${isFiltered
        ? 'No videos match that search.'
        : 'No videos yet. Add the first one to get started.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all videos on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Title', 'title', params)}
          <th>YouTube URL</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          ${listSortHeader(ctx, 'Updated', 'updated', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(videos).map((video) => `
          <tr${canReorder ? ' data-id="' + int(video.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(video.id)}"
                     data-bulk-item aria-label="Select ${e(video.title)}">
            </td>
            ${(canReorder) ? `${reorderCell(video.title)}` : ''}
            ${orderCell(video.display_order, dupeOrders)}
            <td class="data-table-primary">${e(video.title)}</td>
            <td class="data-table-secondary">${e(video.youtube_url)}</td>
            <td>
              <span class="status-badge status-badge--${e(video.status)}">${e(ucfirst(video.status))}</span>
            </td>
            <td>${e(fmtDate(video.updated_at))}</td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(video.id)}" class="link-edit">Edit<span class="sr-only"> ${e(video.title)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete &quot;${e(video.title)}&quot;? It will move to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(video.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(video.title)}</span></button>
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
  <a href="index.php" class="link-back">&larr; Back to Video Gallery</a>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="${e(v.title)}" required maxlength="200">
    <p class="field-hint">Use a project's exact name (as in Our Active Projects, e.g. "Secondary School Scholarships") and the video also appears on that project's page, beside its Impact section.</p>
  </div>
  <div class="field">
    <label for="youtube_url">YouTube Embed URL</label>
    <input type="url" id="youtube_url" name="youtube_url" value="${e(v.youtube_url ?? '')}" required maxlength="255" placeholder="https://www.youtube.com/embed/…">
    <p class="field-hint">Must be a youtube.com or youtube-nocookie.com link. Use YouTube's own Share &rarr; Embed option and paste the URL from the <code>src</code> attribute it gives you (looks like <code>https://www.youtube.com/embed/VIDEO_ID</code>).</p>
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
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Create Video'}</button>
  </div>
</form>
`;
}
