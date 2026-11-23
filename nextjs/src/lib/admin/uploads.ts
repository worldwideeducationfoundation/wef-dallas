/**
 * Upload handling (port of admin/includes/upload.php + includes/image-derivatives.php).
 *
 * - Images: JPEG/PNG/WebP up to 5MB. PDFs up to 15MB. Type is read from the
 *   file's bytes, never from its name.
 * - Stored as <folder>/<16 hex>.<ext> under UPLOADS_DIR.
 * - JPEG metadata (EXIF/XMP, incl. GPS) is stripped, keeping the orientation.
 * - Derivatives: x.jpg.webp twin, and -480w/-768w/-1200w/-1600w variants (+ .webp)
 *   when the source is wide enough, exactly as the PHP site produced them.
 */
import 'server-only';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

// Without this, sharp keeps files open and Windows refuses to delete them.
sharp.cache(false);
import { UPLOADS_DIR } from '../paths';

export const UPLOAD_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
export const UPLOAD_DOCUMENT_MAX_BYTES = 15 * 1024 * 1024;
const DERIVATIVE_WIDTHS = [480, 768, 1200, 1600];
const DERIVATIVE_WIDTH_MARGIN = 1.15;
const DERIVATIVE_QUALITY = 82;
const DERIVATIVE_MIN_SOURCE_BYTES = 8 * 1024;

export type UploadKind = 'image' | 'pdf';
export type UploadInput = { bytes: Buffer; name?: string };
export type UploadResult = { ok: true; path: string | null } | { ok: false; error: string };

/** Mime type from magic bytes (what finfo does in PHP). */
export function sniffMime(b: Buffer): string | null {
  if (b.length >= 3 && b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return 'image/jpeg';
  if (b.length >= 8 && b.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'image/png';
  if (b.length >= 12 && b.toString('ascii', 0, 4) === 'RIFF' && b.toString('ascii', 8, 12) === 'WEBP') return 'image/webp';
  if (b.length >= 5 && b.toString('ascii', 0, 5) === '%PDF-') return 'application/pdf';
  return null;
}

const IMAGE_MIME: Record<string, string> = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
const DOC_MIME: Record<string, string> = { 'application/pdf': 'pdf' };

/**
 * Stores a new file (replacing oldPath), or keeps/removes the old one when no
 * file is given — same contract as handleFileUpload().
 */
export async function handleUpload(
  file: UploadInput | null,
  folder: string,
  kind: UploadKind,
  oldPath: string | null,
  removeRequested = false,
): Promise<UploadResult> {
  if (!file || file.bytes.length === 0) {
    if (removeRequested && oldPath) {
      deleteUploadedFile(oldPath);
      return { ok: true, path: null };
    }
    return { ok: true, path: oldPath };
  }
  const max = kind === 'image' ? UPLOAD_IMAGE_MAX_BYTES : UPLOAD_DOCUMENT_MAX_BYTES;
  if (file.bytes.length > max) return { ok: false, error: `File must be smaller than ${Math.trunc(max / (1024 * 1024))}MB.` };
  const mime = sniffMime(file.bytes);
  const allowed = kind === 'image' ? IMAGE_MIME : DOC_MIME;
  if (!mime || !allowed[mime]) {
    return { ok: false, error: `Only ${kind === 'image' ? 'JPEG, PNG, or WebP image' : 'PDF'} files are allowed.` };
  }
  if (!/^[a-z0-9-]+$/.test(folder)) return { ok: false, error: 'Could not create the upload directory.' };
  const name = crypto.randomBytes(8).toString('hex') + '.' + allowed[mime];
  const dir = path.join(UPLOADS_DIR, folder);
  try {
    fs.mkdirSync(dir, { recursive: true });
  } catch {
    return { ok: false, error: 'Could not create the upload directory.' };
  }
  const abs = path.join(dir, name);
  let bytes = file.bytes;
  if (mime === 'image/jpeg') bytes = await stripJpegMetadata(bytes);
  try {
    fs.writeFileSync(abs, bytes);
  } catch {
    return { ok: false, error: 'Could not save the uploaded file.' };
  }
  await generateImageDerivatives(abs);
  if (oldPath) deleteUploadedFile(oldPath);
  return { ok: true, path: `${folder}/${name}` };
}

async function stripJpegMetadata(bytes: Buffer): Promise<Buffer> {
  const head = bytes.subarray(0, 65536).toString('latin1');
  if (!head.includes('Exif') && !head.includes('http://ns.adobe.com/xap/')) return bytes;
  try {
    // rotate() applies the EXIF orientation; sharp drops metadata by default.
    return await sharp(bytes).rotate().jpeg({ quality: 90 }).toBuffer();
  } catch {
    return bytes;
  }
}

function variantPath(source: string, width: number): string {
  const ext = path.extname(source);
  return path.join(path.dirname(source), `${path.basename(source, ext)}-${width}w${ext}`);
}

export function isImageDerivative(p: string): boolean {
  const n = path.basename(p);
  return /\.(jpg|jpeg|png)\.webp$/i.test(n) || /-\d+w\.(jpg|jpeg|png|webp)$/i.test(n);
}

export async function generateImageDerivatives(abs: string): Promise<number> {
  const ext = path.extname(abs).slice(1).toLowerCase();
  if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext) || isImageDerivative(abs)) return 0;
  let stat: fs.Stats;
  try {
    stat = fs.statSync(abs);
  } catch {
    return 0;
  }
  if (stat.size < DERIVATIVE_MIN_SOURCE_BYTES) return 0;
  const meta = await sharp(abs).metadata().catch(() => null);
  if (!meta?.width || !meta.height) return 0;
  let written = 0;
  const write = async (target: string, width: number | null, format: 'webp' | 'same') => {
    try {
      let img = sharp(abs);
      if (width) img = img.resize({ width, height: Math.round(meta.height! * (width / meta.width!)), fit: 'fill' });
      if (format === 'webp' || ext === 'webp') img = img.webp({ quality: DERIVATIVE_QUALITY });
      else if (ext === 'png') img = img.png({ compressionLevel: 8 });
      else img = img.jpeg({ quality: DERIVATIVE_QUALITY });
      await img.toFile(target);
      written++;
    } catch {
      /* skip this derivative */
    }
  };
  if (ext !== 'webp') await write(abs + '.webp', null, 'webp');
  for (const width of DERIVATIVE_WIDTHS) {
    if (meta.width < width * DERIVATIVE_WIDTH_MARGIN) continue;
    const variant = variantPath(abs, width);
    await write(variant, width, 'same');
    if (ext !== 'webp') await write(variant + '.webp', width, 'webp');
  }
  return written;
}

export function deleteImageDerivatives(abs: string): void {
  const candidates = [abs + '.webp'];
  for (const w of DERIVATIVE_WIDTHS) {
    const v = variantPath(abs, w);
    candidates.push(v, v + '.webp');
  }
  for (const c of candidates) safeRm(c);
}

export function deleteUploadedFile(relativePath: string | null): void {
  if (!relativePath) return;
  const resolved = path.resolve(UPLOADS_DIR, relativePath);
  if (!resolved.startsWith(UPLOADS_DIR + path.sep)) return;
  if (!fs.existsSync(resolved)) return;
  deleteImageDerivatives(resolved);
  safeRm(resolved);
}

function safeRm(p: string): void {
  try {
    fs.rmSync(p, { force: true });
  } catch (e) {
    console.error('could not delete upload', p, (e as Error).message);
  }
}
