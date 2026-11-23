/** Port of admin/modules/submissions/*.php */
import 'server-only';
import { e } from '../../html';
import { dbFetchAll, dbFetchColumn, dbFetchOne, dbIdPlaceholders, dbExecute } from '../../db';
import { ccIsActive, ccSyncSubmission } from '../../constant-contact';
import { SUBMISSION_FORMS, SUBMISSION_STATUSES, submissionFormLabel, submissionStatusLabel, submissionSubjectLabel, submissionUnreadCount } from '../../forms';
import { mysqlDateTime } from '../../time';
import { logActivity } from '../activity';
import { Redirect, csrfField, postId, queryId, requireCapability, requirePostWithCsrf, type PublicCtx } from '../context';
import { can, flashSet } from '../session';
import { adminPage, listOrderLimit, listPagination, listParams, listSortHeader, listToolbar, listUrl, listWhere } from '../ui';
import { int, mb_substr, phpDate, type Routes } from './util';

const BULK: Record<string, { label: string; done: string; status: string | null }> = {
  read: { label: 'Mark as Read', done: 'marked as read', status: 'read' },
  replied: { label: 'Mark as Replied', done: 'marked as replied', status: 'replied' },
  archived: { label: 'Archive', done: 'archived', status: 'archived' },
  new: { label: 'Mark as New (unread)', done: 'marked as new', status: 'new' },
  trash: { label: 'Move to Trash', done: 'moved to Trash', status: null },
};

function syncState(row: Record<string, any>, active: boolean): 'off' | 'synced' | 'pending' | 'failed' {
  if ((row.form_type ?? '') !== 'newsletter' || !active) return 'off';
  if (row.newsletter_synced_at) return 'synced';
  return row.newsletter_sync_error ? 'failed' : 'pending';
}

function syncBadge(row: Record<string, any>, active: boolean): string {
  const labels: Record<string, [string, string]> = { synced: ['On mailing list', 'replied'], failed: ['Not added to list', 'new'], pending: ['Not sent to list', 'archived'] };
  const state = syncState(row, active);
  if (!labels[state]) return '';
  const [label, variant] = labels[state];
  const title = state === 'failed' ? ` title="${e(String(row.newsletter_sync_error))}"` : '';
  return `<span class="status-badge status-badge--${variant}"${title}>${e(label)}</span>`;
}

async function setStatus(ids: number[], status: string, userId: number): Promise<number> {
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length || !(status in SUBMISSION_STATUSES)) return 0;
  return dbExecute(`UPDATE form_submissions SET status = ?, handled_by = ?, handled_at = ? WHERE id IN (${sql}) AND deleted_at IS NULL`, [
    status,
    status === 'new' ? null : userId,
    status === 'new' ? null : mysqlDateTime(),
    ...clean,
  ]);
}

async function index(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'submissions.view', '../../dashboard.php');
  const sortable = { received: 'created_at', name: 'name', email: 'email', form: 'form_type', status: 'status' };
  const params = listParams(ctx, sortable, 'received', ['form_type', 'status']);
  if (!ctx.query.has('dir')) params.dir = 'DESC';
  const where = listWhere(params, ['name', 'email', 'subject', 'message'], { form_type: 'form_type', status: 'status' }, ['deleted_at IS NULL']);
  const total = Number(await dbFetchColumn('SELECT COUNT(*) FROM form_submissions' + where.sql, where.params, 0));
  const rows = await dbFetchAll(
    `SELECT id, form_type, name, email, subject, message, status, created_at, newsletter_synced_at, newsletter_sync_error
     FROM form_submissions` + where.sql + listOrderLimit(params, 'id DESC'),
    where.params,
  );
  const ccActive = await ccIsActive().catch(() => false);
  const unread = await submissionUnreadCount();
  const isFiltered = params.q !== '' || Object.keys(params.filters).length > 0;
  const exportQuery = listUrl(ctx, { page: null });
  const exportUrl = 'export.php' + (exportQuery === '?' ? '' : exportQuery);
  const formOptions = Object.fromEntries(Object.entries(SUBMISSION_FORMS).map(([k, f]) => [k, f.label]));
  const canManage = can(ctx.session, 'submissions.manage');
  const bulkBar = canManage
    ? `<div class="bulk-bar" data-bulk-bar hidden><span class="bulk-bar-count" data-bulk-count aria-live="polite"></span><label class="sr-only" for="bulk-action">Bulk action</label><select id="bulk-action" name="bulk_action" form="bulk-form" required><option value="">Choose an action…</option>${Object.entries(
        BULK,
      )
        .map(([v, a]) => `<option value="${e(v)}">${e(a.label)}</option>`)
        .join('')}</select><button type="submit" form="bulk-form" class="btn-filter">Apply</button></div>`
    : '';
  const body = `
<div class="module-header">
  <h1>Form Submissions</h1>
  ${total > 0 ? `<a href="${e(exportUrl)}" class="btn-primary">Export CSV</a>` : ''}
</div>
${
  unread > 0
    ? `<p class="submission-summary">
    <strong>${unread}</strong> new submission${unread === 1 ? '' : 's'}
    waiting to be read.
    ${(params.filters.status ?? '') !== 'new' ? `<a href="${e(listUrl(ctx, { status: 'new', page: null }))}">Show only new</a>` : ''}
  </p>`
    : ''
}
${listToolbar(ctx, params, { form_type: { label: 'Forms', options: formOptions }, status: { label: 'Statuses', options: SUBMISSION_STATUSES } }, 'Search name, email, or message…')}
${
  !rows.length
    ? `<div class="empty-state">${isFiltered ? 'No submissions match that search.' : 'No form submissions yet. Messages sent from the Contact and Volunteer pages will appear here.'}</div>`
    : `
  ${canManage ? `<form method="post" action="bulk.php" id="bulk-form">${csrfField(ctx)}</form>${bulkBar}` : ''}
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          ${canManage ? `<th class="col-check"><input type="checkbox" data-bulk-all aria-label="Select all submissions on this page"></th>` : ''}
          ${listSortHeader(ctx, 'Received', 'received', params)}
          ${listSortHeader(ctx, 'From', 'name', params)}
          ${listSortHeader(ctx, 'Form', 'form', params)}
          <th>Subject &amp; message</th>
          ${listSortHeader(ctx, 'Status', 'status', params)}
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        ${rows
          .map((row) => {
            const isNew = row.status === 'new';
            const excerpt = String(row.message ?? '').replace(/\s+/gu, ' ').trim();
            const badge = syncBadge(row, ccActive);
            return `
          <tr${isNew ? ' class="is-unread"' : ''}>
            ${canManage ? `<td class="col-check"><input type="checkbox" name="ids[]" form="bulk-form" value="${int(row.id)}" data-bulk-item aria-label="Select the message from ${e(row.name)}"></td>` : ''}
            <td>
              <div class="data-table-primary">${e(phpDate('M j, Y', row.created_at))}</div>
              <div class="data-table-secondary">${e(phpDate('g:i a', row.created_at))}</div>
            </td>
            <td>
              <div class="data-table-primary">${e(row.name)}</div>
              <div class="data-table-secondary">${e(row.email)}</div>
            </td>
            <td>${e(submissionFormLabel(row.form_type))}</td>
            <td>
              <div class="data-table-primary">${e(submissionSubjectLabel(row.form_type, row.subject))}</div>
              <div class="data-table-secondary submission-excerpt">${e([...excerpt].length > 120 ? mb_substr(excerpt, 0, 120) + '…' : excerpt)}</div>
            </td>
            <td>
              <span class="status-badge status-badge--${e(row.status)}">${e(submissionStatusLabel(row.status))}</span>
              ${badge ? `<div class="submission-sync-badge">${badge}</div>` : ''}
            </td>
            <td class="data-table-actions">
              <a href="view.php?id=${int(row.id)}" class="link-edit">${isNew ? 'Read' : 'View'}<span class="sr-only"> the message from ${e(row.name)}</span></a>
              ${
                canManage
                  ? `<form method="post" action="delete.php" data-confirm="Move the message from &quot;${e(row.name)}&quot; to Trash? You can restore it from there.">
                ${csrfField(ctx)}
                <input type="hidden" name="id" value="${int(row.id)}">
                <button type="submit" class="link-delete">Delete<span class="sr-only"> the message from ${e(row.name)}</span></button>
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
  ${listPagination(ctx, total, params)}`
}`;
  return adminPage(ctx, { title: 'Form Submissions', activeNav: 'Form Submissions', body });
}

async function view(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'submissions.view', '../../dashboard.php');
  const id = queryId(ctx);
  if (id === null) throw new Redirect('index.php');
  const s = await dbFetchOne(
    `SELECT s.*, u.name AS handled_by_name FROM form_submissions s LEFT JOIN admin_users u ON u.id = s.handled_by
     WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1`,
    { id },
  );
  if (!s) {
    flashSet(ctx.session, 'error', 'That submission could not be found.');
    throw new Redirect('index.php');
  }
  const canManage = can(ctx.session, 'submissions.manage');
  if (s.status === 'new' && canManage) {
    await setStatus([id], 'read', ctx.user.id);
    s.status = 'read';
    s.handled_by_name = ctx.user.name;
    s.handled_at = mysqlDateTime();
  }
  const pageTitle = 'Submission from ' + s.name;
  const formLabel = submissionFormLabel(s.form_type);
  const subjectLabel = submissionSubjectLabel(s.form_type, s.subject);
  const state = syncState(s, await ccIsActive().catch(() => false));
  const replySubject = `Re: ${subjectLabel} — Worldwide Education Foundation`;
  const replyBody = `Hello ${s.name},\n\n\n\n---\nYou wrote to us on ${phpDate('j F Y', s.created_at)}:\n${s.message}`;
  const replyHref = 'mailto:' + encodeURIComponent(s.email) + '?subject=' + encodeURIComponent(replySubject) + '&body=' + encodeURIComponent(replyBody);
  const body = `
<div class="module-header">
  <h1>Submission from ${e(s.name)}</h1>
  <a href="index.php" class="link-back">&larr; Back to Form Submissions</a>
</div>
<div class="submission-detail">
  <div class="panel">
    <div class="panel-header">
      <h2>${e(subjectLabel)}</h2>
      <span class="status-badge status-badge--${e(s.status)}">${e(submissionStatusLabel(s.status))}</span>
    </div>
    <dl class="submission-meta">
      <div><dt>From</dt><dd>${e(s.name)}</dd></div>
      <div><dt>Email</dt><dd><a href="mailto:${e(s.email)}">${e(s.email)}</a></dd></div>
      <div><dt>Form</dt><dd>${e(formLabel)}</dd></div>
      <div><dt>Received</dt><dd><time datetime="${e(s.created_at)}">${e(phpDate('j F Y \\a\\t g:i a', s.created_at))}</time></dd></div>
      ${
        s.handled_at !== null
          ? `<div><dt>Last handled</dt><dd>${e(s.handled_by_name ?? 'Deleted user')} on ${e(phpDate('j M Y \\a\\t g:i a', s.handled_at))}</dd></div>`
          : ''
      }
      ${
        state !== 'off'
          ? `<div><dt>Mailing list</dt><dd>${
              state === 'synced'
                ? `Added to Constant Contact on ${e(phpDate('j M Y \\a\\t g:i a', s.newsletter_synced_at))}`
                : state === 'failed'
                  ? `<strong>Not added.</strong> <span class="data-table-secondary">${e(String(s.newsletter_sync_error))}</span>`
                  : `<strong>Not sent.</strong> <span class="data-table-secondary">This signup arrived before Constant Contact was connected, so it was never offered to the list.</span>`
            }</dd></div>`
          : ''
      }
      ${can(ctx.session, 'activity.view') ? `<div><dt>Sender IP</dt><dd class="data-table-secondary">${e(s.ip_address)}</dd></div>` : ''}
    </dl>
    ${
      (state === 'failed' || state === 'pending') && canManage
        ? `<form method="post" action="retry-sync.php" class="form-actions">
      ${csrfField(ctx)}
      <input type="hidden" name="id" value="${int(s.id)}">
      <button type="submit" class="btn-filter">Add this address to the mailing list</button>
    </form>`
        : ''
    }
    <div class="submission-message">${e(s.message)}</div>
  </div>
  ${
    canManage
      ? `<form method="post" action="status.php" class="admin-form">
    ${csrfField(ctx)}
    <input type="hidden" name="id" value="${int(s.id)}">
    <h2 class="admin-form-title">Handle this submission</h2>
    <div class="field">
      <label for="submission-status">Status</label>
      <select id="submission-status" name="status" required>
        ${Object.entries(SUBMISSION_STATUSES)
          .map(([v, l]) => `<option value="${e(v)}"${s.status === v ? ' selected' : ''}>${e(l)}</option>`)
          .join('')}
      </select>
    </div>
    <div class="field">
      <label for="submission-note">Internal note</label>
      <textarea id="submission-note" name="admin_note" rows="4" maxlength="1000" placeholder="Who is following this up, and what was agreed?">${e(s.admin_note ?? '')}</textarea>
      <p class="field-hint">Only visible here. The sender never sees this.</p>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn-submit">Save</button>
      <a href="${e(replyHref)}" class="btn-filter">Reply by email</a>
    </div>
  </form>
  <form method="post" action="delete.php" class="submission-danger" data-confirm="Move the message from &quot;${e(s.name)}&quot; to Trash? You can restore it from there.">
    ${csrfField(ctx)}
    <input type="hidden" name="id" value="${int(s.id)}">
    <button type="submit" class="link-delete">Move this submission to Trash</button>
  </form>`
      : ''
  }
</div>`;
  return adminPage(ctx, { title: pageTitle, activeNav: 'Form Submissions', body });
}

async function status(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'submissions.manage', 'index.php');
  requirePostWithCsrf(ctx, 'index.php');
  const id = postId(ctx);
  if (id === null) throw new Redirect('index.php');
  const sub = await dbFetchOne('SELECT name, status FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id });
  if (!sub) {
    flashSet(ctx.session, 'error', 'That submission could not be found.');
    throw new Redirect('index.php');
  }
  const st = ctx.post.status ?? '';
  if (!(st in SUBMISSION_STATUSES)) {
    flashSet(ctx.session, 'error', 'Please choose one of the listed statuses.');
    throw new Redirect('view.php?id=' + id);
  }
  let note: string | null = (ctx.post.admin_note ?? '').trim();
  note = note === '' ? null : mb_substr(note, 0, 1000);
  await setStatus([id], st, ctx.user.id);
  await dbExecute('UPDATE form_submissions SET admin_note = :note WHERE id = :id AND deleted_at IS NULL', { note, id });
  await logActivity(ctx.user.id, 'update', 'submissions', `${sub.name} → ${SUBMISSION_STATUSES[st]}`, ctx.meta);
  flashSet(ctx.session, 'success', 'Submission updated.');
  throw new Redirect('view.php?id=' + id);
}

async function bulk(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'submissions.manage', 'index.php');
  requirePostWithCsrf(ctx, 'index.php');
  const action = ctx.post.bulk_action ?? '';
  if (!BULK[action]) {
    flashSet(ctx.session, 'error', 'Please choose an action to apply.');
    throw new Redirect('index.php');
  }
  const ids = dbIdPlaceholders(ctx.postAll['ids[]'] ?? []).ids;
  if (!ids.length) {
    flashSet(ctx.session, 'error', 'No submissions were selected.');
    throw new Redirect('index.php');
  }
  const cfg = BULK[action];
  const count =
    cfg.status === null
      ? Number(await dbExecute(`UPDATE form_submissions SET deleted_at = ? WHERE id IN (${ids.map(() => '?').join(',')}) AND deleted_at IS NULL`, [mysqlDateTime(), ...ids]))
      : await setStatus(ids, cfg.status, ctx.user.id);
  await logActivity(ctx.user.id, action === 'trash' ? 'delete' : 'update', 'submissions', `Bulk ${cfg.done} ${count} submission(s)`, ctx.meta);
  flashSet(ctx.session, 'success', `${count} submission${count === 1 ? '' : 's'} ${cfg.done}.`);
  throw new Redirect('index.php');
}

async function del(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'submissions.manage', 'index.php');
  requirePostWithCsrf(ctx, 'index.php');
  const id = postId(ctx);
  if (id === null) throw new Redirect('index.php');
  const sub = await dbFetchOne('SELECT name FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id });
  if (!sub) {
    flashSet(ctx.session, 'error', 'That submission could not be found.');
    throw new Redirect('index.php');
  }
  await dbExecute('UPDATE form_submissions SET deleted_at = :at WHERE id = :id AND deleted_at IS NULL', { at: mysqlDateTime(), id });
  await logActivity(ctx.user.id, 'delete', 'submissions', 'Submission from ' + sub.name, ctx.meta);
  flashSet(ctx.session, 'success', 'Submission moved to Trash. You can restore it from Trash.');
  throw new Redirect('index.php');
}

async function exportCsv(pctx: PublicCtx): Promise<Response> {
  const ctx = await requireCapability(pctx, 'submissions.view', '../../dashboard.php');
  const sortable = { received: 's.created_at', name: 's.name', email: 's.email', form: 's.form_type', status: 's.status' };
  const params = listParams(ctx, sortable, 'received', ['form_type', 'status']);
  if (!ctx.query.has('dir')) params.dir = 'DESC';
  const where = listWhere(params, ['s.name', 's.email', 's.subject', 's.message'], { form_type: 's.form_type', status: 's.status' }, ['s.deleted_at IS NULL']);
  const fromSql = ' FROM form_submissions s LEFT JOIN admin_users u ON u.id = s.handled_by' + where.sql;
  const total = Number(await dbFetchColumn('SELECT COUNT(*)' + fromSql, where.params, 0));
  await logActivity(ctx.user.id, 'export', 'submissions', `Exported ${total} submission(s) to CSV`, ctx.meta);
  const safe = (v: unknown) => {
    const s = String(v ?? '');
    return s !== '' && '=+-@\t\r'.includes(s[0]) ? "'" + s : s;
  };
  const cell = (v: unknown) => {
    const s = String(v ?? '');
    return /[",\n\r]/.test(s) || s.includes(' ') ? '"' + s.replace(/"/g, '""') + '"' : s;
  };
  const lines = [['ID', 'Received', 'Form', 'Name', 'Email', 'Subject', 'Message', 'Status', 'Handled by', 'Handled at', 'Internal note', 'IP address'].map(cell).join(',')];
  const rows = await dbFetchAll(
    `SELECT s.id, s.form_type, s.name, s.email, s.subject, s.message, s.status, s.admin_note, s.ip_address, s.created_at, s.handled_at, u.name AS handled_by_name` +
      fromSql +
      ` ORDER BY ${params.sortSql} ${params.dir}, s.id DESC`,
    where.params,
  );
  for (const r of rows)
    lines.push(
      [
        r.id,
        phpDate('Y-m-d H:i:s', r.created_at),
        submissionFormLabel(r.form_type),
        safe(r.name),
        safe(r.email),
        safe(submissionSubjectLabel(r.form_type, r.subject)),
        safe(r.message),
        submissionStatusLabel(r.status),
        safe(r.handled_by_name),
        r.handled_at === null ? '' : phpDate('Y-m-d H:i:s', r.handled_at),
        safe(r.admin_note),
        r.ip_address,
      ]
        .map(cell)
        .join(','),
    );
  return new Response('﻿' + lines.join('\n') + '\n', {
    headers: {
      'Content-Type': 'text/csv; charset=UTF-8',
      'Content-Disposition': `attachment; filename="wef-submissions-${phpDate('Y-m-d', new Date())}.csv"`,
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
    },
  });
}

async function retrySync(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'submissions.manage', '../../dashboard.php');
  requirePostWithCsrf(ctx);
  const id = postId(ctx);
  if (id === null) throw new Redirect('index.php');
  const sub = await dbFetchOne('SELECT id, form_type, email, newsletter_synced_at FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id });
  if (!sub) {
    flashSet(ctx.session, 'error', 'That submission could not be found.');
    throw new Redirect('index.php');
  }
  const back = 'view.php?id=' + id;
  if (sub.form_type !== 'newsletter') {
    flashSet(ctx.session, 'error', 'Only newsletter signups can be added to the mailing list.');
    throw new Redirect(back);
  }
  if (sub.newsletter_synced_at) {
    flashSet(ctx.session, 'success', 'That address is already on the mailing list.');
    throw new Redirect(back);
  }
  if (!(await ccIsActive())) {
    flashSet(ctx.session, 'error', 'Constant Contact is not connected, or no mailing list has been chosen yet.');
    throw new Redirect(back);
  }
  const r = await ccSyncSubmission(id, String(sub.email));
  if (r.ok) {
    await logActivity(ctx.user.id, 'update', 'submissions', `Added submission #${id} to the Constant Contact list`, ctx.meta);
    flashSet(ctx.session, 'success', `Added ${sub.email} to the mailing list.`);
  } else flashSet(ctx.session, 'error', 'Constant Contact did not accept that address: ' + (r.error ?? 'unknown error'));
  throw new Redirect(back);
}


export const submissionsRoutes: Routes = {
  'index.php': index,
  'view.php': view,
  'status.php': status,
  'bulk.php': bulk,
  'delete.php': del,
  'export.php': exportCsv,
  'retry-sync.php': retrySync,
};
