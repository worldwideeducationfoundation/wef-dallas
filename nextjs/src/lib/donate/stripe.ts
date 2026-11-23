/**
 * Stripe over its REST API (port of includes/donate/stripe.php): requests,
 * webhook signature check, and the fixed-term schedule for 12/24-month plans.
 * The secret key is STRIPE_SECRET_KEY; it is never logged or sent to the browser.
 */
import 'server-only';
import crypto from 'node:crypto';
import { donateSetting } from './config';
import { invoiceFooter } from './pricing';
import { appendLog, logTimestamp } from '../log';

export const DONATE_STRIPE_API_VERSION = '2026-11-18.endive';

export class DonateStripeError extends Error {}

export function stripeConfigured(): boolean {
  return donateSetting('STRIPE_SECRET_KEY') !== '';
}

type Params = Record<string, unknown>;

/** Stripe's form encoding: a[b][0][c]=x; booleans as true/false; nulls dropped. */
export function stripeEncode(params: Params | unknown[], prefix = ''): [string, string][] {
  const pairs: [string, string][] = [];
  const entries = Array.isArray(params) ? params.map((v, i) => [String(i), v] as const) : Object.entries(params);
  for (const [key, value] of entries) {
    const name = prefix === '' ? key : `${prefix}[${key}]`;
    if (value === null || value === undefined) continue;
    if (typeof value === 'object') pairs.push(...stripeEncode(value as Params, name));
    else if (typeof value === 'boolean') pairs.push([name, value ? 'true' : 'false']);
    else pairs.push([name, String(value)]);
  }
  return pairs;
}

export async function stripeRequest(method: 'GET' | 'POST', path: string, params: Params = {}, idempotencyKey?: string): Promise<any> {
  const key = donateSetting('STRIPE_SECRET_KEY');
  if (key === '') throw new DonateStripeError('STRIPE_SECRET_KEY is not set.');
  const query = stripeEncode(params)
    .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v))
    .join('&');
  let url = 'https://api.stripe.com/v1/' + path.replace(/^\/+/, '');
  if (method === 'GET' && query !== '') url += '?' + query;
  const headers: Record<string, string> = {
    Authorization: 'Bearer ' + key,
    'Stripe-Version': donateSetting('STRIPE_API_VERSION') || DONATE_STRIPE_API_VERSION,
    Accept: 'application/json',
    'User-Agent': 'wef-donate-node/1.0',
  };
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  if (method !== 'GET') headers['Content-Type'] = 'application/x-www-form-urlencoded';
  let res: Response;
  try {
    res = await fetch(url, { method, headers, body: method === 'GET' ? undefined : query, redirect: 'manual', signal: AbortSignal.timeout(30000) });
  } catch (e) {
    throw new DonateStripeError('Could not reach Stripe: ' + ((e as Error).message || 'unknown network error'));
  }
  let body: any;
  try {
    body = await res.json();
  } catch {
    throw new DonateStripeError(`Stripe answered HTTP ${res.status} with an unreadable body.`);
  }
  if (!body || typeof body !== 'object') throw new DonateStripeError(`Stripe answered HTTP ${res.status} with an unreadable body.`);
  if (res.status < 200 || res.status >= 300) {
    const err = body.error && typeof body.error === 'object' ? body.error : {};
    throw new DonateStripeError(`Stripe HTTP ${res.status} ${err.type ?? 'error'}: ${err.message ?? 'no message'}`);
  }
  return body;
}

/** Verifies the Stripe-Signature header against the RAW body. */
export function stripeConstructEvent(payload: string, header: string, secret: string, tolerance = 300): any {
  let timestamp: number | null = null;
  const signatures: string[] = [];
  for (const part of header.split(',')) {
    const kv = part.trim().split('=');
    if (kv.length < 2) continue;
    const [k, ...rest] = kv;
    const v = rest.join('=');
    if (k === 't') timestamp = /^\d+$/.test(v) ? Number(v) : null;
    else if (k === 'v1') signatures.push(v);
  }
  if (timestamp === null || !signatures.length) throw new DonateStripeError('Malformed signature header.');
  const expected = crypto.createHmac('sha256', secret).update(`${timestamp}.${payload}`).digest('hex');
  const match = signatures.some((sig) => sig.length === expected.length && crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected)));
  if (!match) throw new DonateStripeError('No matching signature.');
  if (Math.abs(Date.now() / 1000 - timestamp) > tolerance) throw new DonateStripeError('Timestamp outside the tolerance zone.');
  try {
    return JSON.parse(payload);
  } catch {
    throw new DonateStripeError('Unreadable event body.');
  }
}

/** Turns a monthly subscription into a schedule that cancels after totalPayments. */
export async function limitToInstallments(subscriptionId: string, totalPayments: number): Promise<'already-limited' | 'created'> {
  const subscription = await stripeRequest('GET', 'subscriptions/' + encodeURIComponent(subscriptionId));
  if (subscription.schedule) return 'already-limited';
  const schedule = await stripeRequest('POST', 'subscription_schedules', { from_subscription: subscriptionId }, 'wef-fixed-term-' + subscriptionId);
  const current = schedule.phases?.[0];
  if (!current || typeof current !== 'object') throw new DonateStripeError('Subscription schedule has no current phase.');
  const idOf = (v: unknown): string | null => (v && typeof v === 'object' ? ((v as { id?: string }).id ?? null) : typeof v === 'string' ? v : null);
  const items = (current.items ?? []).map((item: any) => ({ price: idOf(item.price), quantity: item.quantity ?? 1 }));
  const metadata = subscription.metadata && typeof subscription.metadata === 'object' && Object.keys(subscription.metadata).length ? subscription.metadata : null;
  const shared = {
    items,
    metadata,
    currency: current.currency ?? null,
    description: current.description ?? null,
    collection_method: current.collection_method ?? null,
    default_payment_method: idOf(current.default_payment_method),
    invoice_settings: { description: subscription.description ?? null, footer: await invoiceFooter() },
  };
  await stripeRequest('POST', 'subscription_schedules/' + encodeURIComponent(String(schedule.id)), {
    end_behavior: 'cancel',
    metadata: { source: 'wef-donate', total_payments: String(totalPayments) },
    phases: [
      { ...shared, start_date: current.start_date, end_date: current.end_date },
      { ...shared, duration: { interval: 'month', interval_count: totalPayments - 1 } },
    ],
  });
  return 'created';
}

/** Absolute site URL ending in "/" for Stripe return links. */
export function donateBaseUrl(request: Request): string {
  const configured = donateSetting('DONATE_BASE_URL') || donateSetting('SITE_URL');
  if (configured !== '') return configured.replace(/\/+$/, '') + '/';
  const proto = (request.headers.get('x-forwarded-proto') ?? new URL(request.url).protocol.replace(':', '')).toLowerCase() === 'https' ? 'https' : 'http';
  let host = request.headers.get('x-forwarded-host') ?? request.headers.get('host') ?? 'localhost';
  if (!/^[A-Za-z0-9.-]+(:\d+)?$/.test(host)) host = 'localhost';
  return `${proto}://${host}/`;
}

export function donateLog(message: string, context: Record<string, unknown> = {}): void {
  appendLog('donate.log', `[${logTimestamp()}] ${message}${Object.keys(context).length ? ' ' + JSON.stringify(context) : ''}\n`, 5);
}
