/**
 * Per-request context for the admin screens (the role played by
 * admin/includes/bootstrap.php). The admin is plain server-rendered HTML with
 * the same markup, CSS (public/admin/assets/css/admin.css) and JS
 * (public/admin/assets/js/admin.js) as the PHP admin, at the same URLs.
 */
import 'server-only';
import { requestMeta, type RequestMeta } from './activity';
import { can, csrfToken, csrfVerify, currentUser, enforceSessionLifetime, flashSet, Session, requestSecure, type User } from './session';
import type { UploadInput } from './uploads';

export class Redirect {
  constructor(public location: string) {}
}

export type Ctx = {
  request: Request;
  url: URL;
  method: string;
  session: Session;
  user: User;
  meta: RequestMeta;
  nonce: string;
  /** "../../" from a module page back to /admin/ (PHP $linkBase). */
  linkBase: string;
  post: Record<string, string>;
  postAll: Record<string, string[]>;
  files: Record<string, UploadInput>;
  /** Raw multi-file uploads like photos[] */
  fileLists: Record<string, UploadInput[]>;
  query: URLSearchParams;
};

export type PublicCtx = Omit<Ctx, 'user'> & { user: User | null };

export async function buildContext(request: Request, depth: number): Promise<PublicCtx> {
  const url = new URL(request.url);
  const session = await Session.load();
  const post: Record<string, string> = {};
  const postAll: Record<string, string[]> = {};
  const files: Record<string, UploadInput> = {};
  const fileLists: Record<string, UploadInput[]> = {};
  if (request.method === 'POST') {
    const type = request.headers.get('content-type') ?? '';
    if (type.includes('multipart/form-data') || type.includes('application/x-www-form-urlencoded')) {
      const form = await request.formData();
      for (const [k, v] of form.entries()) {
        if (typeof v === 'string') {
          post[k] = v;
          (postAll[k] ??= []).push(v);
        } else if (v.size > 0) {
          const f = { bytes: Buffer.from(await v.arrayBuffer()), name: v.name };
          files[k] = f;
          (fileLists[k] ??= []).push(f);
        }
      }
    }
  }
  return {
    request,
    url,
    method: request.method,
    session,
    user: currentUser(session),
    meta: requestMeta(request),
    nonce: request.headers.get('x-nonce') ?? '',
    linkBase: '../'.repeat(depth),
    post,
    postAll,
    files,
    fileLists,
    query: url.searchParams,
  };
}

/** requireLogin(): sends to the login page when not signed in or the session ended. */
export async function requireLogin(ctx: PublicCtx): Promise<Ctx> {
  const ended = await enforceSessionLifetime(ctx.session, ctx.meta);
  const user = currentUser(ctx.session);
  if (ended !== null || user === null) throw new Redirect(ctx.linkBase + 'login.php' + (ended !== null ? '?reason=' + ended : ''));
  ctx.user = user;
  return ctx as Ctx;
}

export async function requireCapability(ctx: PublicCtx, capability: string, redirectTo = 'index.php'): Promise<Ctx> {
  const c = await requireLogin(ctx);
  if (!can(c.session, capability)) {
    flashSet(c.session, 'error', 'You do not have permission to do that.');
    throw new Redirect(redirectTo);
  }
  return c;
}

export function requirePostWithCsrf(ctx: PublicCtx, redirectTo = 'index.php') {
  if (ctx.method !== 'POST') throw new Redirect(redirectTo);
  if (!csrfVerify(ctx.session, ctx.post.csrf_token)) {
    flashSet(ctx.session, 'error', 'Your session expired — please try again.');
    throw new Redirect(redirectTo);
  }
}

export function csrfField(ctx: PublicCtx): string {
  return `<input type="hidden" name="csrf_token" value="${csrfToken(ctx.session)}">`;
}

export function postId(ctx: PublicCtx, field = 'id'): number | null {
  const raw = ctx.post[field];
  if (raw === undefined || raw === '') return null;
  return /^[+-]?\d+$/.test(raw.trim()) && Number(raw) >= 1 ? Number(raw) : null;
}

export function queryId(ctx: PublicCtx, field = 'id'): number | null {
  const raw = ctx.query.get(field);
  if (raw === null || raw === '') return null;
  return /^[+-]?\d+$/.test(raw.trim()) && Number(raw) >= 1 ? Number(raw) : null;
}

export async function finish(ctx: PublicCtx, response: Response): Promise<Response> {
  await ctx.session.save(await requestSecure());
  return response;
}

export function htmlResponse(body: string, status = 200): Response {
  return new Response(body, {
    status,
    headers: { 'Content-Type': 'text/html; charset=UTF-8', 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex, nofollow' },
  });
}

export function redirectResponse(location: string): Response {
  return new Response(null, { status: 302, headers: { Location: location, 'Cache-Control': 'no-store' } });
}
