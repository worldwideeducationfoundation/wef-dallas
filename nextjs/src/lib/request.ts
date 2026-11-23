/** Per-request values set by src/proxy.ts. */
import 'server-only';
import { headers } from 'next/headers';

export async function nonce(): Promise<string | undefined> {
  return (await headers()).get('x-nonce') ?? undefined;
}

export async function requestPath(): Promise<string> {
  return (await headers()).get('x-pathname') ?? '/';
}

const ALLOWED_HOSTS = ['www.worldwideeducationfoundation.org', 'worldwideeducationfoundation.org', 'localhost', '127.0.0.1'];

/** Scheme + host the page is served on, and whether the host is one we index. */
export async function siteOrigin(): Promise<{ origin: string; trustedHost: boolean }> {
  const h = await headers();
  const proto = (h.get('x-forwarded-proto') ?? '').toLowerCase() === 'https' ? 'https' : process.env.NODE_ENV === 'production' && process.env.SITE_URL?.startsWith('https') ? 'https' : 'http';
  let host = (h.get('x-forwarded-host') ?? h.get('host') ?? '').toLowerCase();
  const extra = (process.env.SITE_HOSTS ?? '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
  const bare = host.replace(/:\d+$/, '');
  let trustedHost = true;
  if (![...ALLOWED_HOSTS, ...extra].includes(bare)) {
    host = 'www.worldwideeducationfoundation.org';
    trustedHost = false;
  }
  const scheme = trustedHost ? proto : 'https';
  return { origin: `${scheme}://${host}`, trustedHost };
}

export async function clientIp(): Promise<string> {
  const h = await headers();
  return (h.get('x-forwarded-for') ?? '').split(',')[0].trim() || h.get('x-real-ip') || '0.0.0.0';
}
