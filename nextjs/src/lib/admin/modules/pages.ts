/** Ports of admin/modules/pages/{index,edit,save}.php and page-visibility/{index,save}.php */
import 'server-only';
import { e } from '../../html';
import { dbFetchAll, dbFetchColumn } from '../../db';
import { blockImage, blockIsOverridden, blockLimit, blockRegistry, blockValue } from '../../blocks';
import { PAGE_HEADER_IMAGE_PAGES } from '../../content';
import { SWITCHABLE_PAGES } from '../../visibility';
import { Redirect, csrfField, requireCapability, requirePostWithCsrf, type PublicCtx } from '../context';
import { saveContentBlocks, setPageEnabled } from '../site-admin';
import { can, flashInputGet, flashInputSet, flashSet } from '../session';
import { adminPage, fmtDate, imageSpecAttributes, imageSpecHelp, removeFileCheckbox } from '../ui';
import type { Files } from '../crud';
import { mb_substr, type Routes } from './util';

/* ------------------------------ page content ------------------------------ */

async function pagesIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const rows = await dbFetchAll(
    `SELECT page, COUNT(*) AS n, MAX(updated_at) AS last_edit FROM content_blocks
     WHERE value IS NOT NULL AND value <> '' GROUP BY page`,
  );
  const counts = Object.fromEntries(rows.map((r) => [r.page, r]));
  const body = `
<div class="module-header">
  <h1>Page Content</h1>
</div>
<div class="welcome-card">
  <p>
    Text that appears directly on a page — headings, intro paragraphs, section copy.
    Anything left untouched shows its original wording, and <strong>Reset</strong> puts a block back to that
    original at any time. Content lists like Programs, Team and Gallery are edited in their own sections.
  </p>
</div>
<div class="table-scroll">
  <table class="data-table">
    <thead>
      <tr><th>Page</th><th>Editable blocks</th><th>Customised</th><th>Last edited</th><th><span class="sr-only">Actions</span></th></tr>
    </thead>
    <tbody>
      ${Object.entries(blockRegistry())
        .map(([pageKey, config]) => {
          const edited = Math.trunc(Number(counts[pageKey]?.n ?? 0));
          const total = Object.keys(config.blocks).length;
          return `
        <tr>
          <td>
            <div class="data-table-primary">${e(config.label)}</div>
            <div class="data-table-secondary">${e(config.url)}</div>
          </td>
          <td>${total}</td>
          <td>${
            edited > 0
              ? `<span class="status-badge status-badge--active">${edited} of ${total}</span>`
              : `<span class="status-badge status-badge--draft">Using defaults</span>`
          }</td>
          <td>${counts[pageKey]?.last_edit ? e(fmtDate(counts[pageKey].last_edit)) : '<span class="data-table-secondary">—</span>'}</td>
          <td class="data-table-actions">
            <a href="edit.php?page=${e(encodeURIComponent(pageKey))}" class="link-edit">Edit<span class="sr-only"> ${e(config.label)}</span></a>
            <a href="${e(config.url)}" class="link-edit" target="_blank" rel="noopener">View</a>
          </td>
        </tr>`;
        })
        .join('')}
    </tbody>
  </table>
</div>`;
  return adminPage(ctx, { title: 'Page Content', activeNav: 'Page Content', body });
}

async function pagesEdit(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const pageKey = ctx.query.get('page') ?? '';
  const config = blockRegistry()[pageKey];
  if (!config) {
    flashSet(ctx.session, 'error', 'That page could not be found.');
    throw new Redirect('index.php');
  }
  const flashed = flashInputGet(ctx.session);
  const canEdit = can(ctx.session, 'content.edit');
  const hasHeaderImage = PAGE_HEADER_IMAGE_PAGES.includes(pageKey);
  const hasImageBlock = Object.values(config.blocks).some((b) => b.type === 'image');

  let header = '';
  if (hasHeaderImage) {
    const currentHeader = (await dbFetchColumn<string>('SELECT header_image FROM pages WHERE page_key = :page', { page: pageKey })) || null;
    const isPurpose = pageKey === 'home';
    const spec = isPurpose ? 'purpose-background' : 'page-header';
    header = `
  <fieldset class="form-section">
    <legend>${isPurpose ? 'Core Purpose background' : 'Header image'}</legend>
    <div class="field">
      <label for="header_image">${isPurpose ? 'Photo behind "Our Core Purpose" (optional)' : 'Banner photo (optional)'}</label>
      <input type="file" id="header_image" name="header_image" accept=".jpg,.jpeg,.png,.webp"${imageSpecAttributes(spec)}${canEdit ? '' : ' disabled'}>
      ${imageSpecHelp(spec)}
      ${
        currentHeader
          ? `<div class="current-image current-image--wide" id="current-header">
          <img src="../../uploads/${e(currentHeader)}" alt="Current header image">
          <span>Current header image - upload a new file to replace it.</span>
        </div>
        ${
          canEdit
            ? removeFileCheckbox(
                'header_image',
                currentHeader,
                'current-header',
                isPurpose ? 'Remove the photo (back to the plain green banner)' : 'Remove the header image (back to the plain green banner)',
              )
            : ''
        }`
          : `<p class="field-hint">No image yet - ${isPurpose ? 'the Core Purpose banner is' : 'the page shows'} plain green.</p>`
      }
    </div>
  </fieldset>`;
  }

  const grouped = new Map<string, [string, (typeof config.blocks)[string]][]>();
  for (const [key, block] of Object.entries(config.blocks)) {
    const g = block.group ?? '';
    grouped.set(g, [...(grouped.get(g) ?? []), [key, block]]);
  }

  let fields = '';
  for (const [groupLabel, blocks] of grouped) {
    if (groupLabel !== '') fields += `<h2 class="admin-form-title block-group-title">${e(groupLabel)}</h2>`;
    for (const [key, block] of blocks) {
      const stored = await blockValue(pageKey, key);
      const value = String(flashed?.[key] ?? stored);
      const isCustom = await blockIsOverridden(pageKey, key);
      const fieldId = 'block_' + key.replace(/[^a-z0-9_]/gi, '_');
      const limit = blockLimit(block.type);
      if (block.type === 'image') {
        fields += `
    <div class="field">
      <label for="${e(fieldId)}">${e(block.label)}</label>
      <div class="current-image" id="current-${e(fieldId)}">
        <img src="../../../${e(await blockImage(pageKey, key))}" alt="Current picture">
        <span>${isCustom ? 'Your uploaded picture.' : 'The original picture.'} Choose a file to replace it.</span>
      </div>
      <input type="file" id="${e(fieldId)}" name="block_image_${e(key)}" accept=".jpg,.jpeg,.png,.webp"${canEdit ? '' : ' disabled'}>
      ${block.help ? `<p class="field-hint">${e(block.help)}</p>` : ''}
      ${
        isCustom && canEdit
          ? `<label class="remove-file"><input type="checkbox" name="reset[${e(key)}]" value="1" data-remove-toggle="current-${e(fieldId)}"><span>Go back to the original picture</span></label>`
          : ''
      }
    </div>`;
        continue;
      }
      let input: string;
      if (block.type === 'body')
        input = `<textarea id="${e(fieldId)}" name="blocks[${e(key)}]" rows="14" maxlength="${limit}" class="block-body-input">${e(value)}</textarea>
      <p class="field-hint">
        Leave a blank line between paragraphs.
        Start a line with <code>- </code> for a bullet, or <code>## </code> for a subheading.
        Everything else is shown exactly as typed — HTML is not allowed here and will appear as text.
      </p>`;
      else if (block.type === 'textarea') input = `<textarea id="${e(fieldId)}" name="blocks[${e(key)}]" rows="4" maxlength="${limit}">${e(value)}</textarea>`;
      else input = `<input type="text" id="${e(fieldId)}" name="blocks[${e(key)}]" value="${e(value)}" maxlength="${limit}">`;
      const def = String(block.default ?? '');
      fields += `
    <div class="field">
      <label for="${e(fieldId)}">${e(block.label)}</label>
      ${input}
      ${block.help ? `<p class="field-hint">${e(block.help)}</p>` : ''}
      <p class="field-help">${
        isCustom
          ? `<span class="status-badge status-badge--active">Customised</span>
        Original: “${e([...def].length > 160 ? mb_substr(def, 0, 160) + '…' : def)}”`
          : `<span class="status-badge status-badge--draft">Default</span>
        Showing the original wording.`
      }</p>
      ${isCustom && canEdit ? `<label class="remove-file"><input type="checkbox" name="reset[${e(key)}]" value="1"><span>Reset this block to its original wording</span></label>` : ''}
    </div>`;
    }
  }

  const body = `
<div class="module-header">
  <h1>${e(config.label)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Page Content</a>
</div>
<form method="post" action="save.php" class="admin-form"${hasHeaderImage || hasImageBlock ? ' enctype="multipart/form-data"' : ''}>
  ${csrfField(ctx)}
  <input type="hidden" name="page" value="${e(pageKey)}">
  ${header}
  ${fields}
  ${
    canEdit
      ? `<div class="form-actions"><button type="submit" class="btn-submit">Save Changes</button></div>`
      : `<p class="field-help">You have read-only access to page content.</p>`
  }
</form>`;
  return adminPage(ctx, { title: 'Edit ' + config.label, activeNav: 'Page Content', body });
}

async function pagesSave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'content.edit');
  requirePostWithCsrf(ctx);
  const pageKey = ctx.post.page ?? '';
  const config = blockRegistry()[pageKey];
  if (!config) {
    flashSet(ctx.session, 'error', 'That page could not be found.');
    throw new Redirect('index.php');
  }
  const back = 'edit.php?page=' + encodeURIComponent(pageKey);
  const values: Record<string, unknown> = {};
  for (const [key, block] of Object.entries(config.blocks)) if (block.type !== 'image') values[key] = ctx.post[`blocks[${key}]`] ?? '';
  const resets = Object.keys(ctx.post)
    .map((k) => /^reset\[(.+)\]$/.exec(k)?.[1])
    .filter((k): k is string => Boolean(k) && Boolean(ctx.post[`reset[${k}]`]));
  if (ctx.post.remove_header_image) resets.push('header_image');
  const files: Files = {};
  if (ctx.files.header_image) files.header_image = ctx.files.header_image;
  for (const key of Object.keys(config.blocks)) if (ctx.files['block_image_' + key]) files[key] = ctx.files['block_image_' + key];
  const res = await saveContentBlocks(pageKey, values, files, resets, { userId: ctx.user.id, meta: ctx.meta, via: 'admin' });
  if (!res.ok) {
    flashInputSet(ctx.session, values);
    flashSet(ctx.session, 'error', res.errors.join(' '));
  } else flashSet(ctx.session, 'success', res.message);
  throw new Redirect(back);
}

/* ------------------------------ pages on/off ------------------------------ */

async function visibilityIndex(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
  const canEdit = can(ctx.session, 'content.edit');
  let ready = true;
  let states: Record<string, number> = {};
  try {
    states = Object.fromEntries((await dbFetchAll('SELECT page_key, is_enabled FROM pages')).map((r) => [r.page_key, Number(r.is_enabled)]));
  } catch {
    ready = false;
  }
  const offCount = Object.keys(SWITCHABLE_PAGES).filter((s) => states[s] === 0).length;
  const body = `
<div class="module-header">
  <h1>Pages On/Off</h1>
</div>
<div class="welcome-card">
  <p>
    Turn a page <strong>off</strong> to take it off the website: it disappears from the menu at the top,
    every link to it becomes plain text, and its address shows "Page not found". Its content is kept,
    so turning it back <strong>on</strong> brings it back exactly as it was. The home page is always on.
  </p>
</div>
${
  !ready
    ? `<div class="alert alert-error" role="alert">
    The database needs a one-time update before pages can be switched off: import
    <code>page-switches-20261016.sql</code> in phpMyAdmin (or run
    <code>php admin/database/migrate.php</code>). Until then every page stays on.
  </div>`
    : ''
}
<p class="data-table-secondary">
  ${offCount === 0 ? 'All pages are on.' : e(`${offCount} of ${Object.keys(SWITCHABLE_PAGES).length} pages are off.`)}
</p>
<div class="table-scroll">
  <table class="data-table">
    <thead>
      <tr><th>Page</th><th>Status</th><th><span class="sr-only">Switch and view</span></th></tr>
    </thead>
    <tbody>
      ${Object.entries(SWITCHABLE_PAGES)
        .map(([slug, label]) => {
          const on = states[slug] === undefined || states[slug] === 1;
          return `
        <tr>
          <td>
            <div class="data-table-primary">${e(label)}</div>
            <div class="data-table-secondary">/${e(slug)}</div>
          </td>
          <td><span class="status-badge status-badge--${on ? 'active' : 'draft'}">${on ? 'On' : 'Off'}</span></td>
          <td class="data-table-actions">
            ${
              canEdit && ready
                ? `<form method="post" action="save.php" class="page-switch-form">
                ${csrfField(ctx)}
                <input type="hidden" name="page" value="${e(slug)}">
                <input type="hidden" name="enabled" value="${on ? '0' : '1'}">
                <button type="submit" class="page-switch" role="switch" aria-checked="${on ? 'true' : 'false'}" aria-label="${e(label)} page" title="${on ? 'Turn off' : 'Turn on'}">
                  <span class="page-switch-track" aria-hidden="true"><span class="page-switch-thumb"></span></span>
                  <span class="page-switch-text" aria-hidden="true">${on ? 'Turn off' : 'Turn on'}</span>
                </button>
              </form>`
                : ''
            }
            ${on ? `<a href="${e('/' + slug)}" class="link-edit" target="_blank" rel="noopener">View<span class="sr-only"> ${e(label)}</span></a>` : ''}
          </td>
        </tr>`;
        })
        .join('')}
    </tbody>
  </table>
</div>`;
  return adminPage(ctx, { title: 'Pages On/Off', activeNav: 'Pages On/Off', body });
}

async function visibilitySave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'content.edit');
  requirePostWithCsrf(ctx);
  const res = await setPageEnabled(ctx.post.page ?? '', ctx.post.enabled === '1', { userId: ctx.user.id, meta: ctx.meta, via: 'admin' });
  flashSet(ctx.session, res.ok ? 'success' : 'error', res.ok ? res.message : res.errors.join(' '));
  throw new Redirect('index.php');
}

export const pagesRoutes: Routes = { 'index.php': pagesIndex, 'edit.php': pagesEdit, 'save.php': pagesSave };
export const pageVisibilityRoutes: Routes = { 'index.php': visibilityIndex, 'save.php': visibilitySave };
