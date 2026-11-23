/**
 * The admin areas that are not simple lists: page text (content blocks),
 * site settings, Pages On/Off + header photos, form submissions, users and the
 * newsletter list. Ports of modules/{pages,settings,page-visibility,
 * submissions,users,newsletter}/*.php.
 */
import 'server-only';
import bcrypt from 'bcryptjs';
import { blockLimit, blockOverrides, blockRegistry } from '../blocks';
import { PAGE_HEADER_IMAGE_PAGES } from '../content';
import { dbExecute, dbFetchAll, dbFetchColumn, dbFetchOne, dbIdPlaceholders, dbInsert, dbTransaction } from '../db';
import { isValidEmail, SUBMISSION_STATUSES, submissionFormLabel, submissionSubjectLabel } from '../forms';
import { mysqlDateTime } from '../time';
import { SWITCHABLE_PAGES } from '../visibility';
import { ccFetchLists, ccIsConfigured, ccState } from '../constant-contact';
import { logActivity } from './activity';
import type { Actor, Files } from './crud';
import { SETTINGS_FIELDS, validateSettings } from './resources';
import { handleUpload } from './uploads';

const api = (a: Actor) => (a.via === 'api' ? ' (API)' : '');
const truthy = (v: unknown) => v === true || v === 1 || v === '1' || v === 'true' || v === 'on';

/* ----------------------------- content blocks ---------------------------- */

export async function contentBlocks(page?: string) {
  const registry = blockRegistry();
  const out: Record<string, unknown> = {};
  for (const [key, cfg] of Object.entries(registry)) {
    if (page && key !== page) continue;
    const overrides = await blockOverrides(key);
    out[key] = {
      label: cfg.label,
      url: cfg.url,
      blocks: Object.fromEntries(
        Object.entries(cfg.blocks).map(([k, b]) => [
          k,
          { ...b, maxLength: blockLimit(b.type), value: overrides[k] ?? null, overridden: overrides[k] !== undefined && String(overrides[k]).trim() !== '' },
        ]),
      ),
    };
  }
  return out;
}

/**
 * Saves page text. values: {key: "text"}; a value of null, "" or the default
 * resets the block to its default. Image blocks take files[key] (or
 * reset: [key]). header_image goes to the pages table.
 */
export async function saveContentBlocks(page: string, values: Record<string, unknown>, files: Files, resets: string[], actor: Actor) {
  const cfg = blockRegistry()[page];
  if (!cfg) return { ok: false as const, status: 404, errors: ['That page could not be found.'] };
  const errors: string[] = [];
  const text: Record<string, string> = {};
  for (const [key, block] of Object.entries(cfg.blocks)) {
    if (block.type === 'image' || !(key in values)) continue;
    const v = String(values[key] ?? '').replace(/\r\n/g, '\n').trim();
    if ([...v].length > blockLimit(block.type)) errors.push(`“${block.label}” is too long (max ${blockLimit(block.type)} characters).`);
    text[key] = v;
  }
  const unknown = Object.keys(values).filter((k) => !(k in cfg.blocks));
  if (unknown.length) errors.push(`Unknown block(s) for ${page}: ${unknown.join(', ')}.`);
  if (errors.length) return { ok: false as const, status: 422, errors };

  let headerChanged = false;
  if (PAGE_HEADER_IMAGE_PAGES.includes(page) && (files.header_image || truthy(values.remove_header_image) || resets.includes('header_image'))) {
    const old = (await dbFetchColumn<string>('SELECT header_image FROM pages WHERE page_key = :page', { page })) || null;
    const up = await handleUpload(files.header_image ?? null, 'pages', 'image', old, truthy(values.remove_header_image) || resets.includes('header_image'));
    if (!up.ok) return { ok: false as const, status: 422, errors: ['Header image: ' + up.error] };
    if (up.path !== old) {
      await dbExecute(
        `INSERT INTO pages (page_key, header_image, updated_by) VALUES (:page, :image, :user)
         ON DUPLICATE KEY UPDATE header_image = VALUES(header_image), updated_by = VALUES(updated_by)`,
        { page, image: up.path, user: actor.userId },
      );
      headerChanged = true;
    }
  }

  const overrides = await blockOverrides(page);
  const images: Record<string, string | null> = {};
  for (const [key, block] of Object.entries(cfg.blocks)) {
    if (block.type !== 'image') continue;
    if (!files[key] && !resets.includes(key)) continue;
    const old = overrides[key] && String(overrides[key]).trim() !== '' ? String(overrides[key]) : null;
    const up = await handleUpload(files[key] ?? null, 'pages', 'image', old, resets.includes(key));
    if (!up.ok) return { ok: false as const, status: 422, errors: [`${block.label}: ${up.error}`] };
    if (up.path !== old) images[key] = up.path;
  }

  let saved = 0;
  let reset = 0;
  await dbTransaction(async () => {
    for (const [key, block] of Object.entries(cfg.blocks)) {
      if (block.type === 'image') {
        if (!(key in images)) continue;
        if (images[key] === null) {
          await dbExecute('DELETE FROM content_blocks WHERE page = :page AND block_key = :key', { page, key });
          reset++;
        } else {
          await upsertBlock(page, key, images[key]!, actor.userId);
          saved++;
        }
        continue;
      }
      if (!(key in text) && !resets.includes(key)) continue;
      const v = text[key] ?? '';
      if (resets.includes(key) || v === '' || v === block.default) {
        if ((await dbExecute('DELETE FROM content_blocks WHERE page = :page AND block_key = :key', { page, key })) > 0) reset++;
        continue;
      }
      await upsertBlock(page, key, v, actor.userId);
      saved++;
    }
  });
  await logActivity(actor.userId, 'update', 'pages', `${cfg.label} — ${saved} block(s) saved, ${reset} reset${headerChanged ? ', header image changed' : ''}${api(actor)}`, actor.meta);
  return {
    ok: true as const,
    message: `${cfg.label} updated: ${saved} block${saved === 1 ? '' : 's'} saved${reset > 0 ? `, ${reset} reset to default` : ''}${headerChanged ? ', header image updated' : ''}.`,
    saved,
    reset,
  };
}

async function upsertBlock(page: string, key: string, value: string, user: number | null) {
  await dbExecute(
    `INSERT INTO content_blocks (page, block_key, value, updated_by) VALUES (:page, :key, :value, :user)
     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)`,
    { page, key, value, user },
  );
}

/* -------------------------------- settings ------------------------------- */

export async function getSettings() {
  return (await dbFetchOne('SELECT * FROM site_settings WHERE id = 1')) ?? {};
}

export async function saveSettings(raw: Record<string, unknown>, actor: Actor) {
  const current = await getSettings();
  const input: Record<string, string | null> = {};
  for (const f of SETTINGS_FIELDS) {
    const v = f in raw ? String(raw[f] ?? '').trim() : String(current[f] ?? '').trim();
    input[f] = v === '' ? null : v;
  }
  const errors = validateSettings(input);
  if (errors.length) return { ok: false as const, status: 422, errors };
  if (!(await dbFetchOne('SELECT id FROM site_settings WHERE id = 1'))) await dbExecute('INSERT INTO site_settings (id) VALUES (1)');
  await dbExecute(`UPDATE site_settings SET ${SETTINGS_FIELDS.map((f) => `${f} = :${f}`).join(', ')} WHERE id = 1`, input);
  await logActivity(actor.userId, 'update', 'settings', 'Site settings updated' + api(actor), actor.meta);
  return { ok: true as const, message: 'Settings saved.', settings: await getSettings() };
}

/* ------------------------------ pages on/off ----------------------------- */

export async function pagesState() {
  const rows = await dbFetchAll('SELECT page_key, header_image, is_enabled FROM pages');
  const byKey = new Map(rows.map((r) => [r.page_key, r]));
  return {
    switchable: Object.entries(SWITCHABLE_PAGES).map(([slug, label]) => ({ slug, label, enabled: Number(byKey.get(slug)?.is_enabled ?? 1) === 1 })),
    headerImagePages: PAGE_HEADER_IMAGE_PAGES.map((page) => ({ page, header_image: byKey.get(page)?.header_image ?? null })),
  };
}

export async function setPageEnabled(slug: string, enabled: boolean, actor: Actor) {
  if (!(slug in SWITCHABLE_PAGES)) return { ok: false as const, status: 404, errors: ['That page cannot be switched on or off.'] };
  await dbExecute(
    `INSERT INTO pages (page_key, is_enabled, updated_by) VALUES (:page_key, :is_enabled, :updated_by)
     ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_by = VALUES(updated_by)`,
    { page_key: slug, is_enabled: enabled ? 1 : 0, updated_by: actor.userId },
  );
  const label = SWITCHABLE_PAGES[slug];
  await logActivity(actor.userId, 'update', 'pages', `${label} page turned ${enabled ? 'on' : 'off'}${api(actor)}`, actor.meta);
  return {
    ok: true as const,
    message: enabled
      ? `${label} is on again: it is back in the menu and its links work.`
      : `${label} is off: it is out of the menu, links to it are disabled, and its address shows "Page not found".`,
  };
}

/* ------------------------------ submissions ------------------------------ */

export async function listSubmissions(p: { form_type?: string; status?: string; q?: string; trashed?: boolean; limit?: number; offset?: number }) {
  const where = [p.trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
  const params: Record<string, unknown> = {};
  if (p.form_type) {
    where.push('form_type = :form_type');
    params.form_type = p.form_type;
  }
  if (p.status && p.status in SUBMISSION_STATUSES) {
    where.push('status = :status');
    params.status = p.status;
  }
  if (p.q?.trim()) {
    where.push('(name LIKE :q OR email LIKE :q OR message LIKE :q)');
    params.q = '%' + p.q.trim().replace(/[\\%_]/g, (m) => '\\' + m) + '%';
  }
  const w = 'WHERE ' + where.join(' AND ');
  const limit = Math.min(Math.max(p.limit ?? 100, 1), 1000);
  const offset = Math.max(p.offset ?? 0, 0);
  const total = Number(await dbFetchColumn(`SELECT COUNT(*) FROM form_submissions ${w}`, params, 0));
  const items = await dbFetchAll(
    `SELECT id, form_type, name, email, subject, message, status, admin_note, handled_by, handled_at, newsletter_synced_at,
            newsletter_sync_error, created_at, updated_at, deleted_at
     FROM form_submissions ${w} ORDER BY created_at DESC, id DESC LIMIT ${limit} OFFSET ${offset}`,
    params,
  );
  return {
    total,
    limit,
    offset,
    items: items.map((r) => ({ ...r, form_label: submissionFormLabel(r.form_type), subject_label: submissionSubjectLabel(r.form_type, r.subject) })),
  };
}

export async function updateSubmission(id: number, raw: { status?: string; admin_note?: string | null }, actor: Actor) {
  const sub = await dbFetchOne('SELECT name, status, admin_note FROM form_submissions WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id });
  if (!sub) return { ok: false as const, status: 404, errors: ['That submission could not be found.'] };
  const status = raw.status ?? sub.status;
  if (!(status in SUBMISSION_STATUSES)) return { ok: false as const, status: 422, errors: ['Please choose one of the listed statuses.'] };
  let note = raw.admin_note === undefined ? sub.admin_note : String(raw.admin_note ?? '').trim();
  note = note === '' || note === null ? null : [...String(note)].slice(0, 1000).join('');
  await dbTransaction(async () => {
    await dbExecute(
      `UPDATE form_submissions SET status = :status, handled_by = :by, handled_at = :at WHERE id = :id AND deleted_at IS NULL`,
      { status, by: status === 'new' ? null : actor.userId, at: status === 'new' ? null : mysqlDateTime(), id },
    );
    await dbExecute('UPDATE form_submissions SET admin_note = :note WHERE id = :id AND deleted_at IS NULL', { note, id });
  });
  await logActivity(actor.userId, 'update', 'submissions', `${sub.name} → ${SUBMISSION_STATUSES[status]}${api(actor)}`, actor.meta);
  return { ok: true as const, message: 'Submission updated.' };
}

export async function trashSubmissions(ids: number[], actor: Actor, restore = false) {
  const { sql, ids: clean } = dbIdPlaceholders(ids);
  if (!clean.length) return 0;
  const count = restore
    ? await dbExecute(`UPDATE form_submissions SET deleted_at = NULL WHERE id IN (${sql}) AND deleted_at IS NOT NULL`, clean)
    : await dbExecute(`UPDATE form_submissions SET deleted_at = ? WHERE id IN (${sql}) AND deleted_at IS NULL`, [mysqlDateTime(), ...clean]);
  await logActivity(actor.userId, restore ? 'restore' : 'delete', 'submissions', `${restore ? 'Restored' : 'Moved to Trash'}: ${count} submission(s)${api(actor)}`, actor.meta);
  return count;
}

export async function submissionsCsv(formType?: string): Promise<string> {
  const rows = (await listSubmissions({ form_type: formType, limit: 1000 })).items;
  const cols = ['id', 'created_at', 'form_type', 'name', 'email', 'subject', 'message', 'status', 'admin_note'];
  const cell = (v: unknown) => {
    let s = String(v ?? '');
    if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; // spreadsheet formula injection guard
    return '"' + s.replace(/"/g, '""') + '"';
  };
  return [cols.join(','), ...rows.map((r) => cols.map((c) => cell((r as Record<string, unknown>)[c])).join(','))].join('\r\n') + '\r\n';
}

/* --------------------------------- users --------------------------------- */

export const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_DENY_LIST = [
  'password1234', 'passw0rd1234', 'administrator', 'iloveyou1234', 'qwertyuiop12', 'letmein12345', 'welcome12345', 'trustno1234',
  'monkey123456', 'football1234', 'baseball1234', 'dragon123456', 'sunshine1234', 'princess1234', 'starwars1234', 'whatever1234',
  '123456789012', '111111111111', '000000000000', 'abcdefghijkl', 'worldwideeducation', 'wefdallas1234', 'wefadmin1234',
];

export function passwordPolicyError(password: string, email = '', name = ''): string | null {
  if ([...password].length < PASSWORD_MIN_LENGTH) return `Password must be at least ${PASSWORD_MIN_LENGTH} characters.`;
  const n = password.trim().toLowerCase();
  if (/^(.)\1+$/u.test(n)) return 'Password cannot be the same character repeated.';
  if (n.includes('password') || n.includes('qwerty')) return 'Password must not contain "password" or "qwerty".';
  if (/(?:0123456789|abcdefghij|1234567890)/.test(n)) return 'Password must not be a sequence of consecutive characters.';
  if (PASSWORD_DENY_LIST.includes(n)) return 'That password is too common. Choose something less predictable.';
  const local = (email.split('@')[0] ?? '').trim().toLowerCase();
  if (local !== '' && [...local].length >= 4 && n.includes(local)) return 'Password must not contain your email address.';
  for (const part of name.trim().toLowerCase().split(/\s+/)) if ([...part].length >= 4 && n.includes(part)) return 'Password must not contain your name.';
  return null;
}

/** PHP password_hash() writes $2y$; bcryptjs reads $2y$ and writes $2b$, which PHP also accepts. */
export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 10);
}
export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  try {
    return await bcrypt.compare(password, hash.replace(/^\$2y\$/, '$2b$'));
  } catch {
    return false;
  }
}

export async function listUsers() {
  return dbFetchAll('SELECT id, name, email, role, status, last_login_at, created_at FROM admin_users ORDER BY name');
}

export async function saveUser(id: number | null, raw: Record<string, unknown>, actor: Actor & { isSelf?: boolean }) {
  const existing = id !== null ? await dbFetchOne('SELECT * FROM admin_users WHERE id = :id', { id }) : null;
  if (id !== null && !existing) return { ok: false as const, status: 404, errors: ['That user could not be found.'] };
  const pick = (k: string, d: string) => (k in raw ? String(raw[k] ?? '') : existing ? String(existing[k] ?? '') : d);
  const input = {
    name: pick('name', '').trim(),
    email: pick('email', '').toLowerCase().trim(),
    role: pick('role', 'editor'),
    status: pick('status', 'active'),
  };
  const password = String(raw.password ?? '');
  const errors: string[] = [];
  if (input.name === '' || [...input.name].length > 120) errors.push('Name is required (max 120 characters).');
  if (!isValidEmail(input.email) || [...input.email].length > 190) errors.push('A valid email address is required.');
  if (!['super_admin', 'administrator', 'editor'].includes(input.role)) errors.push('Please choose a valid role.');
  if (!['active', 'disabled'].includes(input.status)) errors.push('Please choose a valid status.');
  if (id === null && password === '') errors.push('Password is required.');
  else if (password !== '') {
    const p = passwordPolicyError(password, input.email, input.name);
    if (p) errors.push(p);
  }
  if (!errors.length && (await dbFetchOne('SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1', { email: input.email, id: id ?? 0 })))
    errors.push('That email is already in use by another account.');
  if (!errors.length && existing && existing.role === 'super_admin' && existing.status === 'active' && (input.role !== 'super_admin' || input.status !== 'active')) {
    const supers = Number(await dbFetchColumn("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'", [], 0));
    if (supers <= 1) errors.push('This is the last active Super Admin — its role or status cannot be changed away from that.');
  }
  if (errors.length) return { ok: false as const, status: 422, errors };
  let newId = id;
  if (id !== null) {
    if (password !== '')
      await dbExecute('UPDATE admin_users SET name = :name, email = :email, role = :role, status = :status, password_hash = :hash WHERE id = :id', {
        ...input,
        hash: await hashPassword(password),
        id,
      });
    else await dbExecute('UPDATE admin_users SET name = :name, email = :email, role = :role, status = :status WHERE id = :id', { ...input, id });
  } else {
    newId = await dbInsert('INSERT INTO admin_users (name, email, role, status, password_hash) VALUES (:name, :email, :role, :status, :hash)', {
      ...input,
      hash: await hashPassword(password),
    });
  }
  await logActivity(actor.userId, id !== null ? 'update' : 'create', 'users', input.email + api(actor), actor.meta);
  return { ok: true as const, id: newId!, message: id !== null ? 'User updated.' : 'User created.' };
}

export async function deleteUser(id: number, actor: Actor) {
  const user = await dbFetchOne('SELECT id, email, role, status FROM admin_users WHERE id = :id', { id });
  if (!user) return { ok: false as const, status: 404, errors: ['That user could not be found.'] };
  if (actor.userId === id) return { ok: false as const, status: 422, errors: ['You cannot delete your own account.'] };
  if (user.role === 'super_admin' && user.status === 'active') {
    const supers = Number(await dbFetchColumn("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'", [], 0));
    if (supers <= 1) return { ok: false as const, status: 422, errors: ['You cannot delete the last active Super Admin.'] };
  }
  await dbExecute('DELETE FROM admin_users WHERE id = :id', { id });
  await logActivity(actor.userId, 'delete', 'users', user.email + api(actor), actor.meta);
  return { ok: true as const, message: 'User deleted.' };
}

/* ------------------------------- newsletter ------------------------------ */

export async function newsletterStatus() {
  const s = await ccState();
  return {
    configured: ccIsConfigured(),
    connected: Boolean(s.refresh_token),
    list_id: s.list_id ?? null,
    list_name: s.list_name ?? null,
    connected_at: s.connected_at ?? null,
    last_error: s.last_error ?? null,
    last_error_at: s.last_error_at ?? null,
  };
}

export async function setNewsletterList(listId: string, actor: Actor) {
  if (listId.trim() === '') return { ok: false as const, status: 422, errors: ['Please choose a mailing list.'] };
  const fetched = await ccFetchLists();
  if (!fetched.ok) return { ok: false as const, status: 502, errors: ['Could not confirm that list with Constant Contact: ' + fetched.error] };
  const match = fetched.lists.find((l) => l.id === listId.trim());
  if (!match) return { ok: false as const, status: 422, errors: ['That list no longer exists on your Constant Contact account. Please choose another.'] };
  await dbExecute('UPDATE constant_contact SET list_id = :id, list_name = :name WHERE id = 1', { id: match.id, name: [...match.name].slice(0, 190).join('') });
  await logActivity(actor.userId, 'update', 'settings', `Newsletter list set to "${match.name}"${api(actor)}`, actor.meta);
  return { ok: true as const, message: `Newsletter signups will be added to “${match.name}”.` };
}

/* -------------------------------- activity ------------------------------- */

export async function recentActivity(limit = 100, offset = 0) {
  return dbFetchAll(
    `SELECT a.id, a.action, a.module, a.details, a.ip_address, a.created_at, a.user_id, u.name AS user_name, u.email AS user_email
     FROM activity_logs a LEFT JOIN admin_users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC LIMIT ${Math.min(Math.max(limit, 1), 500)} OFFSET ${Math.max(offset, 0)}`,
  );
}
