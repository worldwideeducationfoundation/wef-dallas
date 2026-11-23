/**
 * Admin API: everything the admin site can do, for scripts and Claude.
 * Auth: `Authorization: Bearer $ADMIN_API_KEY`. Full reference: GET /api/admin/v1
 * (also docs/ADMIN-API.md).
 *
 * Bodies are JSON, or multipart/form-data when sending files. In JSON, a file
 * field can be {"url": "https://..."} or {"base64": "...", "filename": "x.jpg"}.
 */
import { NextResponse } from 'next/server';
import { apiKeyConfigured, checkApiKey } from '@/lib/admin/api-auth';
import { requestMeta } from '@/lib/admin/activity';
import {
  bulkAction,
  getItem,
  listItems,
  purgeItems,
  reorderItems,
  restoreItems,
  saveItem,
  trashItems,
  trashedItems,
  type Actor,
  type Files,
} from '@/lib/admin/crud';
import { RESOURCES, getResource, SETTINGS_FIELDS } from '@/lib/admin/resources';
import {
  contentBlocks,
  deleteUser,
  getSettings,
  listSubmissions,
  listUsers,
  newsletterStatus,
  pagesState,
  recentActivity,
  saveContentBlocks,
  saveSettings,
  saveUser,
  setNewsletterList,
  setPageEnabled,
  submissionsCsv,
  trashSubmissions,
  updateSubmission,
} from '@/lib/admin/site-admin';
import { handleUpload, type UploadInput } from '@/lib/admin/uploads';
import { DbUnavailableError, dbFetchOne } from '@/lib/db';
import { ccFetchLists } from '@/lib/constant-contact';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

type Body = { fields: Record<string, unknown>; files: Files };
type Ctx = { params: Promise<{ path?: string[] }> };

const json = (data: unknown, status = 200) => NextResponse.json(data, { status, headers: { 'Cache-Control': 'no-store' } });
const fail = (status: number, errors: string[] | string) => json({ ok: false, errors: Array.isArray(errors) ? errors : [errors] }, status);

const MAX_REMOTE_BYTES = 15 * 1024 * 1024;

async function fileFromJson(v: unknown): Promise<UploadInput | null | string> {
  if (!v || typeof v !== 'object') return null;
  const o = v as { url?: string; base64?: string; filename?: string };
  if (typeof o.base64 === 'string') {
    const data = o.base64.replace(/^data:[^;]+;base64,/, '');
    return { bytes: Buffer.from(data, 'base64'), name: o.filename };
  }
  if (typeof o.url === 'string') {
    let u: URL;
    try {
      u = new URL(o.url);
    } catch {
      return 'File URL is not valid.';
    }
    if (u.protocol !== 'https:' || /^(localhost|127\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|\[)/i.test(u.hostname)) return 'File URL must be a public https:// address.';
    try {
      const res = await fetch(u, { redirect: 'follow', signal: AbortSignal.timeout(20000) });
      if (!res.ok) return `Could not download the file (HTTP ${res.status}).`;
      const len = Number(res.headers.get('content-length') ?? 0);
      if (len > MAX_REMOTE_BYTES) return 'The file is too large.';
      const buf = Buffer.from(await res.arrayBuffer());
      if (buf.length > MAX_REMOTE_BYTES) return 'The file is too large.';
      return { bytes: buf, name: u.pathname.split('/').pop() };
    } catch (e) {
      return 'Could not download the file: ' + (e as Error).message;
    }
  }
  return null;
}

/** Reads JSON or multipart. Returns an error string for bad input. */
async function readBody(request: Request, fileFields: string[]): Promise<Body | string> {
  const type = request.headers.get('content-type') ?? '';
  const fields: Record<string, unknown> = {};
  const files: Files = {};
  if (type.includes('multipart/form-data')) {
    const form = await request.formData();
    for (const [k, v] of form.entries()) {
      if (typeof v === 'string') {
        if (k.endsWith('[]')) fields[k.slice(0, -2)] = [...((fields[k.slice(0, -2)] as unknown[]) ?? []), v];
        else fields[k] = v;
      } else if (v.size > 0) files[k] = { bytes: Buffer.from(await v.arrayBuffer()), name: v.name };
    }
    return { fields, files };
  }
  if (type.includes('application/json')) {
    let data: unknown;
    try {
      data = await request.json();
    } catch {
      return 'The request body is not valid JSON.';
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) return 'The request body must be a JSON object.';
    for (const [k, v] of Object.entries(data as Record<string, unknown>)) {
      if (fileFields.includes(k) && v && typeof v === 'object') {
        const f = await fileFromJson(v);
        if (typeof f === 'string') return `${k}: ${f}`;
        files[k] = f;
      } else fields[k] = v;
    }
    return { fields, files };
  }
  if (request.method === 'GET' || request.method === 'DELETE' || !request.body) return { fields, files };
  return 'Send JSON (Content-Type: application/json) or multipart/form-data.';
}

const intId = (s: string | undefined) => {
  const n = Number(s);
  return Number.isInteger(n) && n > 0 ? n : null;
};
const idList = (v: unknown): number[] => (Array.isArray(v) ? v : String(v ?? '').split(',')).map((x) => Number(x)).filter((n) => Number.isInteger(n) && n > 0);

function describe() {
  const resources = Object.fromEntries(
    Object.values(RESOURCES).map((r) => [
      r.key,
      {
        label: r.label,
        plural: r.plural,
        table: r.table,
        trash: r.softDelete,
        status: r.hasStatus ? ['draft', 'active'] : null,
        filters: r.filters ?? [],
        fields: r.fields.map((f) => ({ name: f.name, label: f.label, type: f.type, max: f.max, options: f.options, nullable: !!f.nullable, default: f.default, help: f.help })),
        files: r.uploads.map((u) => ({ field: u.field, column: u.column, kind: u.kind, label: u.label, requiredOnCreate: !!u.requiredOnCreate, removable: u.removable })),
      },
    ]),
  );
  return {
    name: 'WEF admin API',
    version: 1,
    auth: 'Authorization: Bearer <ADMIN_API_KEY>',
    notes: [
      'Bodies: JSON, or multipart/form-data for files. In JSON a file field may be {"url":"https://..."} or {"base64":"...","filename":"photo.jpg"}.',
      'PATCH only changes the fields you send. Send "remove_<file field>": true to remove a removable file.',
      'Images: JPEG/PNG/WebP up to 5MB (resized copies are made automatically). PDFs up to 15MB.',
      'DELETE moves an item to Trash; DELETE ?purge=1 on an item already in Trash deletes it permanently with its files.',
      'Every change is written to the admin Activity log, marked "(API)".',
    ],
    endpoints: [
      'GET    /api/admin/v1                         this document',
      'GET    /api/admin/v1/resources               content types and their fields',
      'GET    /api/admin/v1/resources/{type}        list (?status=draft|active&q=&trashed=1&limit=&offset=&sort=&dir=ASC|DESC&<filter>=)',
      'POST   /api/admin/v1/resources/{type}        create',
      'GET    /api/admin/v1/resources/{type}/{id}   read one',
      'PATCH  /api/admin/v1/resources/{type}/{id}   update (partial)',
      'DELETE /api/admin/v1/resources/{type}/{id}   move to Trash (?purge=1 to delete permanently from Trash)',
      'POST   /api/admin/v1/resources/{type}/{id}/restore',
      'POST   /api/admin/v1/resources/{type}/reorder   {"ids":[3,1,2]}',
      'POST   /api/admin/v1/resources/{type}/bulk      {"action":"publish|draft|delete","ids":[...]}',
      'GET    /api/admin/v1/content-blocks[?page=home]  editable page text with defaults and current values',
      'PATCH  /api/admin/v1/content-blocks/{page}       {"<block_key>":"text", "reset":["<key>"]}; image blocks and header_image as files',
      'GET    /api/admin/v1/settings                PATCH /api/admin/v1/settings {contact_email, ...}',
      'GET    /api/admin/v1/pages                   PATCH /api/admin/v1/pages/{slug} {"enabled":true|false}',
      'GET    /api/admin/v1/submissions             (?form_type=contact|volunteer|newsletter&status=&q=&trashed=1)',
      'GET    /api/admin/v1/submissions/export.csv  PATCH|DELETE /api/admin/v1/submissions/{id}  POST .../{id}/restore',
      'GET    /api/admin/v1/trash                   everything in Trash',
      'GET    /api/admin/v1/activity                (?limit=&offset=)',
      'GET    /api/admin/v1/users                   POST /users, PATCH|DELETE /users/{id}',
      'GET    /api/admin/v1/newsletter              GET /newsletter/lists, PATCH /newsletter {"list_id":"..."}',
      'POST   /api/admin/v1/uploads                 {"folder":"pages","file":{...}} -> {"path":"pages/abc.jpg"}',
    ],
    resources,
    settingsFields: SETTINGS_FIELDS,
  };
}

async function handle(request: Request, ctx: Ctx): Promise<Response> {
  if (!apiKeyConfigured()) return fail(503, 'The admin API is switched off: set ADMIN_API_KEY (32+ characters) on the server.');
  if (!checkApiKey(request)) return fail(401, 'Missing or wrong API key.');

  const path = (await ctx.params).path ?? [];
  const method = request.method;
  const url = new URL(request.url);
  const qs = url.searchParams;
  const actor: Actor = { userId: null, meta: requestMeta(request), via: 'api' };
  const [section, a, b, c] = path;

  try {
    if (!section) return json(describe());

    /* ---------------- resources ---------------- */
    if (section === 'resources') {
      if (!a) return json(describe().resources);
      const r = getResource(a);
      if (!r) return fail(404, `Unknown content type "${a}". See GET /api/admin/v1/resources.`);
      const fileFields = r.uploads.map((u) => u.field);

      if (!b) {
        if (method === 'GET') {
          const filters: Record<string, string> = {};
          for (const f of r.filters ?? []) if (qs.has(f)) filters[f] = qs.get(f)!;
          return json(
            await listItems(r, {
              status: qs.get('status') ?? undefined,
              q: qs.get('q') ?? undefined,
              trashed: qs.get('trashed') === '1',
              filters,
              sort: qs.get('sort') ?? undefined,
              dir: qs.get('dir')?.toUpperCase() === 'DESC' ? 'DESC' : 'ASC',
              limit: Number(qs.get('limit') ?? 100),
              offset: Number(qs.get('offset') ?? 0),
            }),
          );
        }
        if (method === 'POST') {
          const body = await readBody(request, fileFields);
          if (typeof body === 'string') return fail(400, body);
          const res = await saveItem(r, null, body.fields, body.files, actor);
          return res.ok ? json({ ok: true, id: res.id, message: res.message, item: res.row }, 201) : fail(res.status, res.errors);
        }
        return fail(405, 'Use GET or POST.');
      }

      if (b === 'reorder' || b === 'bulk') {
        if (method !== 'POST') return fail(405, 'Use POST.');
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const ids = idList(body.fields.ids);
        if (!ids.length) return fail(422, 'No items were supplied.');
        if (b === 'reorder') return json({ ok: true, count: await reorderItems(r, ids, actor) });
        const res = await bulkAction(r, String(body.fields.action ?? ''), ids, actor);
        return res.ok ? json(res) : fail(422, res.message);
      }

      const id = intId(b);
      if (id === null) return fail(404, 'Not found.');
      if (c === 'restore') {
        if (method !== 'POST') return fail(405, 'Use POST.');
        const n = await restoreItems(r, [id], actor);
        return n ? json({ ok: true, message: `${r.label} restored.` }) : fail(404, `That ${r.label.toLowerCase()} is not in Trash.`);
      }
      if (c) return fail(404, 'Not found.');
      if (method === 'GET') {
        const row = await getItem(r, id);
        return row ? json({ item: row }) : fail(404, `That ${r.label.toLowerCase()} could not be found.`);
      }
      if (method === 'PATCH' || method === 'PUT' || method === 'POST') {
        const body = await readBody(request, fileFields);
        if (typeof body === 'string') return fail(400, body);
        const res = await saveItem(r, id, body.fields, body.files, actor);
        return res.ok ? json({ ok: true, id: res.id, message: res.message, item: res.row }) : fail(res.status, res.errors);
      }
      if (method === 'DELETE') {
        if (qs.get('purge') === '1') {
          const n = await purgeItems(r, [id], actor);
          return n ? json({ ok: true, message: `${r.label} deleted permanently.` }) : fail(404, `That ${r.label.toLowerCase()} is not in Trash (move it to Trash first).`);
        }
        const n = await trashItems(r, [id], actor);
        return n
          ? json({ ok: true, message: r.softDelete ? `${r.label} moved to Trash. You can restore it from Trash.` : `${r.label} deleted.` })
          : fail(404, `That ${r.label.toLowerCase()} could not be found.`);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- content blocks ---------------- */
    if (section === 'content-blocks') {
      if (method === 'GET') {
        const data = await contentBlocks(a ?? qs.get('page') ?? undefined);
        return a && !Object.keys(data).length ? fail(404, 'That page could not be found.') : json(data);
      }
      if ((method === 'PATCH' || method === 'PUT' || method === 'POST') && a) {
        const pageBlocks = (await contentBlocks(a))[a] as { blocks: Record<string, { type: string }> } | undefined;
        if (!pageBlocks) return fail(404, 'That page could not be found.');
        const imageKeys = Object.entries(pageBlocks.blocks).filter(([, v]) => v.type === 'image').map(([k]) => k);
        const body = await readBody(request, [...imageKeys, 'header_image']);
        if (typeof body === 'string') return fail(400, body);
        const { reset, ...values } = body.fields;
        const res = await saveContentBlocks(a, values, body.files, Array.isArray(reset) ? reset.map(String) : reset ? [String(reset)] : [], actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- settings ---------------- */
    if (section === 'settings') {
      if (method === 'GET') return json({ settings: await getSettings() });
      if (method === 'PATCH' || method === 'PUT' || method === 'POST') {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const res = await saveSettings(body.fields, actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- pages on/off ---------------- */
    if (section === 'pages') {
      if (method === 'GET') return json(await pagesState());
      if ((method === 'PATCH' || method === 'PUT' || method === 'POST') && a) {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const v = body.fields.enabled ?? body.fields.is_enabled;
        if (v === undefined) return fail(422, 'Send {"enabled": true} or {"enabled": false}.');
        const res = await setPageEnabled(a, v === true || v === 1 || v === '1' || v === 'true', actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- submissions ---------------- */
    if (section === 'submissions') {
      if (!a && method === 'GET')
        return json(
          await listSubmissions({
            form_type: qs.get('form_type') ?? undefined,
            status: qs.get('status') ?? undefined,
            q: qs.get('q') ?? undefined,
            trashed: qs.get('trashed') === '1',
            limit: Number(qs.get('limit') ?? 100),
            offset: Number(qs.get('offset') ?? 0),
          }),
        );
      if (a === 'export.csv' && method === 'GET') {
        return new Response(await submissionsCsv(qs.get('form_type') ?? undefined), {
          headers: { 'Content-Type': 'text/csv; charset=UTF-8', 'Content-Disposition': 'attachment; filename="submissions.csv"', 'Cache-Control': 'no-store' },
        });
      }
      const id = intId(a);
      if (id === null) return fail(404, 'Not found.');
      if (b === 'restore' && method === 'POST') {
        return (await trashSubmissions([id], actor, true)) ? json({ ok: true, message: 'Submission restored.' }) : fail(404, 'That submission is not in Trash.');
      }
      if (method === 'GET') {
        const row = await dbFetchOne('SELECT * FROM form_submissions WHERE id = :id', { id });
        return row ? json({ item: row }) : fail(404, 'That submission could not be found.');
      }
      if (method === 'PATCH' || method === 'PUT' || method === 'POST') {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const res = await updateSubmission(id, body.fields as { status?: string; admin_note?: string }, actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      if (method === 'DELETE') {
        return (await trashSubmissions([id], actor)) ? json({ ok: true, message: 'Submission moved to Trash.' }) : fail(404, 'That submission could not be found.');
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- trash / activity ---------------- */
    if (section === 'trash' && method === 'GET') return json({ items: await trashedItems() });
    if (section === 'activity' && method === 'GET') return json({ items: await recentActivity(Number(qs.get('limit') ?? 100), Number(qs.get('offset') ?? 0)) });

    /* ---------------- users ---------------- */
    if (section === 'users') {
      if (!a && method === 'GET') return json({ items: await listUsers() });
      if (!a && method === 'POST') {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const res = await saveUser(null, body.fields, actor);
        return res.ok ? json(res, 201) : fail(res.status, res.errors);
      }
      const id = intId(a);
      if (id === null) return fail(404, 'Not found.');
      if (method === 'GET') {
        const row = await dbFetchOne('SELECT id, name, email, role, status, last_login_at, created_at FROM admin_users WHERE id = :id', { id });
        return row ? json({ item: row }) : fail(404, 'That user could not be found.');
      }
      if (method === 'PATCH' || method === 'PUT' || method === 'POST') {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const res = await saveUser(id, body.fields, actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      if (method === 'DELETE') {
        const res = await deleteUser(id, actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- newsletter ---------------- */
    if (section === 'newsletter') {
      if (!a && method === 'GET') return json(await newsletterStatus());
      if (a === 'lists' && method === 'GET') {
        const r = await ccFetchLists();
        return r.ok ? json({ lists: r.lists }) : fail(502, r.error ?? 'Could not load lists.');
      }
      if (!a && (method === 'PATCH' || method === 'PUT' || method === 'POST')) {
        const body = await readBody(request, []);
        if (typeof body === 'string') return fail(400, body);
        const res = await setNewsletterList(String(body.fields.list_id ?? ''), actor);
        return res.ok ? json(res) : fail(res.status, res.errors);
      }
      return fail(405, 'Method not allowed.');
    }

    /* ---------------- raw uploads ---------------- */
    if (section === 'uploads' && method === 'POST') {
      const body = await readBody(request, ['file']);
      if (typeof body === 'string') return fail(400, body);
      const folder = String(body.fields.folder ?? 'pages');
      const kind = body.fields.kind === 'pdf' ? 'pdf' : 'image';
      if (!body.files.file) return fail(422, 'Send a file in "file".');
      const up = await handleUpload(body.files.file, folder, kind, null);
      return up.ok ? json({ ok: true, path: up.path, url: '/admin/uploads/' + up.path }, 201) : fail(422, up.error);
    }

    return fail(404, 'Not found. See GET /api/admin/v1 for the list of endpoints.');
  } catch (e) {
    if (e instanceof DbUnavailableError) return fail(503, 'The database is not reachable.');
    console.error('admin api error', e);
    return fail(500, 'Something went wrong: ' + (e as Error).message);
  }
}

export const GET = handle;
export const POST = handle;
export const PATCH = handle;
export const PUT = handle;
export const DELETE = handle;
