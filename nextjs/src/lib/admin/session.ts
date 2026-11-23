/**
 * Admin sessions, login, roles and CSRF (port of admin/includes/{auth,csrf,bootstrap}.php).
 *
 * Sessions are stored server-side like PHP's: an opaque random id in an
 * HttpOnly, SameSite=Strict cookie, and the data in storage/sessions/<id>.json.
 * Same limits as the PHP admin: 1 hour idle, 12 hours absolute, the account
 * re-checked against the database every 5 minutes.
 */
import 'server-only';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { cookies, headers } from 'next/headers';
import { dbExecute, dbFetchColumn, dbFetchOne } from '../db';
import { STORAGE_DIR } from '../paths';
import { adminLog, logActivity, type RequestMeta } from './activity';
import { hashPassword, verifyPassword } from './site-admin';

export const MAX_LOGIN_ATTEMPTS = 5;
export const LOGIN_LOCKOUT_MINUTES = 15;
export const MAX_LOGIN_ATTEMPTS_PER_IP = 20;
export const IP_SPRAY_EMAIL_THRESHOLD = 5;
export const SESSION_IDLE_SECONDS = 3600;
export const SESSION_ABSOLUTE_SECONDS = 43200;
export const SESSION_REVALIDATE_SECONDS = 300;

export const ROLE_CAPABILITIES: Record<string, string[]> = {
  super_admin: ['*'],
  administrator: [
    'content.view', 'content.create', 'content.edit', 'content.delete', 'media.view', 'media.upload', 'media.delete',
    'settings.manage', 'activity.view', 'submissions.view', 'submissions.manage', 'newsletter.manage',
  ],
  editor: ['content.view', 'content.create', 'content.edit', 'media.view', 'media.upload'],
};
export const ALL_CAPABILITIES = [
  'content.view', 'content.create', 'content.edit', 'content.delete', 'media.view', 'media.upload', 'media.delete',
  'settings.manage', 'activity.view', 'users.manage', 'newsletter.manage', 'submissions.view', 'submissions.manage',
];
export const ROLE_LABELS: Record<string, string> = { super_admin: 'Super Admin', administrator: 'Administrator', editor: 'Editor' };

const COOKIE = 'wef_admin_session';
const SESSION_DIR = path.join(STORAGE_DIR, 'sessions');

export type SessionData = {
  user_id?: number;
  user_name?: string;
  user_email?: string;
  user_role?: string;
  auth_started_at?: number;
  last_activity?: number;
  revalidated_at?: number;
  csrf_token?: string;
  flash?: { type: string; message: string };
  flash_input?: Record<string, unknown>;
  [k: string]: unknown;
};

export type User = { id: number; name: string; email: string; role: string };

const now = () => Math.floor(Date.now() / 1000);

function fileFor(id: string): string | null {
  return /^[a-f0-9]{64}$/.test(id) ? path.join(SESSION_DIR, id + '.json') : null;
}

/** One request's session. Call save() (done by AdminRequest.finish) to persist changes. */
export class Session {
  id: string;
  data: SessionData;
  private dirty = false;
  private destroyed = false;
  isNew: boolean;

  private constructor(id: string, data: SessionData, isNew: boolean) {
    this.id = id;
    this.data = data;
    this.isNew = isNew;
  }

  static async load(): Promise<Session> {
    const jar = await cookies();
    const id = jar.get(COOKIE)?.value ?? '';
    const file = fileFor(id);
    if (file) {
      try {
        const data = JSON.parse(fs.readFileSync(file, 'utf8')) as SessionData;
        return new Session(id, data, false);
      } catch {
        /* missing or unreadable: start fresh */
      }
    }
    const s = new Session(crypto.randomBytes(32).toString('hex'), {}, true);
    if (Math.random() < 0.02) gcSessions();
    return s;
  }

  get<K extends keyof SessionData>(k: K): SessionData[K] {
    return this.data[k];
  }
  set<K extends keyof SessionData>(k: K, v: SessionData[K]) {
    this.data[k] = v;
    this.dirty = true;
  }
  unset(k: keyof SessionData) {
    delete this.data[k];
    this.dirty = true;
  }
  touch() {
    this.dirty = true;
  }

  /** session_regenerate_id(true) */
  regenerate() {
    this.removeFile();
    this.id = crypto.randomBytes(32).toString('hex');
    this.isNew = true;
    this.dirty = true;
  }

  destroy() {
    this.removeFile();
    this.data = {};
    this.destroyed = true;
  }

  private removeFile() {
    const f = fileFor(this.id);
    if (f) fs.rmSync(f, { force: true });
  }

  async save(secure: boolean): Promise<void> {
    const jar = await cookies();
    if (this.destroyed) {
      jar.set(COOKIE, '', { path: '/', httpOnly: true, sameSite: 'strict', secure, maxAge: 0 });
      return;
    }
    if (!this.dirty && !this.isNew) return;
    if (this.isNew && Object.keys(this.data).length === 0) return;
    fs.mkdirSync(SESSION_DIR, { recursive: true });
    fs.writeFileSync(fileFor(this.id)!, JSON.stringify(this.data));
    if (this.isNew) jar.set(COOKIE, this.id, { path: '/', httpOnly: true, sameSite: 'strict', secure });
  }
}

/** Removes session files idle longer than the absolute limit. */
function gcSessions() {
  try {
    const cutoff = Date.now() - SESSION_ABSOLUTE_SECONDS * 1000;
    for (const f of fs.readdirSync(SESSION_DIR)) {
      const p = path.join(SESSION_DIR, f);
      if (fs.statSync(p).mtimeMs < cutoff) fs.rmSync(p, { force: true });
    }
  } catch {
    /* ignore */
  }
}

/* ------------------------------ request ------------------------------ */

export async function requestSecure(): Promise<boolean> {
  const h = await headers();
  return (h.get('x-forwarded-proto') ?? '').toLowerCase() === 'https' || process.env.APP_FORCE_HTTPS_COOKIE === '1';
}

export async function requestMetaFromHeaders(): Promise<RequestMeta> {
  const h = await headers();
  return {
    ip: (h.get('x-forwarded-for') ?? '').split(',')[0].trim() || h.get('x-real-ip') || '0.0.0.0',
    userAgent: h.get('user-agent') ?? '',
  };
}

/* ------------------------------- users ------------------------------- */

export function currentUser(s: Session): User | null {
  if (!s.data.user_id) return null;
  return { id: s.data.user_id, name: s.data.user_name ?? '', email: s.data.user_email ?? '', role: s.data.user_role ?? '' };
}

export function can(s: Session, capability: string): boolean {
  if (!ALL_CAPABILITIES.includes(capability)) {
    adminLog('WARN', `unknown capability checked: '${capability}' — denying`);
    return false;
  }
  const u = currentUser(s);
  if (!u) return false;
  const caps = ROLE_CAPABILITIES[u.role] ?? [];
  return caps.includes('*') || caps.includes(capability);
}

/** Idle/absolute timeouts and periodic re-check of the account. Returns why the session ended, if it did. */
export async function enforceSessionLifetime(s: Session, meta: RequestMeta): Promise<string | null> {
  if (!s.data.user_id) return null;
  const t = now();
  if (s.data.auth_started_at && t - s.data.auth_started_at > SESSION_ABSOLUTE_SECONDS) {
    await logoutUser(s, meta);
    return 'expired';
  }
  if (s.data.last_activity && t - s.data.last_activity > SESSION_IDLE_SECONDS) {
    await logoutUser(s, meta);
    return 'idle';
  }
  s.set('last_activity', t);
  if (t - (s.data.revalidated_at ?? 0) >= SESSION_REVALIDATE_SECONDS) {
    const fresh = await dbFetchOne('SELECT name, email, role, status FROM admin_users WHERE id = :id LIMIT 1', { id: s.data.user_id });
    if (!fresh) {
      s.destroy();
      return 'removed';
    }
    if (fresh.status !== 'active') {
      await logoutUser(s, meta);
      return 'disabled';
    }
    s.set('user_name', fresh.name);
    s.set('user_email', fresh.email);
    s.set('user_role', fresh.role);
    s.set('revalidated_at', t);
  }
  return null;
}

export async function logoutUser(s: Session, meta: RequestMeta) {
  const u = currentUser(s);
  if (u) await logActivity(u.id, 'logout', 'auth', null, meta);
  s.destroy();
}

/* ------------------------------- login ------------------------------- */

const DUMMY_HASH = '$2b$10$Vh6ptC4AAwq4tKOdyGovWewG7Dd2aEGurDhizI.fQEV3GLp6UYh06';

async function failedSince(where: string, params: Record<string, unknown>): Promise<number> {
  return Number(
    await dbFetchColumn(`SELECT COUNT(*) FROM login_attempts WHERE ${where} AND success = 0 AND created_at > (NOW() - INTERVAL :minutes MINUTE)`, {
      ...params,
      minutes: LOGIN_LOCKOUT_MINUTES,
    }, 0),
  );
}

async function distinctEmailFailures(ip: string): Promise<number> {
  return Number(
    await dbFetchColumn(
      'SELECT COUNT(DISTINCT email) FROM login_attempts WHERE ip_address = :ip AND success = 0 AND created_at > (NOW() - INTERVAL :minutes MINUTE)',
      { ip, minutes: LOGIN_LOCKOUT_MINUTES },
      0,
    ),
  );
}

export async function attemptLogin(s: Session, emailRaw: string, password: string, meta: RequestMeta): Promise<{ ok: boolean; error: string | null }> {
  const email = emailRaw.trim().toLowerCase();
  const generic = 'Invalid email or password.';
  if (email === '' || password === '') return { ok: false, error: generic };
  if ((await failedSince('email = :email', { email })) >= MAX_LOGIN_ATTEMPTS || (await failedSince('ip_address = :ip', { ip: meta.ip })) >= MAX_LOGIN_ATTEMPTS_PER_IP) {
    adminLog('WARN', `login throttled: email=${email} ip=${meta.ip} distinct_emails_tried=${await distinctEmailFailures(meta.ip)}`);
    return { ok: false, error: 'Too many failed attempts. Try again in a few minutes.' };
  }
  const user = await dbFetchOne('SELECT id, name, email, password_hash, role, status FROM admin_users WHERE email = :email LIMIT 1', { email });
  const passwordOk = await verifyPassword(password, user?.password_hash ?? DUMMY_HASH);
  const record = (success: boolean) =>
    dbExecute('INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, :success)', { email, ip: meta.ip, success: success ? 1 : 0 });
  if (!user || user.status !== 'active' || !passwordOk) {
    await record(false);
    const distinct = await distinctEmailFailures(meta.ip);
    if (distinct >= IP_SPRAY_EMAIL_THRESHOLD)
      adminLog('WARN', `possible password spraying: ip=${meta.ip} failed against ${distinct} distinct emails in ${LOGIN_LOCKOUT_MINUTES} min`);
    return { ok: false, error: generic };
  }
  await record(true);
  // PHP's default cost is 10; rehash anything weaker.
  const cost = Number(/^\$2[aby]\$(\d+)\$/.exec(String(user.password_hash))?.[1] ?? 0);
  if (cost < 10) {
    await dbExecute('UPDATE admin_users SET password_hash = :hash WHERE id = :id', { hash: await hashPassword(password), id: user.id });
    adminLog('INFO', 'password hash upgraded for user id ' + Number(user.id));
  }
  await dbExecute('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id', { id: user.id });
  s.regenerate();
  const t = now();
  s.set('user_id', Number(user.id));
  s.set('user_name', user.name);
  s.set('user_email', user.email);
  s.set('user_role', user.role);
  s.set('auth_started_at', t);
  s.set('last_activity', t);
  s.set('revalidated_at', t);
  await logActivity(Number(user.id), 'login', 'auth', null, meta);
  return { ok: true, error: null };
}

/* ------------------------------ csrf/flash ------------------------------ */

export function csrfToken(s: Session): string {
  if (!s.data.csrf_token) s.set('csrf_token', crypto.randomBytes(32).toString('hex'));
  return s.data.csrf_token!;
}

export function csrfVerify(s: Session, submitted: unknown): boolean {
  const t = s.data.csrf_token;
  if (!t || typeof submitted !== 'string' || submitted === '') return false;
  const a = Buffer.from(t);
  const b = Buffer.from(submitted);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export function flashSet(s: Session, type: string, message: string) {
  s.set('flash', { type, message });
}
export function flashGet(s: Session) {
  const f = s.data.flash;
  if (!f) return null;
  s.unset('flash');
  return f;
}
export function flashInputSet(s: Session, data: Record<string, unknown>) {
  s.set('flash_input', data);
}
export function flashInputGet(s: Session): Record<string, any> | null {
  const f = s.data.flash_input;
  if (!f) return null;
  s.unset('flash_input');
  return f as Record<string, any>;
}
