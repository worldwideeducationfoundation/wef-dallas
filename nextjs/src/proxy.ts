/**
 * Runs before every page request. Replaces what public/.htaccess did:
 *  - HTTPS redirect (not on localhost)
 *  - 301s: /public/x -> /x, x.php / x.html -> x, early-childhood & scholarships -> active-projects
 *  - security headers and the Content-Security-Policy (with a per-request nonce
 *    for Next's own inline scripts; the site's scripts are external files)
 *  - passes the request path to the pages (canonical URL, page-visibility checks)
 */
import fs from 'node:fs';
import path from 'node:path';
import { NextResponse, type NextRequest } from 'next/server';
import { NOT_FOUND_HEADER, NOT_FOUND_PATH, notFoundKind } from '@/lib/route-gate';

const PUBLIC_DIR = path.join(process.cwd(), 'public');
const webpTwins = new Map<string, boolean>();

/** .htaccess: serve x.jpg.webp in place of x.jpg (same URL) to browsers that accept WebP. */
function webpTwin(request: NextRequest): NextResponse | null {
  const p = request.nextUrl.pathname;
  if (!/^\/assets\/.+\.(jpe?g|png)$/i.test(p) || !(request.headers.get('accept') ?? '').includes('image/webp')) return null;
  let rel: string;
  try {
    rel = decodeURIComponent(p);
  } catch {
    return null;
  }
  const twin = path.join(PUBLIC_DIR, rel + '.webp');
  if (!twin.startsWith(PUBLIC_DIR + path.sep)) return null;
  let exists = webpTwins.get(twin);
  if (exists === undefined) {
    exists = fs.existsSync(twin);
    webpTwins.set(twin, exists);
  }
  if (!exists) return null;
  const url = request.nextUrl.clone();
  url.pathname = p + '.webp';
  const res = NextResponse.rewrite(url);
  res.headers.set('Vary', 'Accept');
  return res;
}

const LOCAL_HOST = /^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$|\.local(:\d+)?$/i;

export async function proxy(request: NextRequest) {
  if (request.nextUrl.pathname.startsWith('/assets/')) return webpTwin(request) ?? NextResponse.next();
  const url = request.nextUrl;
  const host = request.headers.get('host') ?? '';
  const path = url.pathname;

  // HTTPS (Hostinger terminates TLS in front of Node and sets X-Forwarded-Proto).
  const proto = request.headers.get('x-forwarded-proto');
  if (proto === 'http' && !LOCAL_HOST.test(host)) {
    return NextResponse.redirect(`https://${host}${path}${url.search}`, 301);
  }

  if (request.method === 'GET' || request.method === 'HEAD') {
    if (path.startsWith('/public/')) return redirect(request, path.slice('/public'.length));
    const legacy = /^\/(early-childhood|scholarships)(?:\.php)?\/?$/i.exec(path);
    if (legacy) return redirect(request, '/active-projects');
    const ext = /^\/([a-z0-9-]+)\.(?:php|html)$/.exec(path);
    if (ext && ext[1] !== 'index') return redirect(request, '/' + ext[1]);
    if (path === '/index.php' || path === '/index.html') return redirect(request, '/');
  }

  const nonce = Buffer.from(crypto.randomUUID()).toString('base64');
  const isDev = process.env.NODE_ENV === 'development';
  const isAdmin = path === '/admin' || path.startsWith('/admin/');
  const csp = [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' https://unpkg.com${isDev ? " 'unsafe-eval'" : ''}`,
    "style-src 'self' 'unsafe-inline' https://unpkg.com",
    "font-src 'self'",
    "img-src 'self' data: blob: https://unpkg.com https://*.cartocdn.com https://server.arcgisonline.com https://mt1.google.com",
    `connect-src 'self'${isDev ? ' ws:' : ''}`,
    "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://donorbox.org",
    "object-src 'none'",
    "base-uri 'self'",
    isAdmin ? "form-action 'self' https://authz.constantcontact.com" : "form-action 'self'",
    "frame-ancestors 'self'",
  ].join('; ');

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set('x-nonce', nonce);
  requestHeaders.set('x-pathname', path);
  requestHeaders.set('x-search', url.search);
  requestHeaders.set('Content-Security-Policy', csp);

  // Unknown / switched-off pages: render the "Page not found" page (status 404) server-side.
  const kind = request.method === 'GET' || request.method === 'HEAD' ? await notFoundKind(path, url.searchParams) : null;
  let response: NextResponse;
  if (kind) {
    requestHeaders.set(NOT_FOUND_HEADER, kind);
    const target = request.nextUrl.clone();
    target.pathname = NOT_FOUND_PATH;
    response = NextResponse.rewrite(target, { request: { headers: requestHeaders } });
  } else {
    response = NextResponse.next({ request: { headers: requestHeaders } });
  }
  response.headers.set('Content-Security-Policy', csp);
  response.headers.set('X-Content-Type-Options', 'nosniff');
  response.headers.set('X-Frame-Options', 'SAMEORIGIN');
  response.headers.set('Referrer-Policy', 'strict-origin-when-cross-origin');
  response.headers.set('X-Permitted-Cross-Domain-Policies', 'none');
  response.headers.set('Cross-Origin-Opener-Policy', 'same-origin');
  response.headers.set(
    'Permissions-Policy',
    'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), accelerometer=()',
  );
  if (proto === 'https') response.headers.set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  if (!path.startsWith('/api/')) response.headers.set('Cache-Control', 'no-cache, must-revalidate');
  return response;
}

function redirect(request: NextRequest, pathname: string) {
  const target = request.nextUrl.clone();
  target.pathname = pathname;
  return NextResponse.redirect(target, 301);
}

export const config = {
  matcher: [
    {
      source: '/((?!_next/static|_next/image|css/|js/|admin/assets/|admin/uploads/).*)',
      missing: [
        { type: 'header', key: 'next-router-prefetch' },
        { type: 'header', key: 'purpose', value: 'prefetch' },
      ],
    },
  ],
};
