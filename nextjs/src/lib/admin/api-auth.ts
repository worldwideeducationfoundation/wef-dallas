/**
 * API key check for /api/admin/v1. The key is the ADMIN_API_KEY environment
 * variable (at least 32 characters). Callers send:
 *   Authorization: Bearer <key>     (or  X-API-Key: <key>)
 * With no key configured, the API is switched off.
 */
import 'server-only';
import crypto from 'node:crypto';

export function apiKeyConfigured(): boolean {
  return (process.env.ADMIN_API_KEY ?? '').trim().length >= 32;
}

export function checkApiKey(request: Request): boolean {
  const expected = (process.env.ADMIN_API_KEY ?? '').trim();
  if (expected.length < 32) return false;
  const auth = request.headers.get('authorization') ?? '';
  const given = (auth.toLowerCase().startsWith('bearer ') ? auth.slice(7) : request.headers.get('x-api-key') ?? '').trim();
  if (!given) return false;
  const a = crypto.createHash('sha256').update(given).digest();
  const b = crypto.createHash('sha256').update(expected).digest();
  return crypto.timingSafeEqual(a, b);
}
