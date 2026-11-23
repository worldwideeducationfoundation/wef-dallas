/**
 * The save / delete / bulk / reorder endpoints every content module shares
 * (ports of each module's save.php, delete.php, bulk.php, reorder.php and
 * admin/includes/{trash,bulk,reorder}.php).
 */
import 'server-only';
import { NextResponse } from 'next/server';
import { bulkAction, reorderItems, saveItem, trashItems, type Actor } from './crud';
import { getResource, type Resource } from './resources';
import { can, csrfVerify, flashInputSet, flashSet } from './session';
import { Redirect, postId, requireCapability, requireLogin, requirePostWithCsrf, type Ctx, type PublicCtx } from './context';
import { dbFetchOne } from '../db';

export const SHORT_LABELS: Record<string, string> = {
  gallery: 'Photo',
  reports: 'Report',
  updates: 'Update',
  'completed-projects': 'Project',
  'success-stories': 'Story',
  'mission-projects': 'Project',
  'impact-milestones': 'Milestone',
  'hero-slides': 'Slide',
  partners: 'Partner',
  'donate-options': 'Option',
};

export const actorOf = (ctx: Ctx): Actor => ({ userId: ctx.user.id, meta: ctx.meta, via: 'admin' });

/** save.php: validate, store, flash, redirect. */
export async function handleSave(ctx: PublicCtx, resourceKey: string, opts: { formPath?: string; indexPath?: string; afterSave?: (c: Ctx, id: number, isEdit: boolean) => Promise<string | void> } = {}): Promise<never> {
  const formPath = opts.formPath ?? 'form.php';
  const indexPath = opts.indexPath ?? 'index.php';
  const r = getResource(resourceKey)!;
  let c = await requireLogin(ctx);
  requirePostWithCsrf(c);
  const id = c.post.id !== undefined && c.post.id !== '' ? Math.trunc(Number(c.post.id)) || 0 : null;
  const isEdit = id !== null;
  c = await requireCapability(c, isEdit ? 'content.edit' : 'content.create');
  const back = isEdit ? `${formPath}?id=${id}` : formPath;
  const result = await saveItem(r, id, c.post, c.files, actorOf(c));
  if (!result.ok) {
    flashInputSet(c.session, { ...c.post, id });
    flashSet(c.session, 'error', result.status === 404 ? `That ${r.label.toLowerCase()} could not be found.` : result.errors.join(' '));
    throw new Redirect(result.status === 404 ? indexPath : back);
  }
  flashSet(c.session, 'success', result.message);
  const next = opts.afterSave ? await opts.afterSave(c, result.id, isEdit) : undefined;
  throw new Redirect(next ?? indexPath);
}

/** delete.php → processDelete(type) */
export async function handleDelete(ctx: PublicCtx, resourceKey: string, redirectTo = 'index.php'): Promise<never> {
  const r = getResource(resourceKey)!;
  const c = await requireCapability(ctx, 'content.delete', redirectTo);
  requirePostWithCsrf(c, redirectTo);
  const id = postId(c);
  if (id === null) throw new Redirect(redirectTo);
  const noun = SHORT_LABELS[r.key] ?? r.label;
  const row = await dbFetchOne(`SELECT id FROM ${r.table} WHERE id = :id${r.softDelete ? ' AND deleted_at IS NULL' : ''} LIMIT 1`, { id });
  if (!row) {
    flashSet(c.session, 'error', `That ${noun} could not be found.`);
    throw new Redirect(redirectTo);
  }
  await trashItems(r, [id], actorOf(c));
  flashSet(c.session, 'success', r.softDelete ? `${noun} moved to Trash. You can restore it from Trash.` : `${noun} deleted.`);
  throw new Redirect(redirectTo);
}

/** bulk.php → processBulkAction() */
export async function handleBulk(ctx: PublicCtx, resourceKey: string, redirectTo = 'index.php'): Promise<never> {
  const r: Resource = getResource(resourceKey)!;
  const c = await requireLogin(ctx);
  if (c.method !== 'POST') throw new Redirect(redirectTo);
  if (!csrfVerify(c.session, c.post.csrf_token)) {
    flashSet(c.session, 'error', 'Your session expired — please try again.');
    throw new Redirect(redirectTo);
  }
  const action = c.post.bulk_action ?? '';
  const caps: Record<string, string> = { publish: 'content.edit', draft: 'content.edit', delete: 'content.delete' };
  if (!caps[action]) {
    flashSet(c.session, 'error', 'Please choose an action to apply.');
    throw new Redirect(redirectTo);
  }
  if (!can(c.session, caps[action])) {
    flashSet(c.session, 'error', 'You do not have permission to do that.');
    throw new Redirect(redirectTo);
  }
  const ids = (c.postAll['ids[]'] ?? c.postAll.ids ?? []).map((x) => Math.trunc(Number(x))).filter((n) => n > 0);
  const res = await bulkAction(r, action, ids, actorOf(c));
  flashSet(c.session, res.ok ? 'success' : 'error', res.message);
  throw new Redirect(redirectTo);
}

/** reorder.php → processReorder() (JSON) */
export async function handleReorder(ctx: PublicCtx, resourceKey: string): Promise<Response> {
  const r = getResource(resourceKey)!;
  const json = (status: number, body: unknown) => NextResponse.json(body, { status });
  if (ctx.method !== 'POST') return json(405, { error: 'Method not allowed.' });
  if (!csrfVerify(ctx.session, ctx.post.csrf_token)) return json(403, { error: 'Your session expired — please reload the page.' });
  let c: Ctx;
  try {
    c = await requireLogin(ctx);
  } catch {
    return json(403, { error: 'Your session expired — please reload the page.' });
  }
  if (!can(c.session, 'content.edit')) return json(403, { error: 'You do not have permission to reorder items.' });
  const ids = (c.postAll['ids[]'] ?? c.postAll.ids ?? []).map((x) => Math.trunc(Number(x))).filter((n) => n > 0);
  if (!ids.length) return json(400, { error: 'No items were supplied.' });
  const count = await reorderItems(r, [...new Set(ids)], actorOf(c));
  return json(200, { ok: true, count });
}
