/**
 * Create / read / update / trash / restore / purge / reorder / bulk for every
 * resource in resources.ts. Ports of each module's save.php plus
 * admin/includes/{bulk,trash,reorder,listing}.php. Used by the admin API and
 * the admin screens.
 */
import 'server-only';
import { dbExecute, dbFetchAll, dbFetchColumn, dbFetchOne, dbIdPlaceholders, dbInsert, dbTransaction } from '../db';
import { mysqlDateTime } from '../time';
import { logActivity, type RequestMeta } from './activity';
import { type Resource, type Field, RESOURCES } from './resources';
import { deleteUploadedFile, handleUpload, type UploadInput } from './uploads';

export type Actor = { userId: number | null; meta: RequestMeta; via?: 'api' | 'admin' };
export type Files = Record<string, UploadInput | null | undefined>;
export type SaveResult = { ok: true; id: number; message: string; row: Record<string, any> } | { ok: false; errors: string[]; status: number };

const phpInt = (v: unknown): number => {
  const n = Number.parseInt(String(v ?? '').trim(), 10);
  return Number.isNaN(n) ? 0 : n;
};

function convert(field: Field, raw: unknown): unknown {
  switch (field.type) {
    case 'int':
      if (field.nullable && String(raw ?? '').trim() === '') return null;
      return phpInt(raw);
    case 'fk':
      return raw === null || String(raw ?? '') === '' ? null : phpInt(raw);
    case 'decimal':
      return String(raw ?? '').trim();
    case 'enum':
      return String(raw ?? field.default ?? '');
    default: {
      const s = String(raw ?? '').trim();
      return field.nullable && s === '' ? null : s;
    }
  }
}

/** Builds the full input row: given values win, then (on edit) the stored row, then defaults. */
export function buildInput(resource: Resource, raw: Record<string, unknown>, existing: Record<string, any> | null): Record<string, any> {
  const input: Record<string, any> = {};
  for (const f of resource.fields) {
    if (Object.prototype.hasOwnProperty.call(raw, f.name)) input[f.name] = convert(f, raw[f.name]);
    else if (existing && f.name in existing) input[f.name] = existing[f.name] === null ? null : convert(f, existing[f.name]);
    else input[f.name] = convert(f, f.default ?? (f.type === 'int' ? 0 : ''));
  }
  return input;
}

const label = (r: Resource, row: Record<string, any>) => String(row[r.labelColumn] ?? '');
const truthy = (v: unknown) => v === true || v === 1 || v === '1' || v === 'true' || v === 'on';

export async function getItem(resource: Resource, id: number, includeTrashed = true): Promise<Record<string, any> | null> {
  const trashed = resource.softDelete && !includeTrashed ? ' AND deleted_at IS NULL' : '';
  return dbFetchOne(`SELECT * FROM ${resource.table} WHERE id = :id${trashed} LIMIT 1`, { id });
}

export async function saveItem(
  resource: Resource,
  id: number | null,
  raw: Record<string, unknown>,
  files: Files,
  actor: Actor,
): Promise<SaveResult> {
  const isEdit = id !== null;
  let existing: Record<string, any> | null = null;
  if (isEdit) {
    existing = await getItem(resource, id!, false);
    if (!existing) return { ok: false, errors: [`That ${resource.label.toLowerCase()} could not be found.`], status: 404 };
  }
  const input = buildInput(resource, raw, existing);
  await resource.prepare?.(input);
  const errors = await resource.validate(input, { id, isEdit });
  for (const u of resource.uploads) {
    if (!isEdit && u.requiredOnCreate && !files[u.field]?.bytes?.length) errors.push(u.requiredOnCreate);
  }
  for (const f of resource.fields) {
    if (f.max && typeof input[f.name] === 'string' && [...input[f.name]].length > f.max && !errors.length) {
      errors.push(`${f.label} must be ${f.max} characters or fewer.`);
    }
  }
  if (errors.length) return { ok: false, errors, status: 422 };

  for (const u of resource.uploads) {
    const remove = u.removable && truthy(raw['remove_' + u.field]);
    const result = await handleUpload(files[u.field] ?? null, u.folder, u.kind, existing?.[u.column] ?? null, remove);
    if (!result.ok) return { ok: false, errors: [result.error], status: 422 };
    input[u.column] = result.path;
  }

  const columns = Object.keys(input);
  let newId: number;
  if (isEdit) {
    await dbExecute(`UPDATE ${resource.table} SET ${columns.map((c) => `${c} = :${c}`).join(', ')} WHERE id = :row_id`, { ...input, row_id: id });
    newId = id!;
  } else {
    newId = await dbInsert(`INSERT INTO ${resource.table} (${columns.join(', ')}) VALUES (${columns.map((c) => ':' + c).join(', ')})`, input);
  }
  await logActivity(actor.userId, isEdit ? 'update' : 'create', resource.module, label(resource, input) + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
  const row = (await getItem(resource, newId)) ?? { id: newId, ...input };
  return { ok: true, id: newId, message: isEdit ? resource.updatedMessage : resource.createdMessage, row };
}

export type ListParams = {
  status?: string;
  q?: string;
  trashed?: boolean;
  filters?: Record<string, string>;
  sort?: string;
  dir?: 'ASC' | 'DESC';
  limit?: number;
  offset?: number;
};

export async function listItems(resource: Resource, p: ListParams = {}) {
  const where: string[] = [];
  const params: Record<string, unknown> = {};
  if (resource.softDelete) where.push(p.trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL');
  if (resource.hasStatus && p.status && ['draft', 'active'].includes(p.status)) {
    where.push('status = :status');
    params.status = p.status;
  }
  for (const [k, v] of Object.entries(p.filters ?? {})) {
    if (!(resource.filters ?? []).includes(k) || v === '') continue;
    where.push(`${k} = :f_${k}`);
    params['f_' + k] = v;
  }
  if (p.q && p.q.trim() !== '') {
    where.push('(' + resource.searchColumns.map((c) => `${c} LIKE :q`).join(' OR ') + ')');
    params.q = '%' + p.q.trim().replace(/[\\%_]/g, (m) => '\\' + m) + '%';
  }
  const columns = new Set(['id', ...resource.fields.map((f) => f.name), ...resource.uploads.map((u) => u.column)]);
  const sort = p.sort && (columns.has(p.sort) || ['created_at', 'updated_at'].includes(p.sort)) ? p.sort : resource.defaultSort;
  const dir = p.dir === 'DESC' ? 'DESC' : 'ASC';
  const order = sort === 'display_order' ? `display_order ${dir}, ${resource.labelColumn} ${dir}, id` : `${sort} ${dir}, id`;
  const whereSql = where.length ? 'WHERE ' + where.join(' AND ') : '';
  const limit = Math.min(Math.max(p.limit ?? 100, 1), 1000);
  const offset = Math.max(p.offset ?? 0, 0);
  const total = Number(await dbFetchColumn(`SELECT COUNT(*) FROM ${resource.table} ${whereSql}`, params, 0));
  const items = await dbFetchAll(`SELECT * FROM ${resource.table} ${whereSql} ORDER BY ${order} LIMIT ${limit} OFFSET ${offset}`, params);
  return { total, limit, offset, items };
}

/** Move to Trash (soft delete); sponsor groups take their sponsors with them. Resources without trash are deleted outright. */
export async function trashItems(resource: Resource, ids: number[], actor: Actor): Promise<number> {
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return 0;
  if (!resource.softDelete) {
    const rows = await dbFetchAll(`SELECT * FROM ${resource.table} WHERE id IN (${sql})`, clean);
    const count = await dbExecute(`DELETE FROM ${resource.table} WHERE id IN (${sql})`, clean);
    for (const row of rows) for (const u of resource.uploads) deleteUploadedFile(row[u.column] ?? null);
    await logActivity(actor.userId, 'delete', resource.module, rows.map((r) => label(resource, r)).join(', ') + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
    return count;
  }
  const rows = await dbFetchAll(`SELECT ${resource.labelColumn} AS l FROM ${resource.table} WHERE id IN (${sql}) AND deleted_at IS NULL`, clean);
  if (!rows.length) return 0;
  const now = mysqlDateTime();
  const count = await dbTransaction(async () => {
    const c = await dbExecute(`UPDATE ${resource.table} SET deleted_at = ? WHERE id IN (${sql}) AND deleted_at IS NULL`, [now, ...clean]);
    if (resource.key === 'sponsor-groups') await dbExecute(`UPDATE sponsors SET deleted_at = ? WHERE group_id IN (${sql}) AND deleted_at IS NULL`, [now, ...clean]);
    return c;
  });
  const names = rows.map((r) => String(r.l));
  await logActivity(
    actor.userId,
    'delete',
    resource.module,
    (clean.length > 1 ? `Bulk moved ${count} to Trash: ${names.join(', ')}` : names[0]) + (actor.via === 'api' ? ' (API)' : ''),
    actor.meta,
  );
  return count;
}

export async function restoreItems(resource: Resource, ids: number[], actor: Actor): Promise<number> {
  if (!resource.softDelete) return 0;
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return 0;
  const count = await dbTransaction(async () => {
    if (resource.key === 'sponsor-groups') {
      const groups = await dbFetchAll(`SELECT id, deleted_at FROM sponsor_groups WHERE id IN (${sql}) AND deleted_at IS NOT NULL`, clean);
      for (const g of groups) await dbExecute('UPDATE sponsors SET deleted_at = NULL WHERE group_id = ? AND deleted_at = ?', [g.id, g.deleted_at]);
    }
    return dbExecute(`UPDATE ${resource.table} SET deleted_at = NULL WHERE id IN (${sql}) AND deleted_at IS NOT NULL`, clean);
  });
  if (count) await logActivity(actor.userId, 'restore', resource.module, `Restored ${count} item(s)` + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
  return count;
}

/** Permanently deletes items that are already in Trash, and their files. */
export async function purgeItems(resource: Resource, ids: number[], actor: Actor): Promise<number> {
  if (!resource.softDelete) return 0;
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return 0;
  const rows = await dbFetchAll(`SELECT * FROM ${resource.table} WHERE id IN (${sql}) AND deleted_at IS NOT NULL`, clean);
  if (!rows.length) return 0;
  const childFiles: string[] = [];
  for (const child of resource.childFiles ?? []) {
    try {
      const r = await dbFetchAll(
        `SELECT c.${child.column} AS f FROM ${child.table} c JOIN ${resource.table} p ON p.id = c.${child.foreignKey}
         WHERE c.${child.foreignKey} IN (${sql}) AND p.deleted_at IS NOT NULL`,
        clean,
      );
      childFiles.push(...r.map((x) => String(x.f)));
    } catch {
      /* ignore */
    }
  }
  const count = await dbExecute(`DELETE FROM ${resource.table} WHERE id IN (${sql}) AND deleted_at IS NOT NULL`, clean);
  for (const row of rows) for (const u of resource.uploads) deleteUploadedFile(row[u.column] ?? null);
  for (const f of childFiles) deleteUploadedFile(f);
  await logActivity(actor.userId, 'purge', resource.module, rows.map((r) => label(resource, r)).join(', ') + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
  return count;
}

/** Sets display_order to the given sequence, starting from the lowest current value (processReorder). */
export async function reorderItems(resource: Resource, ids: number[], actor: Actor): Promise<number> {
  if (!resource.hasOrder) return 0;
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return 0;
  const start = Number(await dbFetchColumn(`SELECT COALESCE(MIN(display_order), 0) FROM ${resource.table} WHERE id IN (${sql})`, clean, 0));
  await dbTransaction(async () => {
    for (const [i, id] of clean.entries()) await dbExecute(`UPDATE ${resource.table} SET display_order = ? WHERE id = ?`, [start + i, id]);
  });
  await logActivity(actor.userId, 'reorder', resource.module, `Reordered ${clean.length} item(s)` + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
  return clean.length;
}

export const BULK_ACTIONS = { publish: 'published', draft: 'moved to draft', delete: 'moved to Trash' } as const;

export async function bulkAction(resource: Resource, action: string, ids: number[], actor: Actor): Promise<{ ok: boolean; count: number; message: string }> {
  if (!(action in BULK_ACTIONS)) return { ok: false, count: 0, message: 'Please choose an action to apply.' };
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return { ok: false, count: 0, message: 'No items were selected.' };
  let count: number;
  if (action === 'delete') {
    count = await trashItems(resource, clean, actor);
    if (!count) return { ok: false, count: 0, message: 'Those items could not be found.' };
  } else {
    if (!resource.hasStatus) return { ok: false, count: 0, message: 'This content type has no status.' };
    const newStatus = action === 'publish' ? 'active' : 'draft';
    count = await dbExecute(`UPDATE ${resource.table} SET status = ? WHERE id IN (${sql})${resource.softDelete ? ' AND deleted_at IS NULL' : ''}`, [newStatus, ...clean]);
    await logActivity(actor.userId, 'update', resource.module, `Bulk ${BULK_ACTIONS[action as 'publish']} ${clean.length} item(s)` + (actor.via === 'api' ? ' (API)' : ''), actor.meta);
  }
  const verb = BULK_ACTIONS[action as keyof typeof BULK_ACTIONS];
  return { ok: true, count, message: `${count} item${count === 1 ? '' : 's'} ${verb}.` };
}

/** Everything in Trash across all content types, newest first (trashedItems()). */
export async function trashedItems() {
  const items: { type: string; typeLabel: string; id: number; label: string; deleted_at: string }[] = [];
  for (const r of Object.values(RESOURCES)) {
    if (!r.softDelete) continue;
    const rows = await dbFetchAll(`SELECT id, ${r.labelColumn} AS label, deleted_at FROM ${r.table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC`);
    for (const row of rows) items.push({ type: r.key, typeLabel: r.label, id: Number(row.id), label: String(row.label ?? ''), deleted_at: String(row.deleted_at) });
  }
  const subs = await dbFetchAll('SELECT id, name AS label, deleted_at FROM form_submissions WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC');
  for (const row of subs) items.push({ type: 'submissions', typeLabel: 'Form submission', id: Number(row.id), label: String(row.label ?? ''), deleted_at: String(row.deleted_at) });
  return items.sort((a, b) => (a.deleted_at < b.deleted_at ? 1 : a.deleted_at > b.deleted_at ? -1 : 0));
}
