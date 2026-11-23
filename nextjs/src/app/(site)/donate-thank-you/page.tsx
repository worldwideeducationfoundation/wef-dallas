/** Port of public/donate-thank-you.php — reads the finished payment back from Stripe. Never shows the email. */
import type { Metadata } from 'next';
import A from '@/components/A';
import SitePage from '@/components/site/SitePage';
import { donateConfig, donateMoney } from '@/lib/donate/config';
import { stripeConfigured, stripeRequest } from '@/lib/donate/stripe';
import { pageMetadata } from '@/lib/seo';

const cfg = donateConfig();
const org = cfg.org;
const SEO = { title: 'Thank you — ' + org.legalName, description: `Thank you for your gift to ${org.legalName}.`, noIndex: true };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

async function loadGift(sessionId: string | undefined) {
  if (!sessionId || !/^cs_(test|live)_[A-Za-z0-9]+$/.test(sessionId) || !stripeConfigured()) return null;
  let session: any;
  try {
    session = await stripeRequest('GET', 'checkout/sessions/' + encodeURIComponent(sessionId));
  } catch {
    return null;
  }
  const meta = session.metadata && typeof session.metadata === 'object' ? session.metadata : {};
  if ((meta.source ?? '') !== 'wef-donate' || (session.status ?? '') !== 'complete') return null;
  const amount = donateMoney(Math.trunc(Number(session.amount_total ?? 0)));
  const installments = Math.trunc(Number(meta.installments ?? 0)) || 0;
  const centers = Math.trunc(Number(meta.centers ?? 0)) || 0;
  const forCenters = centers > 0 ? ` for ${centers} ${centers === 1 ? 'center' : 'centers'}` : '';
  let line = `${amount}${forCenters}, one time`;
  if ((session.mode ?? '') === 'subscription') line = installments > 1 ? `${amount} a month${forCenters}, for ${installments} months` : `${amount} a month${forCenters}`;
  let honor = '';
  if ((meta.honor_name ?? '') !== '') honor = `${(meta.honor_type ?? '') === 'memory' ? 'In memory of' : 'In honor of'} ${meta.honor_name}`;
  return { firstName: String(meta.donor_name ?? '').split(' ')[0], line, honor, processing: (session.payment_status ?? '') === 'unpaid' };
}

export default async function DonateThankYouPage({ searchParams }: { searchParams: Promise<{ session_id?: string | string[] }> }) {
  const sid = (await searchParams).session_id;
  const gift = await loadGift(typeof sid === 'string' ? sid : undefined);
  return (
    <SitePage seo={SEO} styles={['css/donate.css']} mainClassName="wef wef-thanks">
      <div className="wef-wrap">
        <div className="wef-card wef-thanks-card" role="status">
          <p className="wef-eyebrow wef-muted">Thank you</p>
          <h1 className="wef-thanks-title">{gift && gift.firstName !== '' ? `Thank you, ${gift.firstName}.` : 'Thank you for your gift.'}</h1>
          {gift && (
            <p className="wef-thanks-gift">
              <strong>{gift.line}</strong>
              {gift.honor !== '' && (
                <>
                  <br />
                  {gift.honor}
                </>
              )}
            </p>
          )}
          <p className="wef-muted wef-para">
            {gift && gift.processing
              ? 'Your payment is still being processed. You will get a receipt by email once it clears.'
              : 'A receipt is on its way to the email address you gave us.'}{' '}
            Because your gift is tax-deductible, please keep the receipt for your records.
          </p>
          <div className="wef-thanks-actions">
            <a href="./" className="wef-btn wef-btn--quiet">
              Back to the home page
            </a>
            <A href="donate" className="wef-btn wef-btn--quiet">
              Make another gift
            </A>
          </div>
          <p className="wef-muted wef-thanks-legal">
            {org.legalName} is a 501(c)(3) nonprofit, EIN {org.ein}. {cfg.legal.noGoodsOrServices}
          </p>
        </div>
      </div>
    </SitePage>
  );
}
