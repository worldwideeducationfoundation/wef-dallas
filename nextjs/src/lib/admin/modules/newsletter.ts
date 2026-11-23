/** Port of admin/modules/newsletter/*.php (Constant Contact connection). The callback URL is unchanged. */
import 'server-only';
import crypto from 'node:crypto';
import { e } from '../../html';
import { dbExecute } from '../../db';
import { mysqlDateTime } from '../../time';
import { ccAuthorizeUrl, ccClearTokens, ccExchangeCode, ccFetchLists, ccIsConfigured, ccRedirectUri, ccState } from '../../constant-contact';
import { adminLog, logActivity } from '../activity';
import { Redirect, csrfField, htmlResponse, requireCapability, requirePostWithCsrf, type PublicCtx } from '../context';
import { setNewsletterList } from '../site-admin';
import { flashSet } from '../session';
import { adminPage } from '../ui';
import { number_format, phpDate, type Routes } from './util';


async function index(pctx: PublicCtx) {
  const ctx = await requireCapability(pctx, 'newsletter.manage', '../../dashboard.php');
  const configured = ccIsConfigured();
  const state = await ccState();
  const connected = Boolean(state.refresh_token);
  let lists: { id: string; name: string; count: number }[] = [];
  let listError: string | null = null;
  if (connected) {
    const f = await ccFetchLists();
    lists = f.lists;
    listError = f.error;
  }
  const selectedListId = String(state.list_id ?? '');
  const selectedMissing = connected && selectedListId !== '' && listError === null && !lists.some((l) => l.id === selectedListId);
  const when = (v: unknown) => e(phpDate('j M Y \\a\\t g:i a', v));
  const body = `
<div class="module-header">
  <h1>Newsletter</h1>
</div>
${
  !configured
    ? `<div class="panel">
    <div class="panel-header"><h2>Constant Contact is not set up yet</h2></div>
    <div class="panel-body">
      <p>
        The footer newsletter form is working — every address typed into it is saved and
        appears under <a href="${e(ctx.linkBase)}modules/submissions/index.php">Form Submissions</a>.
        They are just not being added to a Constant Contact mailing list automatically yet.
      </p>
      <p>To switch that on, someone with access to the server needs to add three values to the
         app's environment variables:</p>
      <ul class="field-hint-list">
        <li><code>CC_CLIENT_ID</code> — the API key from your Constant Contact developer app</li>
        <li><code>CC_CLIENT_SECRET</code> — the app secret generated alongside it</li>
        <li><code>CC_REDIRECT_URI</code> — <code>${e(ccRedirectUri() || "the address of this module's callback.php")}</code></li>
      </ul>
      <p class="field-hint">Step-by-step instructions are in <code>.env.example</code>.</p>
    </div>
  </div>`
    : `<div class="panel">
    <div class="panel-header">
      <h2>Connection</h2>
      <span class="status-badge status-badge--${connected ? 'replied' : 'new'}">${connected ? 'Connected' : 'Not connected'}</span>
    </div>
    <div class="panel-body">
      ${
        connected
          ? `<dl class="submission-meta">
        <div><dt>Connected</dt><dd>${state.connected_at !== null ? when(state.connected_at) : '&mdash;'}</dd></div>
        <div><dt>Signups are added to</dt><dd>${selectedListId === '' ? '<strong>No list chosen yet</strong>' : e(String(state.list_name ?? selectedListId))}</dd></div>
        <div><dt>Access token renews</dt><dd class="data-table-secondary">${state.expires_at !== null ? 'automatically, before ' + when(state.expires_at) : 'on the next signup'}</dd></div>
      </dl>`
          : `<p>
        Connect the site to your Constant Contact account. You will be sent to Constant
        Contact to sign in and approve access, then brought straight back here.
      </p>
      <p class="field-hint">
        Signups are being saved either way — connecting means they also reach your mailing
        list without anyone copying them across.
      </p>`
      }
      ${
        state.last_error
          ? `<div class="alert alert-error" role="alert"><strong>Last problem:</strong> ${e(String(state.last_error))}${
              state.last_error_at !== null ? ` <span class="data-table-secondary">(${when(state.last_error_at)})</span>` : ''
            }</div>`
          : ''
      }
      <div class="form-actions">
        <form method="post" action="connect.php">
          ${csrfField(ctx)}
          <button type="submit" class="btn-submit">${connected ? 'Reconnect' : 'Connect to Constant Contact'}</button>
        </form>
      </div>
    </div>
  </div>
  ${
    connected
      ? `<div class="panel">
    <div class="panel-header"><h2>Mailing list</h2></div>
    <div class="panel-body">
      ${
        listError !== null
          ? `<div class="alert alert-error" role="alert">Could not read your lists from Constant Contact: ${e(listError)}</div>`
          : !lists.length
            ? `<p>This Constant Contact account has no mailing lists yet. Create one in Constant Contact, then reload this page.</p>`
            : `${
                selectedMissing
                  ? `<div class="alert alert-error" role="alert">
          The list this site was sending signups to no longer exists on your Constant
          Contact account. Choose another one below — until you do, new signups are
          saved here but not added to any list.
        </div>`
                  : ''
              }
      <form method="post" action="save.php" class="admin-form">
        ${csrfField(ctx)}
        <div class="field">
          <label for="cc-list">Add newsletter signups to</label>
          <select id="cc-list" name="list_id" required>
            <option value="">Choose a list…</option>
            ${lists.map((l) => `<option value="${e(l.id)}"${l.id === selectedListId ? ' selected' : ''}>${e(l.name)} (${number_format(l.count)} contacts)</option>`).join('')}
          </select>
          <p class="field-hint">
            Addresses are added with sign-up-form permission, which is what actually
            happened — somebody typed their address into the form in the site footer.
          </p>
        </div>
        <div class="form-actions"><button type="submit" class="btn-submit">Save list</button></div>
      </form>`
      }
    </div>
  </div>
  <form method="post" action="disconnect.php" class="submission-danger" data-confirm="Disconnect Constant Contact? New signups will still be saved here, but will stop being added to your mailing list.">
    ${csrfField(ctx)}
    <button type="submit" class="link-delete">Disconnect Constant Contact</button>
  </form>`
      : ''
  }`
}`;
  return adminPage(ctx, { title: 'Newsletter', activeNav: 'Newsletter', body });
}

async function connect(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'newsletter.manage', '../../dashboard.php');
  requirePostWithCsrf(ctx);
  if (!ccIsConfigured()) {
    flashSet(ctx.session, 'error', 'Constant Contact is not set up in the environment variables yet.');
    throw new Redirect('index.php');
  }
  const state = crypto.randomBytes(32).toString('hex');
  ctx.session.set('cc_oauth_state', state);
  ctx.session.set('cc_oauth_started_at', Math.floor(Date.now() / 1000));
  await logActivity(ctx.user.id, 'update', 'settings', 'Started Constant Contact authorisation', ctx.meta);
  throw new Redirect(ccAuthorizeUrl(state));
}

/** The page Constant Contact sends the browser back to; it re-posts into exchange.php with the session cookie. */
async function callback(ctx: PublicCtx): Promise<Response> {
  const q = (k: string) => ctx.query.get(k) ?? '';
  const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Finishing the Constant Contact connection…</title>
<style>
  body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fbfaf8;
         color: #3a3f47; display: flex; align-items: center; justify-content: center;
         min-height: 100vh; margin: 0; padding: 24px; }
  .box { background: #fff; border: 1px solid #e7e9ec; border-radius: 16px; padding: 32px;
         max-width: 460px; box-shadow: 0 8px 24px rgba(18,20,23,.06); text-align: center; }
  h1 { font-size: 18px; margin: 0 0 12px; color: #121417; }
  p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
  button { font: inherit; font-weight: 600; background: #0e611d; color: #fff; border: 0;
           border-radius: 8px; padding: 10px 18px; cursor: pointer; }
</style>
</head>
<body>
  <div class="box">
    <h1>Finishing the Constant Contact connection…</h1>
    <p>One moment — returning you to the admin panel.</p>
    <form id="cc-exchange" method="post" action="exchange.php">
      <input type="hidden" name="code" value="${e(q('code'))}">
      <input type="hidden" name="state" value="${e(q('state'))}">
      <input type="hidden" name="error" value="${e(q('error'))}">
      <input type="hidden" name="error_description" value="${e(q('error_description'))}">
      <button type="submit">Continue</button>
    </form>
  </div>
  <script nonce="${e(ctx.nonce)}">document.getElementById('cc-exchange').submit();</script>
</body>
</html>`;
  const res = htmlResponse(html);
  res.headers.set('Referrer-Policy', 'no-referrer');
  return res;
}

async function exchange(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'newsletter.manage', '../../dashboard.php');
  if (ctx.method !== 'POST') throw new Redirect('index.php');
  const expected = String(ctx.session.get('cc_oauth_state') ?? '');
  const startedAt = Number(ctx.session.get('cc_oauth_started_at') ?? 0);
  ctx.session.unset('cc_oauth_state');
  ctx.session.unset('cc_oauth_started_at');
  const submitted = ctx.post.state ?? '';
  const code = (ctx.post.code ?? '').trim();
  const error = (ctx.post.error ?? '').trim();
  const errorDescription = (ctx.post.error_description ?? '').trim();
  const fail = (m: string): never => {
    flashSet(ctx.session, 'error', m);
    throw new Redirect('index.php');
  };
  if (expected === '' || expected.length !== submitted.length || !crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(submitted))) {
    adminLog('WARN', 'constant contact: callback state mismatch — authorisation rejected');
    fail('That connection attempt could not be verified. Please click Connect and try again.');
  }
  if (startedAt === 0 || Date.now() / 1000 - startedAt > 900) fail('That connection attempt took too long and has expired. Please click Connect and try again.');
  if (error !== '') {
    const detail = error === 'access_denied' ? 'The request to access your Constant Contact account was declined.' : errorDescription !== '' ? errorDescription : error;
    adminLog('WARN', 'constant contact: authorisation refused — ' + error);
    fail('Constant Contact did not complete the connection. ' + detail);
  }
  if (code === '') fail('Constant Contact did not send an authorisation code back. Please try connecting again.');
  const result = await ccExchangeCode(code);
  if (!result.ok) fail('Could not complete the connection: ' + result.error);
  await dbExecute('UPDATE constant_contact SET connected_at = :at, connected_by = :user WHERE id = 1', { at: mysqlDateTime(), user: ctx.user.id });
  await logActivity(ctx.user.id, 'update', 'settings', 'Connected Constant Contact', ctx.meta);
  const listChosen = Boolean((await ccState()).list_id);
  flashSet(ctx.session, 'success', listChosen ? 'Connected to Constant Contact.' : 'Connected to Constant Contact. Now choose which mailing list newsletter signups should join.');
  throw new Redirect('index.php');
}

async function disconnect(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'newsletter.manage', '../../dashboard.php');
  requirePostWithCsrf(ctx);
  await ccClearTokens(`Disconnected from the admin panel by ${ctx.user.name}.`);
  await logActivity(ctx.user.id, 'update', 'settings', 'Disconnected Constant Contact', ctx.meta);
  flashSet(
    ctx.session,
    'success',
    'Constant Contact disconnected. Newsletter signups are still saved under Form Submissions. This site has forgotten its access, but has not revoked it at Constant Contact — do that from your Constant Contact account if you disconnected because of a leak.',
  );
  throw new Redirect('index.php');
}

async function save(pctx: PublicCtx): Promise<never> {
  const ctx = await requireCapability(pctx, 'newsletter.manage', '../../dashboard.php');
  requirePostWithCsrf(ctx);
  const res = await setNewsletterList(ctx.post.list_id ?? '', { userId: ctx.user.id, meta: ctx.meta, via: 'admin' });
  flashSet(ctx.session, res.ok ? 'success' : 'error', res.ok ? res.message : res.errors.join(' '));
  throw new Redirect('index.php');
}

export const newsletterRoutes: Routes = {
  'index.php': index,
  'connect.php': connect,
  'callback.php': callback,
  'exchange.php': exchange,
  'disconnect.php': disconnect,
  'save.php': save,
};
