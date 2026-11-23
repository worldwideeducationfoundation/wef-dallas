/* eslint-disable */
// Markup ported from website/public/admin/modules/map-centers/{index,form}.php (draft-converted, reviewed).
import 'server-only';
import { e } from '../../../html';
import { csrfField, type Ctx } from '../../context';
import { can } from '../../session';
import { bulkToolbar, documentSpecHelp, fmtDate, imageSpecAttributes, imageSpecHelp, listPagination, listSortHeader, listUrl, orderCell, orderHeader, removeFileCheckbox, reorderCell, reorderNotice, reorderTableAttributes, ucfirst, viewToggle } from '../../ui';
import { int, mb_substr, mb_strimwidth, number_format, basename } from '../util';

export function indexBody(ctx: Ctx, d: Record<string, any>): string {
  const { params, total, isFiltered, canReorder, dupeOrders } = d;
  const centers: Record<string, any>[] = d.rows;
  return `<div class="module-header">
  <h1>Map Centers</h1>
  ${(can(ctx.session, 'content.create')) ? `
  <a href="form.php" class="btn-primary">+ Add Center</a>
  ` : ''}
</div>
<div class="welcome-card">
  <p>The learning centers on the home page's interactive map. Active centers appear on the map straight away;
    Draft keeps one off the map without deleting it. The sponsor filter on the map lists every sponsor named here,
    and clicking a sponsor on the Honor Board shows that sponsor's centers - so spell sponsors the same way in both places.</p>
</div>
${d.toolbar}
${(!centers.length) ? `
  <div class="empty-state">
    ${isFiltered ? 'No centers match that search.' : 'No centers yet. Add the first one to put it on the map.'}
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
            <input type="checkbox" data-bulk-all aria-label="Select all centers on this page">
          </th>
          ${(canReorder) ? `<th><span class="sr-only">Reorder</span></th>` : ''}
          ${orderHeader(ctx, params)}
          ${listSortHeader(ctx, 'Village', 'village', params)}
          ${listSortHeader(ctx, 'District', 'district', params)}
          ${listSortHeader(ctx, 'Type', 'model', params)}
          ${listSortHeader(ctx, 'Sponsor', 'sponsor', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${Object.values(centers).map((center) => `
          <tr${canReorder ? ' data-id="' + int(center.id) + '" draggable="true"' : ''}>
            <td class="col-check">
              <input type="checkbox" name="ids[]" form="bulk-form" value="${int(center.id)}"
                     data-bulk-item aria-label="Select ${e(center.village)}">
            </td>
            ${(canReorder) ? `${reorderCell(center.village)}` : ''}
            ${orderCell(center.display_order, dupeOrders)}
            <td class="data-table-primary">
              ${e(center.village)}
              ${(Boolean(center.lead_name)) ? `<span class="media-variants">${e(center.lead_name)}</span>` : ''}
            </td>
            <td>${e(center.district)}</td>
            <td>${e(center.center_model)}</td>
            <td class="data-table-secondary">${e(center.sponsor)}</td>
            <td>
              <span class="status-badge status-badge--${e(center.status)}">${e(ucfirst(center.status))}</span>
            </td>
            <td class="data-table-actions">
              <a href="form.php?id=${int(center.id)}" class="link-edit">Edit<span class="sr-only"> ${e(center.village)}</span></a>
              <a href="https://www.google.com/maps?q=${e(String(Number(center.latitude)))},${e(String(Number(center.longitude)))}" target="_blank" rel="noopener" class="link-edit">Map<span class="sr-only"> of ${e(center.village)}</span></a>
              ${(can(ctx.session, 'content.delete')) ? `
              <form method="post" action="delete.php" data-confirm="Delete the center in &quot;${e(center.village)}&quot;? It leaves the map and moves to Trash, where you can restore it.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(center.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(center.village)}</span></button>
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
  const sponsorNames: string[] = d.sponsorNames; const MAP_CENTER_DISTRICTS: string[] = d.districts; const MAP_CENTER_MODELS: string[] = d.models;
  const { pageTitle, isEdit, id, existing } = d;
  const v: Record<string, any> = d.values;
  return `<div class="module-header">
  <h1>${e(pageTitle)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Map Centers</a>
</div>
<form method="post" action="save.php" class="admin-form">
  ${csrfField(ctx)}
  ${(isEdit) ? `<input type="hidden" name="id" value="${int(id)}">` : ''}
  <div class="field">
    <label for="village">Village</label>
    <input type="text" id="village" name="village" value="${e(String(v.village))}" required maxlength="255" placeholder="e.g. Dawashish Jughoor Lower Chitral">
    <p class="field-hint">Shown on the center's pin. Ending it with the district, as the existing centers do, reads well there.</p>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="district">District</label>
      <select id="district" name="district" required>
        ${Object.values(MAP_CENTER_DISTRICTS).map((district) => `
          <option value="${e(district)}"${v.district === district ? ' selected' : ''}>${e(district)}</option>
        `).join('')}
      </select>
    </div>
    <div class="field">
      <label for="center_model">Type</label>
      <select id="center_model" name="center_model" required>
        ${Object.values(MAP_CENTER_MODELS).map((model) => `
          <option value="${e(model)}"${v.center_model === model ? ' selected' : ''}>${e(model)}</option>
        `).join('')}
      </select>
    </div>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="sponsor">Sponsor</label>
      <input type="text" id="sponsor" name="sponsor" value="${e(String(v.sponsor))}" required maxlength="255" list="sponsor-names">
      <datalist id="sponsor-names">
        ${Object.values(sponsorNames).map((name) => `<option value="${e(name)}">`).join('')}
      </datalist>
    </div>
    <div class="field">
      <label for="lead_name">Center lead (optional)</label>
      <input type="text" id="lead_name" name="lead_name" value="${e(String(v.lead_name ?? ''))}" maxlength="160" placeholder="The entrepreneur or teacher running it">
    </div>
  </div>
  <div class="field">
    <span class="field-label">Location on the map</span>
    <div id="center-map" class="center-map" data-lat-input="latitude" data-lng-input="longitude"
         data-centers-url="../../../api/map-centers.php" aria-label="Map: click to place the center's pin, drag the pin to adjust"></div>
    <p class="field-hint" id="center-map-status" aria-live="polite">Click the map where the center is to place its pin, then drag the pin to adjust.</p>
  </div>
  <div class="form-row-2">
    <div class="field">
      <label for="latitude">Latitude</label>
      <input type="text" id="latitude" name="latitude" value="${e(String(v.latitude))}" required inputmode="decimal" placeholder="e.g. 35.838713">
    </div>
    <div class="field">
      <label for="longitude">Longitude</label>
      <input type="text" id="longitude" name="longitude" value="${e(String(v.longitude))}" required inputmode="decimal" placeholder="e.g. 71.791280">
    </div>
  </div>
  <p class="field-hint">
    In Google Maps, right-click the center's spot and click the numbers at the top of the menu to copy them
    (latitude first, e.g. <code>35.838713, 71.791280</code>).
    ${(isEdit) ? `
      <a href="https://www.google.com/maps?q=${e(String(Number(v.latitude)))},${e(String(Number(v.longitude)))}" target="_blank" rel="noopener">Check the current position on Google Maps</a>.
    ` : ''}
  </p>
  <div class="form-row-2">
    <div class="field">
      <label for="display_order">Number</label>
      <input type="number" id="display_order" name="display_order" value="${e(String(v.display_order ?? 0))}" step="1">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <option value="active"${v.status === 'active' ? ' selected' : ''}>Active (on the map)</option>
        <option value="draft"${v.status === 'draft' ? ' selected' : ''}>Draft (hidden)</option>
      </select>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">${isEdit ? 'Save Changes' : 'Add Center'}</button>
  </div>
</form>
`;
}
