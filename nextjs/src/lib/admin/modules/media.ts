/** Port of admin/modules/media/*.php (files in UPLOADS_DIR). */
import 'server-only';
import fs from 'node:fs';
import path from 'node:path';
import { e } from '../../html';
import { dbExecute, dbFetchAll, dbTransaction } from '../../db';
import { UPLOADS_DIR } from '../../paths';
import { logActivity } from '../activity';
import { Redirect, csrfField, requireCapability, requirePostWithCsrf, type PublicCtx } from '../context';
import { RESOURCES } from '../resources';
import { can, flashSet } from '../session';
import { adminPage, listPagination, listParams, listSortHeader, listToolbar } from '../ui';
import { deleteUploadedFile, handleUpload, isImageDerivative, sniffMime } from '../uploads';
import { phpDate, type Routes } from './util';

const LIBRARY = 'library';

function formatBytes(b: number): string {
  if (b >= 1024 * 1024) return `${Math.round((b / (1024 * 1024)) * 10) / 10} MB`;
  if (b >= 1024) return `${Math.round((b / 1024) * 10) / 10} KB`;
  return `${b} B`;
}

function referenceColumns(): [string, string][] {
  const pairs: [string, string][] = [];
  for (const r of Object.values(RESOURCES)) {
    if (r.key === 'program-photos') continue;
    for (const u of r.uploads) pairs.push([r.table, u.column]);
  }
  pairs.push(['pages', 'header_image'], ['program_photos', 'image_path'], ['content_blocks', 'value']);
  return pairs;
}

async function referencedPaths(): Promise<Set<string>> {
  const sql = referenceColumns()
    .map(([t, c]) => `SELECT \`${c}\` AS p FROM \`${t}\` WHERE \`${c}\` IS NOT NULL AND \`${c}\` <> ''`)
    .join(' UNION ALL ');
  return new Set((await dbFetchAll(sql)).map((r) => String(r.p)));
}

function fileFromPath(p: string): string | null {
  if (p === '' || p.includes('\0')) return null;
  const abs = path.resolve(UPLOADS_DIR, p);
  if (!abs.startsWith(UPLOADS_DIR + path.sep)) return null;
  try {
    if (!fs.statSync(abs).isFile()) return null;
  } catch {
    return null;
  }
  if (path.basename(abs).startsWith('.') || isImageDerivative(abs)) return null;
  return abs;
}

function kindOf(p: string): 'image' | 'pdf' | 'other' {
  const ext = path.extname(p).slice(1).toLowerCase();
  if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'image';
  return ext === 'pdf' ? 'pdf' : 'other';
}

function walk(dir: string, out: string[] = []): string[] {
  for (const ent of fs.existsSync(dir) ? fs.readdirSync(dir, { withFileTypes: true }) : []) {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(full, out);
    else if (ent.isFile()) out.push(full);
  }
  return out;
}

const urlOf = (p: string) => '../../uploads/' + p.split('/').map(encodeURIComponent).join('/');

async function index(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'media.view', '../../dashboard.php');
  const referenced = await referencedPaths();
  type F = { path: string; folder: string; size: number; mtime: number; used: boolean; status: string; kind: string };
  let files: F[] = [];
  const variants: Record<string, number> = {};
  let totalBytes = 0;
  let orphanCount = 0;
  const folders = new Set<string>();
  for (const abs of walk(UPLOADS_DIR)) {
    const name = path.basename(abs);
    if (name.startsWith('.')) continue;
    const rel = path.relative(UPLOADS_DIR, abs).split(path.sep).join('/');
    const st = fs.statSync(abs);
    totalBytes += st.size;
    if (isImageDerivative(rel)) {
      const original = rel.replace(/\.(jpg|jpeg|png)\.webp$/i, '.$1').replace(/-\d+w(\.(jpg|jpeg|png|webp))(\.webp)?$/i, '$1');
      variants[original] = (variants[original] ?? 0) + 1;
      continue;
    }
    const folder = rel.includes('/') ? rel.split('/')[0] : '';
    folders.add(folder);
    const used = referenced.has(rel);
    const status = used ? 'used' : folder === LIBRARY ? 'library' : 'orphaned';
    if (status === 'orphaned') orphanCount++;
    files.push({ path: rel, folder, size: st.size, mtime: Math.floor(st.mtimeMs / 1000), used, status, kind: kindOf(rel) });
  }
  const totalFiles = files.length;
  const params = listParams(ctx, { modified: 'mtime', file: 'path', size: 'size', status: 'status' }, 'modified', ['usage', 'folder']);
  if (!ctx.query.has('dir') && params.sort === 'modified') params.dir = 'DESC';
  if (params.q !== '') files = files.filter((f) => f.path.toLowerCase().includes(params.q.toLowerCase()));
  if (params.filters.usage) files = files.filter((f) => f.status === params.filters.usage);
  if (params.filters.folder !== undefined) files = files.filter((f) => f.folder === params.filters.folder);
  const key = params.sortSql as keyof F;
  const dir = params.dir === 'DESC' ? -1 : 1;
  files.sort((a, b) => dir * (a[key] < b[key] ? -1 : a[key] > b[key] ? 1 : 0));
  const matched = files.length;
  files = files.slice(params.offset, params.offset + params.perPage);
  const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
  const statusLabels: Record<string, string> = { used: 'In use', library: 'Library', orphaned: 'Unused' };
  const statusBadges: Record<string, string> = { used: 'active', library: 'active', orphaned: 'draft' };
  const folderOptions = Object.fromEntries(
    [...folders].sort().map((f) => [f, f === '' ? '(top level)' : f.charAt(0).toUpperCase() + f.slice(1)]),
  );
  const body = `
<div class="module-header">
  <h1>Media Manager</h1>
  ${can(ctx.session, 'media.upload') ? `<a href="upload.php" class="btn-primary">+ Upload Files</a>` : ''}
</div>
<div class="welcome-card">
  <p><strong>${totalFiles}</strong> file${totalFiles === 1 ? '' : 's'} in <code>admin/uploads/</code>
    — <strong>${formatBytes(totalBytes)}</strong> total, including the smaller copies made for phones and fast loading.
    ${orphanCount > 0 ? `<strong>${orphanCount}</strong> unused (uploaded, but no longer shown anywhere) - safe to delete.` : 'No unused files.'}
  </p>
  <p class="field-hint">
    <strong>In use</strong>: shown on the site; delete it from its own section (Team, Gallery...) instead.
    <strong>Replace</strong> swaps the file for a new one everywhere it appears.
    <strong>Library</strong>: files you uploaded here to link to (Copy link), kept until you delete them.
  </p>
</div>
${listToolbar(ctx, params, { usage: { label: 'Files', options: statusLabels }, folder: { label: 'Folders', options: folderOptions } }, 'Search file names…')}
${
  !files.length
    ? `<div class="empty-state">${isFiltered ? 'No files match that search.' : 'No files uploaded yet.'}</div>`
    : `
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th><span class="sr-only">Preview</span></th>
          ${listSortHeader(ctx, 'File', 'file', params)}
          ${listSortHeader(ctx, 'Size', 'size', params)}
          ${listSortHeader(ctx, 'Modified', 'modified', params)}
          ${listSortHeader(ctx, 'Status', 'status', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
      ${files
        .map((f) => {
          const url = urlOf(f.path);
          const nv = variants[f.path] ?? 0;
          return `
        <tr>
          <td>${
            f.kind === 'image'
              ? `<img src="${e(url)}" alt="" class="data-table-avatar media-thumb" loading="lazy">`
              : `<span class="data-table-avatar data-table-avatar--empty media-thumb" aria-hidden="true">${f.kind === 'pdf' ? 'PDF' : 'FILE'}</span>`
          }</td>
          <td class="data-table-primary">
            ${e(f.path)}
            ${nv ? `<span class="media-variants">+ ${nv} size${nv === 1 ? '' : 's'}</span>` : ''}
          </td>
          <td>${e(formatBytes(f.size))}</td>
          <td>${e(phpDate('M j, Y', new Date(f.mtime * 1000)))}</td>
          <td><span class="status-badge status-badge--${statusBadges[f.status]}">${statusLabels[f.status]}</span></td>
          <td class="data-table-actions">
            <a href="${e(url)}" target="_blank" rel="noopener" class="link-edit">View<span class="sr-only"> ${e(f.path)}</span></a>
            <button type="button" class="link-edit" data-copy-url="${e(url)}">Copy link</button>
            ${can(ctx.session, 'media.upload') && (f.kind === 'image' || f.kind === 'pdf') ? `<a href="replace.php?path=${e(encodeURIComponent(f.path))}" class="link-edit">Replace</a>` : ''}
            ${
              !f.used && can(ctx.session, 'media.delete')
                ? `<form method="post" action="delete.php" data-confirm="Delete &quot;${e(f.path)}&quot;${nv ? ' and its smaller copies' : ''}? This cannot be undone.">
              ${csrfField(ctx)}
              <input type="hidden" name="path" value="${e(f.path)}">
              <button type="submit" class="link-delete">Delete<span class="sr-only"> ${e(f.path)}</span></button>
            </form>`
                : ''
            }
          </td>
        </tr>`;
        })
        .join('')}
      </tbody>
    </table>
  </div>
  ${listPagination(ctx, matched, params)}`
}`;
  return adminPage(ctx, { title: 'Media Manager', activeNav: 'Media Manager', body });
}

async function uploadForm(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'media.upload', 'index.php');
  const body = `
<div class="module-header">
  <h1>Upload Files</h1>
  <a href="index.php" class="link-back">&larr; Back to Media Manager</a>
</div>
<form method="post" action="upload-save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  <div class="field">
    <label for="files">Files</label>
    <input type="file" id="files" name="files[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required>
    <p class="field-hint">
      Photos (JPG, PNG or WebP, up to 5MB each) and PDFs (up to 15MB each), up to 12 per upload.
      They go into the <strong>Library</strong> folder; use <strong>Copy link</strong> in the list to link to one,
      for example from a project update's "Read more" link.
    </p>
    <p class="field-hint">
      To show a photo in a section of the site (Team, Gallery, a project...), add it in that section instead -
      the section then shows it and keeps track of it.
    </p>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">Upload</button>
  </div>
</form>`;
  return adminPage(ctx, { title: 'Upload Files', activeNav: 'Media Manager', body });
}

async function uploadSave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'media.upload', 'index.php');
  requirePostWithCsrf(ctx, 'upload.php');
  const files = ctx.fileLists['files[]'] ?? [];
  if (!files.length) {
    flashSet(ctx.session, 'error', 'Choose at least one file to upload.');
    throw new Redirect('upload.php');
  }
  const added: string[] = [];
  const errors: string[] = [];
  for (const f of files) {
    if (added.length >= 12) {
      errors.push('Only 12 files can be uploaded at once; upload the rest separately.');
      break;
    }
    const kind = sniffMime(f.bytes) === 'application/pdf' ? 'pdf' : 'image';
    const up = await handleUpload(f, LIBRARY, kind, null);
    if (up.ok && up.path !== null) added.push(up.path);
    else errors.push(`${f.name ?? 'file'}: ${up.ok ? 'not saved' : up.error}`);
  }
  if (added.length) await logActivity(ctx.user.id, 'create', 'media', `${added.length} file(s) uploaded to the library`, ctx.meta);
  if (errors.length) {
    flashSet(ctx.session, 'error', (added.length ? `${added.length} uploaded. ` : '') + errors.join(' '));
    throw new Redirect(added.length ? 'index.php?folder=' + LIBRARY : 'upload.php');
  }
  flashSet(ctx.session, 'success', `${added.length} file${added.length === 1 ? '' : 's'} uploaded to the Library.`);
  throw new Redirect('index.php?folder=' + LIBRARY);
}

async function replaceForm(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'media.upload', 'index.php');
  const p = ctx.query.get('path') ?? '';
  const kind = kindOf(p);
  if (fileFromPath(p) === null || (kind !== 'image' && kind !== 'pdf')) {
    flashSet(ctx.session, 'error', 'That file could not be found.');
    throw new Redirect('index.php');
  }
  const isUsed = (await referencedPaths()).has(p);
  const body = `
<div class="module-header">
  <h1>Replace File</h1>
  <a href="index.php" class="link-back">&larr; Back to Media Manager</a>
</div>
<form method="post" action="replace-save.php" enctype="multipart/form-data" class="admin-form">
  ${csrfField(ctx)}
  <input type="hidden" name="path" value="${e(p)}">
  <div class="field">
    <span class="field-label">Current file</span>
    <div class="current-image">
      ${kind === 'image' ? `<img src="${e(urlOf(p))}" alt="Current file">` : ''}
      <span><code>${e(p)}</code> - ${isUsed ? 'shown on the website' : 'not shown anywhere yet'}.</span>
    </div>
  </div>
  <div class="field">
    <label for="file">New ${kind === 'pdf' ? 'PDF' : 'photo'}</label>
    <input type="file" id="file" name="file" accept="${kind === 'pdf' ? '.pdf' : '.jpg,.jpeg,.png,.webp'}" required>
    <p class="field-hint">
      ${isUsed ? 'Every place on the website that shows this file will show the new one instead.' : "The new file takes this one's place."}
      The old file and its smaller copies are then deleted. ${kind === 'pdf' ? 'Up to 15MB.' : 'JPG, PNG or WebP, up to 5MB.'}
    </p>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-submit">Replace</button>
  </div>
</form>`;
  return adminPage(ctx, { title: 'Replace File', activeNav: 'Media Manager', body });
}

async function replaceSave(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'media.upload', 'index.php');
  requirePostWithCsrf(ctx);
  const p = ctx.post.path ?? '';
  const kind = kindOf(p);
  if (fileFromPath(p) === null || (kind !== 'image' && kind !== 'pdf')) {
    flashSet(ctx.session, 'error', 'That file could not be found.');
    throw new Redirect('index.php');
  }
  const back = 'replace.php?path=' + encodeURIComponent(p);
  const folder = p.includes('/') ? path.posix.dirname(p) : LIBRARY;
  const up = await handleUpload(ctx.files.file ?? null, folder, kind, null);
  if (!up.ok || up.path === null) {
    flashSet(ctx.session, 'error', up.ok ? 'Choose a file to upload.' : up.error);
    throw new Redirect(back);
  }
  const newPath = up.path;
  let changed = 0;
  try {
    await dbTransaction(async () => {
      for (const [t, c] of referenceColumns()) changed += await dbExecute(`UPDATE \`${t}\` SET \`${c}\` = :new WHERE \`${c}\` = :old`, { new: newPath, old: p });
    });
  } catch (err) {
    deleteUploadedFile(newPath);
    throw err;
  }
  deleteUploadedFile(p);
  await logActivity(ctx.user.id, 'update', 'media', `Replaced ${p} with ${newPath} (${changed} place${changed === 1 ? '' : 's'} updated)`, ctx.meta);
  flashSet(
    ctx.session,
    'success',
    changed > 0 ? `File replaced. ${changed} place${changed === 1 ? '' : 's'} on the website now show${changed === 1 ? 's' : ''} the new file.` : 'File replaced.',
  );
  throw new Redirect('index.php');
}

async function del(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'media.delete');
  requirePostWithCsrf(ctx);
  const p = ctx.post.path ?? '';
  if (p === '' || fileFromPath(p) === null) {
    flashSet(ctx.session, 'error', 'That file could not be found.');
    throw new Redirect('index.php');
  }
  if ((await referencedPaths()).has(p)) {
    flashSet(ctx.session, 'error', 'That file is still in use and cannot be deleted from here.');
    throw new Redirect('index.php');
  }
  deleteUploadedFile(p);
  await logActivity(ctx.user.id, 'delete', 'media', p, ctx.meta);
  flashSet(ctx.session, 'success', 'File deleted.');
  throw new Redirect('index.php');
}

export const mediaRoutes: Routes = {
  'index.php': index,
  'upload.php': uploadForm,
  'upload-save.php': uploadSave,
  'replace.php': replaceForm,
  'replace-save.php': replaceSave,
  'delete.php': del,
};
