/**
 * Serves admin-uploaded files from UPLOADS_DIR at the same URLs the PHP site
 * used (/admin/uploads/<folder>/<file>). Uploads live outside public/ so new
 * ones are served without a rebuild and survive a redeploy.
 */
import fs from 'node:fs';
import path from 'node:path';
import { uploadsPath } from '@/lib/paths';

const TYPES: Record<string, string> = {
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.pdf': 'application/pdf',
};

export async function GET(request: Request, { params }: { params: Promise<{ path: string[] }> }) {
  const parts = (await params).path;
  if (parts.some((p) => p.startsWith('.') || p.includes('..'))) return new Response('Not found', { status: 404 });
  let file = uploadsPath(parts.join('/'));
  const ext = file ? path.extname(file).toLowerCase() : '';
  if (!file || !TYPES[ext]) return new Response('Not found', { status: 404 });

  // WebP twin (x.jpg.webp) for browsers that accept it, like the .htaccess rule.
  let type = TYPES[ext];
  const accept = request.headers.get('accept') ?? '';
  if (/\.(jpe?g|png)$/i.test(file) && accept.includes('image/webp') && fs.existsSync(file + '.webp')) {
    file = file + '.webp';
    type = 'image/webp';
  }
  let stat: fs.Stats;
  try {
    stat = fs.statSync(file);
    if (!stat.isFile()) throw new Error('not a file');
  } catch {
    return new Response('Not found', { status: 404 });
  }
  const body = fs.readFileSync(file);
  const headers: Record<string, string> = {
    'Content-Type': type,
    'Content-Length': String(stat.size),
    'Cache-Control': ext === '.pdf' ? 'public, max-age=2592000' : 'public, max-age=15552000',
    'Last-Modified': stat.mtime.toUTCString(),
    'X-Content-Type-Options': 'nosniff',
    Vary: 'Accept',
  };
  if (ext === '.pdf') headers['Content-Disposition'] = 'inline';
  if (ext === '.svg') headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'";
  return new Response(body, { headers });
}
