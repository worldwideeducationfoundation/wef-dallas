/**
 * Shared shape of the "list + form" admin modules: the PHP index.php/form.php
 * heads (permission, query, list params) with each module's own markup from
 * ./bodies/<module>.ts.
 */
import 'server-only';
import { dbFetchAll, dbFetchColumn, dbFetchOne } from '../../db';
import { Redirect, requireCapability, type Ctx } from '../context';
import { handleBulk, handleDelete, handleReorder, handleSave } from '../generic';
import { flashInputGet, flashSet } from '../session';
import { adminPage, duplicateOrders, listOrderLimit, listParams, listToolbar, listWhere, reorderEnabled } from '../ui';
import type { Routes } from './util';
import { UPLOAD_IMAGE_MAX_BYTES } from '../uploads';

export type SimpleSpec = {
  resource: string;
  table: string;
  title: string;
  activeNav: string;
  sortable: Record<string, string>;
  search: string[];
  select: string;
  tieBreaker: string | ((p: { sort: string }) => string);
  filterKeys?: string[];
  filterColumns?: Record<string, string>;
  toolbarFilters?: (ctx: Ctx) => Promise<Record<string, { label: string; options: Record<string, string> }>>;
  reorderScope?: string[];
  dupeGroup?: string;
  formStyles?: string[];
  formScripts?: string[];
  placeholder: string;
  addTitle: string;
  editTitle: string;
  notFound: string;
  defaults: Record<string, unknown> | ((ctx: Ctx) => Promise<Record<string, unknown>>);
  indexBody: (ctx: Ctx, d: Record<string, any>) => string;
  formBody: (ctx: Ctx, d: Record<string, any>) => string;
  indexExtra?: (ctx: Ctx) => Promise<Record<string, unknown>> | Record<string, unknown>;
  afterSave?: (c: Ctx, id: number, isEdit: boolean) => Promise<string | void>;
  formExtra?: (ctx: Ctx) => Promise<Record<string, unknown>> | Record<string, unknown>;
};

export const STATUS_FILTER = { status: { label: 'Statuses', options: { active: 'Active', draft: 'Draft' } } };

export function simpleModule(s: SimpleSpec): Routes {
  return {
    'index.php': async (pctx) => {
      const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
      const params = listParams(ctx, s.sortable, 'default', s.filterKeys ?? ['status']);
      const where = listWhere(params, s.search, s.filterColumns ?? { status: 'status' }, ['deleted_at IS NULL']);
      const total = Number(await dbFetchColumn(`SELECT COUNT(*) FROM ${s.table}` + where.sql, where.params, 0));
      const rows = await dbFetchAll(`SELECT ${s.select} FROM ${s.table}` + where.sql + listOrderLimit(params, typeof s.tieBreaker === 'function' ? s.tieBreaker(params) : s.tieBreaker), where.params);
      const d = {
        params,
        total,
        rows,
        isFiltered: params.q !== '' || Object.keys(params.filters).length > 0,
        canReorder: reorderEnabled(ctx, params, s.reorderScope ?? []),
        dupeOrders: duplicateOrders(rows, s.dupeGroup ?? null),
        toolbar: listToolbar(ctx, params, s.toolbarFilters ? await s.toolbarFilters(ctx) : STATUS_FILTER, s.placeholder),
        ...(s.indexExtra ? await s.indexExtra(ctx) : {}),
      };
      return adminPage(ctx, { title: s.title, activeNav: s.activeNav, body: s.indexBody(ctx, d) });
    },
    'form.php': async (pctx) => {
      const ctx = await requireCapability(pctx, 'content.view', '../../dashboard.php');
      const id = ctx.query.has('id') ? Math.trunc(Number(ctx.query.get('id'))) || 0 : null;
      const isEdit = id !== null;
      let existing: Record<string, any> | null = null;
      if (isEdit) {
        existing = await dbFetchOne(`SELECT * FROM ${s.table} WHERE id = :id AND deleted_at IS NULL LIMIT 1`, { id });
        if (!existing) {
          flashSet(ctx.session, 'error', s.notFound);
          throw new Redirect('index.php');
        }
      }
      const pageTitle = isEdit ? s.editTitle : s.addTitle;
      const values = flashInputGet(ctx.session) ?? existing ?? (typeof s.defaults === 'function' ? await s.defaults(ctx) : { ...s.defaults });
      const d = {
        pageTitle,
        isEdit,
        id,
        existing,
        values,
        maxMb: Math.trunc(UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024)),
        ...(s.formExtra ? await s.formExtra(ctx) : {}),
      };
      return adminPage(ctx, { title: pageTitle, activeNav: s.activeNav, body: s.formBody(ctx, d), extraStyles: s.formStyles, extraScripts: s.formScripts });
    },
    'save.php': (ctx) => handleSave(ctx, s.resource, { afterSave: s.afterSave }),
    'delete.php': (ctx) => handleDelete(ctx, s.resource),
    'bulk.php': (ctx) => handleBulk(ctx, s.resource),
    'reorder.php': (ctx) => handleReorder(ctx, s.resource),
  };
}
