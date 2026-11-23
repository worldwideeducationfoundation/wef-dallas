/**
 * The admin panel, at the same URLs as the PHP admin (/admin/login.php,
 * /admin/modules/team/index.php, ...). Pages are server-rendered HTML with the
 * PHP admin's markup, styles and scripts; see src/lib/admin/modules/*.
 */
import { buildContext, finish, htmlResponse, redirectResponse, Redirect } from '@/lib/admin/context';
import { adminLog } from '@/lib/admin/activity';
import { ADMIN_ROUTES } from '@/lib/admin/modules';
import { DbUnavailableError } from '@/lib/db';

export const dynamic = 'force-dynamic';
export const runtime = 'nodejs';

async function handle(request: Request, { params }: { params: Promise<{ path?: string[] }> }): Promise<Response> {
  const segments = (await params).path ?? [];
  const key = segments.join('/');
  const handler = ADMIN_ROUTES[key];
  if (!handler) return htmlResponse('<!DOCTYPE html><title>Not found</title><h1>Not found</h1>', 404);
  // depth: how many "../" lead from this page back to /admin/ ("" itself is /admin)
  const depth = Math.max(0, segments.length - 1);
  const ctx = await buildContext(request, depth);
  let response: Response;
  try {
    const out = await handler(ctx);
    response = typeof out === 'string' ? htmlResponse(out) : out;
  } catch (e) {
    if (e instanceof Redirect) response = redirectResponse(e.location);
    else if (e instanceof DbUnavailableError) response = htmlResponse('<!DOCTYPE html><title>Admin unavailable</title><p>The database is not reachable. Please try again shortly.</p>', 503);
    else {
      adminLog('EXCEPTION', `${request.method} /admin/${key}: ${(e as Error).stack ?? (e as Error).message}`);
      response = htmlResponse('<!DOCTYPE html><title>Something went wrong</title><p>Something went wrong. The error has been logged.</p>', 500);
    }
  }
  return finish(ctx, response);
}

export const GET = handle;
export const POST = handle;
