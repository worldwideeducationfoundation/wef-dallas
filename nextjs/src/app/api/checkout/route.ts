/**
 * Creates the Stripe Checkout Session (port of public/api/checkout.php).
 * Also at /api/checkout.php. JSON in, {url} out. Prices are worked out here
 * from donate-config.json; the browser only sends choices.
 */
import { NextResponse } from 'next/server';
import { donateConfig, donateMoney } from '@/lib/donate/config';
import { buildMetadata, buildQuote, invoiceFooter, receiptDescription, validateDonation } from '@/lib/donate/pricing';
import { DonateStripeError, donateBaseUrl, donateLog, stripeRequest } from '@/lib/donate/stripe';

export const dynamic = 'force-dynamic';

const respond = (status: number, body: unknown, extra: Record<string, string> = {}) =>
  NextResponse.json(body, { status, headers: { 'Cache-Control': 'no-store', ...extra } });

export async function GET() {
  return respond(405, { error: 'Unsupported request.' }, { Allow: 'POST' });
}

export async function POST(request: Request) {
  if (!(request.headers.get('content-type') ?? '').toLowerCase().includes('application/json')) return respond(415, { error: 'Unsupported request.' });
  let raw: unknown;
  try {
    raw = await request.json();
  } catch {
    return respond(400, { error: 'Unreadable request.' });
  }
  if (!raw || typeof raw !== 'object') return respond(400, { error: 'Unreadable request.' });

  const result = validateDonation(raw);
  if (!result.ok) return respond(422, { error: 'Please check the highlighted fields.', fieldErrors: result.errors });

  const cfg = donateConfig();
  const donation = result.donation;
  const quote = buildQuote(donation.selection);
  const metadata = buildMetadata(donation, quote);
  const baseUrl = donateBaseUrl(request);
  const recurring = quote.mode === 'subscription';
  const receipt = receiptDescription(quote);
  const footer = await invoiceFooter();

  const lineItems = quote.lines.map((line) => ({
    quantity: line.quantity,
    price_data: {
      currency: cfg.currency,
      unit_amount: line.unitAmountCents,
      product_data: { name: line.name, description: line.description },
      ...(recurring ? { recurring: { interval: 'month' } } : {}),
    },
  }));

  const message = [
    `${cfg.org.legalName} is a 501(c)(3) nonprofit (EIN ${cfg.org.ein}). ${cfg.legal.noGoodsOrServices}`,
    recurring && quote.installments
      ? `You will be charged ${donateMoney(quote.perPaymentCents)} today and every month after that, ${quote.installments} payments in all. It then stops by itself.`
      : '',
    recurring && !quote.installments ? `You will be charged ${donateMoney(quote.perPaymentCents)} today and every month until you ask us to stop.` : '',
  ]
    .filter(Boolean)
    .join(' ');

  const params: Record<string, unknown> = {
    mode: quote.mode,
    line_items: lineItems,
    customer_email: donation.details.donorEmail,
    metadata,
    adaptive_pricing: { enabled: false },
    custom_text: { submit: { message } },
    success_url: baseUrl + 'donate-thank-you?session_id={CHECKOUT_SESSION_ID}',
    cancel_url: baseUrl + 'donate?canceled=1',
  };
  if (recurring) {
    params.subscription_data = { metadata, description: receipt, invoice_settings: { description: receipt, footer } };
  } else {
    params.submit_type = 'donate';
    params.customer_creation = 'always';
    params.payment_intent_data = { description: receipt, metadata };
    if (cfg.invoiceForOneTimeGifts) params.invoice_creation = { enabled: true, invoice_data: { description: receipt, footer, metadata } };
  }

  try {
    const session = await stripeRequest('POST', 'checkout/sessions', params);
    const url = String(session.url ?? '');
    if (url === '') throw new DonateStripeError('Stripe did not return a checkout URL.');
    return respond(200, { url });
  } catch (e) {
    donateLog('could not create checkout session: ' + (e as Error).message);
    return respond(502, { error: 'We could not start the payment. Nothing was charged. Please try again in a moment.' });
  }
}
