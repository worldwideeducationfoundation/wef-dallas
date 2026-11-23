/**
 * Decides in the proxy, before rendering, whether a public URL is a 404
 * (unknown page, switched-off page, missing completed project).
 *
 * Why not just call notFound() in the page: in Next 16.4 a notFound() thrown
 * while a page renders is sent as an empty error shell (<html id="__next_error__">)
 * that the browser fills in with JavaScript. The PHP site sends the full
 * "Page not found" page in the HTML. A URL that matches no route at all is
 * server-rendered properly, so the proxy rewrites known-404 URLs to one
 * (NOT_FOUND_PATH); app/not-found.tsx renders it with status 404.
 * The notFound() calls in the pages stay as a safety net.
 */
import fs from 'node:fs';
import path from 'node:path';
import pagesJson from '@/data/pages.json';
import projectPagesJson from '@/data/project-pages.json';
import { tryFetchAll, tryFetchOne } from './db';

/** Matches no route (the [slug] route is one segment deep). */
export const NOT_FOUND_PATH = '/_404/page';
/** Request header telling app/not-found.tsx which "not found" text to show. */
export const NOT_FOUND_HEADER = 'x-wef-not-found';
export type NotFoundKind = 'page' | 'project';

/** Top-level folders under src/app/(site) plus the other app routes. */
const STATIC_ROUTES = new Set([
  'active-projects',
  'annual-reports',
  'completed-project-details',
  'completed-projects',
  'contact',
  'donate',
  'donate-thank-you',
  'gallery',
  'impact-goals',
  'our-mission',
  'our-team',
  'success-stories',
  'video-gallery',
  'volunteer',
]);
/** Paths the gate never touches (route handlers, Next internals). */
const PASS_THROUGH = /^\/(?:api|admin|_next|verify-centers)(?:\/|$)/;

const SWITCHABLE = pagesJson.switchable as Record<string, string>;
const FOLLOWERS = pagesJson.followers as Record<string, string>;
const PROJECT_PAGES = projectPagesJson as Record<string, unknown>;

const PUBLIC_DIR = path.join(process.cwd(), 'public');
const publicFiles = new Map<string, boolean>();

function isPublicFile(pathname: string): boolean {
  let rel: string;
  try {
    rel = decodeURIComponent(pathname);
  } catch {
    return false;
  }
  const file = path.join(PUBLIC_DIR, rel);
  if (!file.startsWith(PUBLIC_DIR + path.sep)) return false;
  let hit = publicFiles.get(file);
  if (hit === undefined) {
    try {
      hit = fs.statSync(file).isFile();
    } catch {
      hit = false;
    }
    publicFiles.set(file, hit);
  }
  return hit;
}

/** Safety net for a page folder added without updating STATIC_ROUTES (needs src/ on the server). */
const SITE_APP_DIR = path.join(process.cwd(), 'src', 'app', '(site)');
function isSiteRouteFolder(slug: string): boolean {
  return /^[a-z0-9-]+$/.test(slug) && fs.existsSync(path.join(SITE_APP_DIR, slug, 'page.tsx'));
}

async function pageSwitchedOff(slug: string): Promise<boolean> {
  const key = FOLLOWERS[slug] ?? slug;
  if (!(key in SWITCHABLE)) return false;
  const rows = await tryFetchAll<{ page_key: string }>('SELECT page_key FROM pages WHERE is_enabled = 0 AND page_key = :key', { key });
  return Boolean(rows && rows.length);
}

async function completedProjectExists(slug: string): Promise<boolean> {
  if (!slug) return false;
  const row = await tryFetchOne(
    "SELECT 1 AS ok FROM completed_projects WHERE slug = :slug AND status = 'active' AND deleted_at IS NULL LIMIT 1",
    { slug },
  );
  if (row) return true;
  const { default: fallback } = await import('@/data/completed-projects.json');
  return (fallback as { slug: string }[]).some((p) => p.slug === slug);
}

/** null = render normally; otherwise which "not found" page to show. */
export async function notFoundKind(pathname: string, search: URLSearchParams): Promise<NotFoundKind | null> {
  if (pathname === '/' || PASS_THROUGH.test(pathname) || isPublicFile(pathname)) return null;
  const segments = pathname.replace(/\/+$/, '').split('/').slice(1);
  if (segments.length !== 1) return 'page';
  const slug = segments[0];
  if (STATIC_ROUTES.has(slug) || isSiteRouteFolder(slug)) {
    if (await pageSwitchedOff(slug)) return 'page';
    if (slug === 'completed-project-details' && !(await completedProjectExists(search.get('slug') ?? ''))) return 'project';
    return null;
  }
  return slug in PROJECT_PAGES ? null : 'page';
}
