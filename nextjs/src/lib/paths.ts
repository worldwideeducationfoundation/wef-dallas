import 'server-only';
import path from 'node:path';

/** Project root (the nextjs/ folder). */
export const ROOT = process.cwd();
export const PUBLIC_DIR = path.join(ROOT, 'public');
export const UPLOADS_DIR = process.env.UPLOADS_DIR ? path.resolve(process.env.UPLOADS_DIR) : path.join(ROOT, 'uploads');
export const STORAGE_DIR = process.env.STORAGE_DIR ? path.resolve(process.env.STORAGE_DIR) : path.join(ROOT, 'storage');

export function publicPath(rel: string): string {
  return path.join(PUBLIC_DIR, rel.replace(/^\/+/, ''));
}

/** Resolves "admin/uploads/x/y.jpg" or "x/y.jpg" inside UPLOADS_DIR, refusing traversal. */
export function uploadsPath(rel: string): string | null {
  const clean = rel.replace(/^\/+/, '').replace(/^admin\/uploads\//, '');
  const full = path.resolve(UPLOADS_DIR, clean);
  return full.startsWith(UPLOADS_DIR + path.sep) ? full : null;
}
