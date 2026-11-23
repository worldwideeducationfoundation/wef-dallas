/**
 * Editable content blocks (port of includes/content-blocks.php).
 * Defaults live in src/data/block-registry.json (exported from the PHP site);
 * admin edits are rows in the content_blocks table that override a default.
 */
import 'server-only';
import { cache } from 'react';
import registryJson from '@/data/block-registry.json';
import seoDefaultsJson from '@/data/seo-page-defaults.json';
import limitsJson from '@/data/block-limits.json';
import { tryFetchAll } from './db';
import { e, phpTrim, richText } from './html';
import { fileExists } from './assets';
import { uploadsPath } from './paths';

export type BlockDef = { label: string; type: string; default: string; group?: string; help?: string };
export type PageBlocks = { label: string; url: string; blocks: Record<string, BlockDef> };

export const BLOCK_REGISTRY = registryJson as unknown as Record<string, PageBlocks>;
export const SEO_PAGE_DEFAULTS = seoDefaultsJson as unknown as Record<string, [string, string]>;
export const BLOCK_LIMITS = limitsJson as Record<string, number>;

export function blockLimit(type: string): number {
  return BLOCK_LIMITS[type] ?? BLOCK_LIMITS.text;
}

/** The registry already includes the per-page SEO fields (merged at export). */
export function blockRegistry(): Record<string, PageBlocks> {
  return BLOCK_REGISTRY;
}

export const blockOverrides = cache(async (page: string): Promise<Record<string, string>> => {
  const rows = await tryFetchAll<{ block_key: string; value: string }>(
    'SELECT block_key, value FROM content_blocks WHERE page = :page',
    { page },
  );
  return Object.fromEntries((rows ?? []).map((r) => [r.block_key, r.value]));
});

function blockDefault(page: string, key: string): string {
  return String(BLOCK_REGISTRY[page]?.blocks?.[key]?.default ?? '');
}

export async function blockValue(page: string, key: string): Promise<string> {
  const overrides = await blockOverrides(page);
  if (overrides[key] !== undefined && overrides[key] !== null && phpTrim(overrides[key]) !== '') return String(overrides[key]);
  return blockDefault(page, key);
}

/** Escaped plain text. */
export async function block(page: string, key: string): Promise<string> {
  return e(await blockValue(page, key));
}

/** Escaped text with line breaks as <br>. */
export async function blockRich(page: string, key: string): Promise<string> {
  return richText(await blockValue(page, key));
}

export async function blockImage(page: string, key: string): Promise<string> {
  const value = await blockValue(page, key);
  if (value !== '' && !value.startsWith('assets/') && /^[a-z0-9_-]+\/[A-Za-z0-9_.-]+$/.test(value) && fileExists(uploadsPath(value))) {
    return 'admin/uploads/' + value;
  }
  return blockDefault(page, key);
}

export async function blockBody(page: string, key: string): Promise<string> {
  return renderBodyText(await blockValue(page, key));
}

/** "## Heading", "- item" and blank-line paragraphs to HTML (port of renderBodyText()). */
export function renderBodyText(value: unknown): string {
  const text = String(value ?? '').replace(/\r\n/g, '\n');
  if (phpTrim(text) === '') return '';
  let html = '';
  let listOpen = false;
  const closeList = () => {
    if (!listOpen) return '';
    listOpen = false;
    return '</ul>';
  };
  for (const chunk of phpTrim(text).split(/\n\s*\n/)) {
    const lines = phpTrim(chunk).split('\n');
    let paragraph = '';
    const flush = () => {
      if (paragraph !== '') html += '<p>' + paragraph + '</p>';
      paragraph = '';
    };
    for (let line of lines) {
      line = phpTrim(line);
      if (line === '') continue;
      if (line.startsWith('## ')) {
        flush();
        html += closeList();
        html += '<h3>' + e(phpTrim(line.slice(3))) + '</h3>';
        continue;
      }
      if (line.startsWith('- ')) {
        flush();
        if (!listOpen) {
          html += '<ul>';
          listOpen = true;
        }
        html += '<li>' + e(phpTrim(line.slice(2))) + '</li>';
        continue;
      }
      html += closeList();
      paragraph += (paragraph === '' ? '' : '<br>\n') + e(line);
    }
    flush();
  }
  html += closeList();
  return html;
}

export async function blockIsOverridden(page: string, key: string): Promise<boolean> {
  const overrides = await blockOverrides(page);
  return overrides[key] !== undefined && phpTrim(overrides[key]) !== '';
}
