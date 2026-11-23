/**
 * Pages On/Off (port of includes/page-visibility.php).
 * A switched-off page answers 404, and links to it render as plain text
 * (<span class="... link-disabled">) — see components/A.tsx and disableLinksToHiddenPages().
 */
import 'server-only';
import { cache } from 'react';
import { notFound } from 'next/navigation';
import pagesJson from '@/data/pages.json';
import { tryFetchAll } from './db';

export const SWITCHABLE_PAGES = pagesJson.switchable as Record<string, string>;
export const PAGE_VISIBILITY_FOLLOWERS = pagesJson.followers as Record<string, string>;

export const disabledPages = cache(async (): Promise<string[]> => {
  const rows = await tryFetchAll<{ page_key: string }>('SELECT page_key FROM pages WHERE is_enabled = 0');
  if (!rows) return [];
  const off = new Set(rows.map((r) => r.page_key));
  return Object.keys(SWITCHABLE_PAGES).filter((k) => off.has(k));
});

export async function pageEnabled(slug: string): Promise<boolean> {
  const s = PAGE_VISIBILITY_FOLLOWERS[slug] ?? slug;
  return !(await disabledPages()).includes(s);
}

/** Slugs whose links should be disabled (switched-off pages plus their followers). */
export async function disabledLinkSlugs(): Promise<string[]> {
  const slugs = [...(await disabledPages())];
  for (const [follower, page] of Object.entries(PAGE_VISIBILITY_FOLLOWERS)) {
    if (slugs.includes(page)) slugs.push(follower);
  }
  return slugs;
}

/** The slug an internal href points at ("./donate?x" -> "donate"), or null. */
export function hrefSlug(href: string): string | null {
  const m = /^(?:\.\/)?([a-z0-9-]+)(?:\.php)?(?:[?#].*)?$/i.exec(href);
  return m ? m[1] : null;
}

const quote = (s: string) => s.replace(/[.*+?^${}()|[\]\\#]/g, '\\$&');

/** Same regex rewrite as the PHP output filter, for raw HTML strings. */
export async function disableLinksToHiddenPages(html: string): Promise<string> {
  const slugs = await disabledLinkSlugs();
  if (!slugs.length) return html;
  const alternatives = slugs.map(quote).join('|');
  const pattern = new RegExp(`<a\\b([^>]*?)\\shref=(["'])(?:\\./)?(?:${alternatives})(?:\\.php)?(?:[?#][^"']*)?\\2([^>]*)>([\\s\\S]*?)</a>`, 'gi');
  return html.replace(pattern, (_m, a1: string, _q: string, a3: string, inner: string) => {
    const attributes = ' ' + a1 + ' ' + a3;
    const c = /\sclass=(["'])(.*?)\1/is.exec(attributes);
    const i = /\sid=(["'])(.*?)\1/is.exec(attributes);
    const cls = c ? c[2].trim() + ' ' : '';
    const id = i ? ` id="${i[2]}"` : '';
    return `<span class="${cls}link-disabled"${id} aria-disabled="true">${inner}</span>`;
  });
}

/** Call at the top of a switchable page: a switched-off page answers 404 (enforcePageVisibility()). */
export async function requirePageEnabled(slug: string): Promise<void> {
  if (!(await pageEnabled(slug))) {
    notFound();
  }
}
