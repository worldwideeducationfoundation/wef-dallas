/**
 * Stripe webhook (port of public/api/stripe-webhook.php). Also at
 * /api/stripe-webhook.php — the URL to give Stripe. Event: checkout.session.completed.
 * Verifies the signature against the raw body; 500 on failure so Stripe retries.
 */
import { NextResponse } from 'next/server';
import { donateSetting } from '@/lib/donate/config';
import { donateLog, limitToInstallments, stripeConstructEvent } from '@/lib/donate/stripe';

export const dynamic = 'force-dynamic';

const respond = (status: number, body: unknown, extra: Record<string, string> = {}) =>
  NextResponse.json(body, { status, headers: { 'Cache-Control': 'no-store', ...extra } });

export async function GET() {
  return respond(405, { error: 'Method not allowed.' }, { Allow: 'POST' });
}

export async function POST(request: Request) {
  const secret = donateSetting('STRIPE_WEBHOOK_SECRET');
  const signature = request.headers.get('stripe-signature') ?? '';
  if (secret === '' || signature === '') return respond(400, { error: 'Webhook is not configured.' });
  const payload = await request.text();
  let event: any;
  try {
    event = stripeConstructEvent(payload, signature, secret);
  } catch {
    return respond(400, { error: 'Invalid signature.' });
  }
  const type = String(event.type ?? '');
  const eventId = String(event.id ?? '');
  try {
    if (type === 'checkout.session.completed') {
      const session = event.data?.object ?? {};
      const meta = session.metadata && typeof session.metadata === 'object' ? session.metadata : {};
      if ((meta.source ?? '') === 'wef-donate') {
        donateLog('checkout completed', {
          session: session.id ?? null,
          mode: session.mode ?? null,
          paymentStatus: session.payment_status ?? null,
          amountTotal: session.amount_total ?? null,
          currency: session.currency ?? null,
          donationType: meta.donation_type ?? null,
          centers: meta.centers ?? null,
          parts: meta.parts ?? null,
          nameMyCenter: meta.name_my_center ?? null,
          hasHonor: Boolean(meta.honor_type),
          updatesOptIn: meta.updates_opt_in ?? null,
        });
        const installments = /^\d+$/.test(String(meta.installments ?? '')) ? Number(meta.installments) : 0;
        const sub = session.subscription ?? null;
        const subscriptionId = sub && typeof sub === 'object' ? (sub.id ?? null) : sub;
        if ((session.mode ?? '') === 'subscription' && typeof subscriptionId === 'string' && subscriptionId !== '' && installments > 1) {
          const result = await limitToInstallments(subscriptionId, installments);
          donateLog('fixed-term plan', { subscription: subscriptionId, payments: installments, result });
        }
      }
    }
  } catch (e) {
    donateLog(`webhook ${type} ${eventId} failed: ${(e as Error).message}`);
    return respond(500, { error: 'Webhook handler failed.' });
  }
  return respond(200, { received: true });
}
