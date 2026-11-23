/**
 * Asset URL and image helpers (port of includes/assets.php).
 * asset() adds ?v=<mtime hex> so a changed file ships under a new URL.
 */
import 'server-only';
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

// Without this, sharp keeps files open and Windows refuses to delete them.
sharp.cache(false);
import { publicPath, uploadsPath } from './paths';

const versions = new Map<string, string>();

export function asset(p: string): string {
  const rel = p.replace(/^\/+/, '');
  let v = versions.get(rel);
  if (v === undefined) {
    try {
      const mtime = Math.floor(fs.statSync(publicPath(rel)).mtimeMs / 1000);
      v = mtime.toString(16).slice(-8);
    } catch {
      v = '0';
    }
    if (process.env.NODE_ENV === 'production') versions.set(rel, v);
  }
  return `${rel}?v=${v}`;
}

/** Absolute file path for a site-relative src (public/ or admin/uploads/). */
export function fileForSrc(src: string): string | null {
  const rel = src.replace(/^\/+/, '').split('?')[0];
  if (rel.startsWith('admin/uploads/')) return uploadsPath(rel);
  return publicPath(decodeURIComponent(rel));
}

export function fileExists(abs: string | null): boolean {
  if (!abs) return false;
  try {
    return fs.statSync(abs).isFile();
  } catch {
    return false;
  }
}

const sizeCache = new Map<string, { width: number; height: number } | null>();

export async function imageSize(src: string): Promise<{ width: number; height: number } | null> {
  const abs = fileForSrc(src);
  if (!abs || !fileExists(abs)) return null;
  if (sizeCache.has(abs)) return sizeCache.get(abs)!;
  let size: { width: number; height: number } | null = null;
  try {
    const meta = await sharp(abs).metadata();
    if (meta.width && meta.height) size = { width: meta.width, height: meta.height };
  } catch {
    size = null;
  }
  sizeCache.set(abs, size);
  return size;
}

const srcsetCache = new Map<string, string>();

/** Width-descriptor srcset from the -480w/-768w/-1200w/-1600w variants beside a file. */
export async function imageSrcset(src: string): Promise<string> {
  src = src.replace(/^\/+/, '');
  if (srcsetCache.has(src)) return srcsetCache.get(src)!;
  const abs = fileForSrc(src);
  if (!abs || !fileExists(abs)) {
    srcsetCache.set(src, '');
    return '';
  }
  const dir = path.posix.dirname(src);
  const ext = path.posix.extname(src).slice(1);
  const base = path.posix.basename(src, '.' + ext);
  const entries: string[] = [];
  for (const w of [480, 768, 1200, 1600]) {
    const rel = (dir === '.' ? '' : dir + '/') + `${base}-${w}w.${ext}`;
    if (fileExists(fileForSrc(rel))) entries.push(`${rel} ${w}w`);
  }
  let out = '';
  if (entries.length) {
    const size = await imageSize(src);
    if (size) entries.push(`${src} ${size.width}w`);
    out = entries.join(', ');
  }
  srcsetCache.set(src, out);
  return out;
}

/** The first existing -{w}w variant of an image, for CSS backgrounds. */
export function imageVariant(image: string, widths: number[]): string | null {
  const dir = path.posix.dirname(image);
  const ext = path.posix.extname(image).slice(1);
  const name = path.posix.basename(image, '.' + ext);
  for (const w of widths) {
    const candidate = `${dir}/${name}-${w}w.${ext}`;
    if (fileExists(fileForSrc(candidate))) return candidate;
  }
  return null;
}
