/**
 * Constant Contact newsletter sync (port of includes/constant-contact.php).
 * OAuth2 (authorisation code + refresh token) against the v3 API; tokens live
 * in the constant_contact table (single row, id = 1).
 * Settings: CC_CLIENT_ID, CC_CLIENT_SECRET, CC_REDIRECT_URI (environment).
 */
import 'server-only';
import { dbExecute, dbFetchOne, dbTransaction } from './db';
import { publicLog } from './log';
import { mysqlDateTime } from './time';

export const CC_AUTHORIZE_URL = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';
export const CC_TOKEN_URL = 'https://authz.constantcontact.com/oauth2/default/v1/token';
export const CC_API_BASE = 'https://api.cc.email/v3';
export const CC_SCOPES = 'contact_data offline_access';
const CC_TIMEOUT_MS = 8000;
const CC_REFRESH_MARGIN_SECONDS = 600;
const CC_MAX_ERROR_LENGTH = 500;
const CC_MAX_ROW_ERROR_LENGTH = 255;

type Row = Record<string, any>;
type HttpResult = { status: number; body: any; raw: string; error: string | null };

let logger: (level: string, message: string) => void = publicLog;
/** The admin swaps in its own log (adminLog) so messages land in the admin log, as in PHP. */
export function setCcLogger(fn: (level: string, message: string) => void) {
  logger = fn;
}

export const ccClientId = () => (process.env.CC_CLIENT_ID ?? '').trim();
export const ccClientSecret = () => (process.env.CC_CLIENT_SECRET ?? '').trim();
export const ccRedirectUri = () => (process.env.CC_REDIRECT_URI ?? '').trim();
export const ccIsConfigured = () => ccClientId() !== '' && ccClientSecret() !== '' && ccRedirectUri() !== '';

function ccLog(level: string, message: string) {
  logger(level, 'constant contact: ' + message);
}

const mbSlice = (s: string, n: number) => [...s].slice(0, n).join('');

export function ccRedact(text: string): string {
  return text
    .replace(/\b[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/g, '[redacted]')
    .replace(/("(?:access|refresh|id)_token"\s*:\s*")[^"]*/gi, '$1[redacted]');
}

export async function ccState(): Promise<Row> {
  let row = await dbFetchOne('SELECT * FROM constant_contact WHERE id = 1');
  if (row === null) {
    await dbExecute('INSERT INTO constant_contact (id) VALUES (1)');
    row = (await dbFetchOne('SELECT * FROM constant_contact WHERE id = 1')) ?? {};
  }
  return row;
}

export async function ccIsConnected(): Promise<boolean> {
  return Boolean((await ccState()).refresh_token);
}

export async function ccIsActive(): Promise<boolean> {
  if (!ccIsConfigured()) return false;
  const s = await ccState();
  return Boolean(s.refresh_token) && Boolean(s.list_id);
}

export async function ccRecordError(error: string | null): Promise<void> {
  await dbExecute('UPDATE constant_contact SET last_error = :error, last_error_at = :at WHERE id = 1', {
    error: error === null ? null : mbSlice(ccRedact(error), CC_MAX_ERROR_LENGTH),
    at: error === null ? null : mysqlDateTime(),
  });
}

async function ccStoreTokens(token: Row): Promise<void> {
  const expiresIn = Number(token.expires_in ?? 0);
  const params: Row = {
    access_token: String(token.access_token ?? ''),
    expires_at: mysqlDateTime(new Date(Date.now() + (expiresIn > 0 ? expiresIn : 3600) * 1000)),
    scope: token.scope !== undefined ? mbSlice(String(token.scope), 255) : null,
  };
  let refreshSql = '';
  if (token.refresh_token) {
    refreshSql = ', refresh_token = :refresh_token';
    params.refresh_token = String(token.refresh_token);
  }
  await dbExecute(
    `UPDATE constant_contact SET access_token = :access_token, expires_at = :expires_at, scope = :scope${refreshSql},
       last_error = NULL, last_error_at = NULL WHERE id = 1`,
    params,
  );
}

export async function ccClearTokens(reason: string): Promise<void> {
  await dbExecute(
    `UPDATE constant_contact SET access_token = NULL, refresh_token = NULL, expires_at = NULL,
       scope = NULL, connected_at = NULL, connected_by = NULL, last_error = :reason, last_error_at = :at WHERE id = 1`,
    { reason: mbSlice(ccRedact(reason), CC_MAX_ERROR_LENGTH), at: mysqlDateTime() },
  );
}

async function ccHttp(method: string, url: string, headers: Record<string, string> = {}, body: string | Row | null = null): Promise<HttpResult> {
  let payload: string | undefined;
  if (body !== null && typeof body === 'object') {
    payload = JSON.stringify(body);
    headers['Content-Type'] = 'application/json';
  } else if (typeof body === 'string') {
    payload = body;
    headers['Content-Type'] = 'application/x-www-form-urlencoded';
  }
  headers.Accept = 'application/json';
  headers['User-Agent'] = 'WEF-Website/1.0 (+https://wefdallas.org)';
  try {
    const res = await fetch(url, { method, headers, body: payload, redirect: 'manual', signal: AbortSignal.timeout(CC_TIMEOUT_MS) });
    const raw = await res.text();
    let decoded: any = {};
    try {
      decoded = JSON.parse(raw);
    } catch {
      decoded = {};
    }
    return { status: res.status, body: decoded && typeof decoded === 'object' ? decoded : {}, raw, error: null };
  } catch (e) {
    return { status: 0, body: {}, raw: '', error: (e as Error).message || 'The request to Constant Contact failed.' };
  }
}

function ccErrorMessage(r: HttpResult): string {
  if (r.error !== null) return ccRedact(r.error);
  const b = r.body;
  if (typeof b?.error_description === 'string') return ccRedact(b.error_description);
  const messages: string[] = [];
  for (const item of Array.isArray(b) ? b : Object.values(b ?? {})) {
    if (item && typeof item === 'object' && typeof (item as Row).error_message === 'string') messages.push((item as Row).error_message);
  }
  if (messages.length) return ccRedact(messages.join(' '));
  if (typeof b?.error === 'string') return ccRedact(b.error);
  return `Constant Contact returned HTTP ${r.status}.`;
}

export function ccAuthorizeUrl(state: string): string {
  const q = new URLSearchParams({ client_id: ccClientId(), redirect_uri: ccRedirectUri(), response_type: 'code', scope: CC_SCOPES, state });
  return CC_AUTHORIZE_URL + '?' + q.toString().replace(/\+/g, '%20');
}

const basicAuth = () => ({ Authorization: 'Basic ' + Buffer.from(ccClientId() + ':' + ccClientSecret()).toString('base64') });

export async function ccExchangeCode(code: string): Promise<{ ok: boolean; error: string | null }> {
  const r = await ccHttp('POST', CC_TOKEN_URL, basicAuth(), new URLSearchParams({ code, redirect_uri: ccRedirectUri(), grant_type: 'authorization_code' }).toString());
  if (r.status !== 200 || !r.body.access_token) {
    const message = ccErrorMessage(r);
    ccLog('ERROR', 'authorisation code exchange failed: ' + message);
    return { ok: false, error: message };
  }
  if (!r.body.refresh_token) {
    ccLog('ERROR', 'token response carried no refresh token — offline_access was not granted');
    return {
      ok: false,
      error:
        'Constant Contact did not grant offline access, so the connection could not be kept alive. Check that the app has the offline_access scope enabled, then try again.',
    };
  }
  await ccStoreTokens(r.body);
  ccLog('INFO', 'connected — access token stored');
  return { ok: true, error: null };
}

async function ccAccessToken(): Promise<{ token: string | null; error: string | null }> {
  return dbTransaction(async () => {
    const state = await dbFetchOne('SELECT * FROM constant_contact WHERE id = 1 FOR UPDATE');
    if (state === null || !state.refresh_token) return { token: null, error: 'Constant Contact is not connected.' };
    const expiresAt = state.expires_at ? new Date(String(state.expires_at).replace(' ', 'T')).getTime() / 1000 : 0;
    if (state.access_token && expiresAt > Date.now() / 1000 + CC_REFRESH_MARGIN_SECONDS) return { token: String(state.access_token), error: null };

    const r = await ccHttp('POST', CC_TOKEN_URL, basicAuth(), new URLSearchParams({ refresh_token: String(state.refresh_token), grant_type: 'refresh_token' }).toString());
    if (r.status === 200 && r.body.access_token) {
      await ccStoreTokens(r.body);
      ccLog('INFO', 'access token refreshed');
      return { token: String(r.body.access_token), error: null };
    }
    const message = ccErrorMessage(r);
    if (r.status === 400 && (r.body.error ?? '') === 'invalid_grant') {
      const why = 'The Constant Contact authorisation expired or was revoked. Reconnect to resume syncing.';
      await ccClearTokens(why);
      ccLog('ERROR', 'refresh token rejected (invalid_grant) — connection cleared');
      return { token: null, error: why };
    }
    await ccRecordError(message);
    ccLog('WARN', 'token refresh failed: ' + message);
    return { token: null, error: message };
  });
}

async function ccApi(method: string, path: string, body: Row | null = null) {
  const auth = await ccAccessToken();
  if (auth.token === null) return { ok: false, status: 0, body: {} as any, error: auth.error };
  const r = await ccHttp(method, CC_API_BASE + path, { Authorization: 'Bearer ' + auth.token }, body);
  const ok = r.status >= 200 && r.status < 300;
  return { ok, status: r.status, body: r.body, error: ok ? null : ccErrorMessage(r) };
}

export async function ccFetchLists(): Promise<{ ok: boolean; lists: { id: string; name: string; count: number }[]; error: string | null }> {
  const r = await ccApi('GET', '/contact_lists?include_count=true&limit=100');
  if (!r.ok) return { ok: false, lists: [], error: r.error };
  const lists = ((r.body.lists ?? []) as Row[])
    .filter((l) => l && l.list_id)
    .map((l) => ({ id: String(l.list_id), name: String(l.name ?? 'Untitled list'), count: Number(l.membership_count ?? 0) }));
  return { ok: true, lists, error: null };
}

export async function ccSubscribe(email: string): Promise<{ ok: boolean; error: string | null }> {
  const listId = String((await ccState()).list_id ?? '');
  if (listId === '') return { ok: false, error: 'No Constant Contact list has been selected.' };
  const r = await ccApi('POST', '/contacts/sign_up_form', { email_address: email, list_memberships: [listId] });
  if (r.ok) {
    await ccRecordError(null);
    return { ok: true, error: null };
  }
  await ccRecordError(r.error);
  return { ok: false, error: r.error };
}

export async function ccSyncSubmission(submissionId: number, email: string): Promise<{ ok: boolean; error: string | null }> {
  try {
    if (!(await ccIsActive())) return { ok: false, error: null };
    const result = await ccSubscribe(email);
    await dbExecute('UPDATE form_submissions SET newsletter_synced_at = :synced, newsletter_sync_error = :error WHERE id = :id', {
      synced: result.ok ? mysqlDateTime() : null,
      error: result.ok ? null : mbSlice(String(result.error), CC_MAX_ROW_ERROR_LENGTH),
      id: submissionId,
    });
    if (result.ok) ccLog('INFO', `submission #${submissionId} added to the mailing list`);
    else ccLog('WARN', `submission #${submissionId} not added: ${result.error}`);
    return result;
  } catch (e) {
    const message = ccRedact((e as Error).message);
    ccLog('ERROR', `submission #${submissionId} sync crashed: ${message}`);
    try {
      await dbExecute('UPDATE form_submissions SET newsletter_sync_error = :error WHERE id = :id', { error: mbSlice(message, CC_MAX_ROW_ERROR_LENGTH), id: submissionId });
    } catch {
      /* ignore */
    }
    return { ok: false, error: message };
  }
}
