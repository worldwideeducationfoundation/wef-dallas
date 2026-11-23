/** Port of public/donate.php (Stripe donation form). Behaviour is in public/js/donate.js. */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import { donateConfig, donateContact, donateMoney } from '@/lib/donate/config';
import { e } from '@/lib/html';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const cfg = donateConfig();
const org = cfg.org;
const SEO = {
  title: 'Donate — ' + org.legalName,
  description: `Give to ${org.legalName}, a 501(c)(3) nonprofit bringing early childhood education to villages in Chitral, Pakistan.`,
  image: 'assets/images/donate/bright-beginnings.webp',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

function StepHeading({ n, id, title, sub = '', titleId, subId }: { n: number; id: string; title: string; sub?: string; titleId?: string; subId?: string }) {
  return (
    <div className="wef-stephead">
      <span className="wef-stepnum" aria-hidden="true">
        {n}
      </span>
      <div>
        <h2 id={id} className="wef-h3">
          <span className="sr-only-wef">Step {n} of 3: </span>
          <span id={titleId}>{title}</span>
        </h2>
        {(sub !== '' || subId) && (
          <p className="wef-muted wef-small" id={subId}>
            {sub}
          </p>
        )}
      </div>
    </div>
  );
}

function Stepper({ id, max }: { id: string; max: number }) {
  return (
    <div data-field="centers">
      <label htmlFor={id} className="wef-label">
        Number of centers
      </label>
      <div className="wef-stepper" data-stepper="" data-max={max}>
        <button type="button" data-step="-1" aria-label="One fewer center" disabled>
          &minus;
        </button>
        <input id={id} inputMode="numeric" autoComplete="off" defaultValue="1" data-centers="" aria-describedby={`${id}-error`} />
        <button type="button" data-step="1" aria-label="One more center">
          +
        </button>
      </div>
      <p id={`${id}-error`} className="wef-error" data-error="centers" hidden></p>
    </div>
  );
}

function MoneyField({ id, field, label, hint }: { id: string; field: string; label: string; hint: string }) {
  return (
    <div>
      <label htmlFor={id} className="wef-label">
        {label}
      </label>
      <div className="wef-money">
        <span aria-hidden="true">$</span>
        <input id={id} className="wef-input" inputMode="decimal" autoComplete="off" placeholder="0.00" data-field={field} aria-describedby={`${id}-hint ${id}-error`} />
      </div>
      <p id={`${id}-hint`} className="wef-hint">
        {hint}
      </p>
      <p id={`${id}-error`} className="wef-error" data-error={field} hidden></p>
    </div>
  );
}

export default async function DonatePage({ searchParams }: { searchParams: Promise<{ canceled?: string }> }) {
  await requirePageEnabled('donate');
  const center = cfg.center;
  const contact = await donateContact();
  const centerPrice = donateMoney(center.priceCents);
  const canceled = (await searchParams).canceled === '1';
  const tel = 'tel:' + contact.phone.replace(/[^+\d]/g, '');
  const smallestPart = Math.min(...cfg.centerParts.map((p) => p.priceCents));
  const options = {
    center: {
      title: 'Sponsor a center',
      price: centerPrice + ' per center',
      panelTitle: 'Sponsor a Bright Beginnings center',
      blurb: 'Start up a complete early childhood learning center. Sponsors are named on the center plaque.',
    },
    parts: { title: 'Fund part of a center', price: 'From ' + donateMoney(smallestPart), panelTitle: 'Fund part of a center', blurb: 'Choose one or more items. Prices are per center.' },
    custom: { title: 'Give any amount', price: 'One-time or monthly', panelTitle: 'Give any amount', blurb: 'A one-time or monthly gift of whatever size suits you.' },
  };
  const clientConfig = {
    custom: cfg.custom,
    maxCenters: cfg.maxCenters,
    plans: center.plans,
    parts: cfg.centerParts.map((p) => ({ id: p.id, label: p.label, priceCents: p.priceCents })),
    options: Object.fromEntries(Object.entries(options).map(([k, o]) => [k, { panelTitle: o.panelTitle, blurb: o.blurb }])),
    checkoutUrl: 'api/checkout.php',
  };
  const mail = `<a class="wef-link" href="mailto:${e(contact.email)}">${e(contact.email)}</a>`;
  const faq: [string, string][] = [
    [
      'Is my donation tax-deductible?',
      `<p>Yes. ${e(org.legalName)} is a registered 501(c)(3) nonprofit, EIN ${e(org.ein)}. Your gift is tax-deductible to the extent allowed by law.</p>` +
        '<p>You will get a thank-you email with your receipt as soon as your payment goes through. Please keep it for your tax records.</p>',
    ],
    [
      'How is my donation used?',
      `<p>Your gift goes to Bright Beginnings early childhood learning centers in Chitral: learning materials, furniture, technology, teacher training and transport. ${e(org.overheadNote ?? '')}</p>`,
    ],
    [
      'How do monthly gifts work?',
      `<p>If you sponsor a center monthly, you pay ${e(donateMoney(center.plans[1].perPaymentCents))} a month for 12 months or ${e(donateMoney(center.plans[2].perPaymentCents))} a month for 24 months. The payments stop by themselves after the last one.</p>` +
        `<p>A monthly gift of any amount carries on until you ask us to stop. To change or stop it, email ${mail}.</p>`,
    ],
    [
      'How will my name appear on the center?',
      '<p>Center sponsors are named on the center&rsquo;s plaque. In the form, tell us exactly how you would like your name to appear, for example &ldquo;The Khan Family&rdquo; or &ldquo;In loving memory of our parents&rdquo;. If you would like to help name the center, tick the box and we will be in touch.</p>',
    ],
    [
      'Can I give in honor or in memory of someone?',
      '<p>Yes. In the form, choose &ldquo;In honor of&rdquo; or &ldquo;In memory of&rdquo; and add their name. We record the dedication with your gift.</p>',
    ],
    [
      'Can I fund just one part of a center?',
      '<p>Yes. Choose &ldquo;Fund part of a center&rdquo; and tick the items you would like to fund, for one center or several. You can also add any other amount.</p>',
    ],
  ];
  const scholarships = cfg.scholarships as { showComingSoonCard?: boolean; comingSoonCopy?: string } | undefined;

  return (
    <SitePage seo={SEO} styles={['css/donate.css']} scripts={['js/donate.js']} mainClassName="wef">
      <section className="wef-hero">
        <div className="wef-wrap wef-hero-grid">
          <div>
            <p className="wef-eyebrow wef-eyebrow--warm">Bright Beginnings · Chitral, Pakistan</p>
            <h1 className="wef-hero-title">Give a child a bright beginning</h1>
            <p className="wef-hero-lead">
              Every gift helps a child in Chitral start school ready to learn. Your generosity equips early childhood learning centers in villages across Upper and Lower
              Chitral, Pakistan.
            </p>
            <dl className="wef-stats">
              {(cfg.impact as unknown as { label: string; value: string }[]).map((s, i) => (
                <div key={i}>
                  <dt>{s.label}</dt>
                  <dd>{s.value}</dd>
                </div>
              ))}
            </dl>
          </div>
          <img
            className="wef-photo wef-hero-photo"
            src="assets/images/donate/bright-beginnings.webp"
            width={1536}
            height={1152}
            alt="Children learning with Montessori materials in a Bright Beginnings center in Chitral"
            fetchPriority="high"
            decoding="async"
          />
        </div>
      </section>

      <section id="give" aria-labelledby="give-title" className="wef-give">
        <h2 id="give-title" className="sr-only-wef">
          Make a donation
        </h2>
        <div className="wef-wrap">
          <noscript>
            <p className="wef-notice wef-notice--error wef-noscript">
              This donation form needs JavaScript. Please turn it on, or email{' '}
              <a className="wef-link" href={`mailto:${contact.email}`}>
                {contact.email}
              </a>{' '}
              and we will help you give.
            </p>
          </noscript>
          <form id="donate-form" className="wef-form-grid" noValidate data-config={JSON.stringify(clientConfig)}>
            <div className="wef-card wef-form">
              {canceled && (
                <p role="status" className="wef-notice wef-notice--spaced">
                  Checkout was cancelled and you have not been charged. You can pick up again below whenever you are ready.
                </p>
              )}
              <section aria-labelledby="step-1" className="wef-form-step">
                <StepHeading n={1} id="step-1" title="How would you like to give?" />
                <fieldset>
                  <legend className="sr-only-wef">How would you like to give?</legend>
                  <div className="wef-tiles">
                    {Object.entries(options).map(([id, o]) => (
                      <label className="wef-choice wef-choice--tile" key={id}>
                        <span className="wef-choice-row">
                          <input type="radio" name="option" value={id} defaultChecked={id === 'center'} />
                          <span className="wef-choice-title wef-heading">{o.title}</span>
                        </span>
                        <span className="wef-tile-sub">{o.price}</span>
                      </label>
                    ))}
                  </div>
                </fieldset>
                {scholarships?.showComingSoonCard && (
                  <p className="wef-soon">
                    <span className="wef-badge">Coming soon</span>
                    <span>{scholarships.comingSoonCopy}</span>
                  </p>
                )}
              </section>

              <section aria-labelledby="step-2" className="wef-form-step">
                <StepHeading n={2} id="step-2" title={options.center.panelTitle} sub={options.center.blurb} titleId="step-2-title" subId="step-2-sub" />
                <div className="wef-stack" data-panel="center">
                  <fieldset id="field-plan" tabIndex={-1} data-field="plan">
                    <legend className="wef-label">How would you like to pay?</legend>
                    <div className="wef-tiles wef-tiles--spaced">
                      {center.plans.map((p, i) => (
                        <label className="wef-choice wef-choice--tile" key={p.id}>
                          <span className="wef-choice-row">
                            <input type="radio" name="plan" value={p.id} defaultChecked={i === 0} />
                            <span className="wef-choice-title">{p.label}</span>
                          </span>
                          <span className="wef-tile-price">
                            <span data-plan-price={p.id}>{donateMoney(p.perPaymentCents)}</span>
                            {p.installments > 1 && <small> / month</small>}
                          </span>
                        </label>
                      ))}
                    </div>
                    <p className="wef-error" data-error="plan" hidden></p>
                  </fieldset>
                  <div className="wef-pair">
                    <Stepper id="field-centers" max={cfg.maxCenters} />
                    <div className="wef-pair-offset">
                      <label className="wef-check">
                        <input type="checkbox" id="field-wantsNaming" />
                        <span>
                          I&rsquo;d like to help name my center(s)
                          <span className="wef-hint">We will be in touch about naming your center.</span>
                        </span>
                      </label>
                    </div>
                  </div>
                  <div id="center-name-wrap" hidden>
                    <label htmlFor="field-centerName" className="wef-label">
                      Do you have a name in mind? (optional)
                    </label>
                    <input id="field-centerName" className="wef-input" maxLength={100} data-field="centerName" aria-describedby="field-centerName-hint field-centerName-error" />
                    <p id="field-centerName-hint" className="wef-hint">
                      For example, a family name or the name of someone you would like to remember.
                    </p>
                    <p id="field-centerName-error" className="wef-error" data-error="centerName" hidden></p>
                  </div>
                </div>

                <div className="wef-stack" data-panel="parts" hidden>
                  <fieldset id="field-items" tabIndex={-1} data-field="items">
                    <legend className="sr-only-wef">Choose one or more. Prices are per center.</legend>
                    <div className="wef-list">
                      {cfg.centerParts.map((p) => (
                        <label className="wef-choice wef-choice--row" key={p.id}>
                          <input type="checkbox" name="items" value={p.id} />
                          <span className="wef-choice-title">{p.label}</span>
                          <span className="wef-choice-price">{donateMoney(p.priceCents)}</span>
                        </label>
                      ))}
                    </div>
                    <p className="wef-error" data-error="items" hidden></p>
                  </fieldset>
                  <div className="wef-pair">
                    <Stepper id="field-centers-parts" max={cfg.maxCenters} />
                    <MoneyField id="field-otherAmountCents" field="otherAmountCents" label="Other amount (optional)" hint="Added on top of the items above, or on its own." />
                  </div>
                </div>

                <div className="wef-stack" data-panel="custom" hidden>
                  <fieldset id="field-frequency" tabIndex={-1} data-field="frequency">
                    <legend className="wef-label">How often?</legend>
                    <div className="wef-segment">
                      <label>
                        <input type="radio" name="frequency" value="once" defaultChecked />
                        <span>One time</span>
                      </label>
                      <label>
                        <input type="radio" name="frequency" value="monthly" />
                        <span>Monthly</span>
                      </label>
                    </div>
                  </fieldset>
                  <div>
                    <p className="wef-label" id="amount-label">
                      Amount
                    </p>
                    <div className="wef-chips" role="group" aria-labelledby="amount-label">
                      {cfg.custom.presetsCents.map((cents) => (
                        <button type="button" className="wef-chip" data-preset={cents} aria-pressed="false" key={cents}>
                          {donateMoney(cents)}
                        </button>
                      ))}
                    </div>
                    <div className="wef-narrow">
                      <MoneyField
                        id="field-amountCents"
                        field="amountCents"
                        label="Or enter another amount"
                        hint={`${donateMoney(cfg.custom.minCents)} to ${donateMoney(cfg.custom.maxCents)}.`}
                      />
                    </div>
                  </div>
                </div>
              </section>

              <section aria-labelledby="step-3" className="wef-form-step">
                <StepHeading n={3} id="step-3" title="About you" />
                <div className="wef-fields">
                  <div className="wef-fields wef-fields--two">
                    <div>
                      <label htmlFor="field-donorName" className="wef-label">
                        Your name<span aria-hidden="true"> *</span>
                      </label>
                      <input id="field-donorName" className="wef-input" autoComplete="name" maxLength={100} required data-field="donorName" aria-describedby="field-donorName-error" />
                      <p id="field-donorName-error" className="wef-error" data-error="donorName" hidden></p>
                    </div>
                    <div>
                      <label htmlFor="field-donorEmail" className="wef-label">
                        Email<span aria-hidden="true"> *</span>
                      </label>
                      <input
                        id="field-donorEmail"
                        type="email"
                        className="wef-input"
                        autoComplete="email"
                        maxLength={254}
                        required
                        data-field="donorEmail"
                        aria-describedby="field-donorEmail-hint field-donorEmail-error"
                      />
                      <p id="field-donorEmail-hint" className="wef-hint">
                        Your receipt is sent here.
                      </p>
                      <p id="field-donorEmail-error" className="wef-error" data-error="donorEmail" hidden></p>
                    </div>
                  </div>
                  <div>
                    <label htmlFor="field-recognitionName" className="wef-label">
                      <span id="recognition-label">How should your name appear on the center plaque?</span>
                      <span aria-hidden="true" id="recognition-required">
                        {' '}
                        *
                      </span>
                    </label>
                    <input
                      id="field-recognitionName"
                      className="wef-input"
                      maxLength={100}
                      required
                      data-field="recognitionName"
                      aria-describedby="field-recognitionName-hint field-recognitionName-error"
                    />
                    <p id="field-recognitionName-hint" className="wef-hint">
                      Write it exactly as it should read, for example &ldquo;The Khan Family&rdquo;.
                    </p>
                    <p id="field-recognitionName-error" className="wef-error" data-error="recognitionName" hidden></p>
                  </div>
                  <fieldset>
                    <legend className="wef-label">Give in honor or in memory of someone? (optional)</legend>
                    <div className="wef-segment">
                      <label>
                        <input type="radio" name="honorType" value="none" defaultChecked />
                        <span>No</span>
                      </label>
                      <label>
                        <input type="radio" name="honorType" value="honor" />
                        <span>In honor of</span>
                      </label>
                      <label>
                        <input type="radio" name="honorType" value="memory" />
                        <span>In memory of</span>
                      </label>
                    </div>
                  </fieldset>
                  <div id="honor-name-wrap" hidden>
                    <label htmlFor="field-honorName" className="wef-label">
                      <span id="honor-label">Name of the person you are honoring</span>
                      <span aria-hidden="true"> *</span>
                    </label>
                    <input id="field-honorName" className="wef-input" maxLength={100} data-field="honorName" aria-describedby="field-honorName-error" />
                    <p id="field-honorName-error" className="wef-error" data-error="honorName" hidden></p>
                  </div>
                  <label className="wef-check">
                    <input type="checkbox" id="field-updatesOptIn" />
                    <span>
                      Send me updates from the field
                      <span className="wef-hint">News and photos from the centers in Chitral.</span>
                    </span>
                  </label>
                </div>
              </section>
            </div>

            <aside aria-labelledby="summary-title" className="wef-summary">
              <div className="wef-summary-head" aria-live="polite">
                <h2 id="summary-title" className="wef-eyebrow">
                  Your gift
                </h2>
                <div id="summary-total">
                  <p className="wef-total">{centerPrice}</p>
                  <p className="wef-summary-note">One-time gift.</p>
                </div>
              </div>
              <div className="wef-summary-body">
                <ul className="wef-lines" id="summary-lines">
                  <li>
                    <span>Bright Beginnings Center</span>
                    <span>{centerPrice}</span>
                  </li>
                </ul>
                <p role="alert" className="wef-notice wef-notice--error" id="summary-errors" hidden></p>
                <p role="alert" className="wef-notice wef-notice--error" id="summary-server" hidden></p>
                <button type="submit" className="wef-btn" id="donate-submit">
                  Continue to payment
                </button>
                <p className="wef-secure">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.6" />
                    <path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="currentColor" strokeWidth="1.6" />
                  </svg>
                  Secure payment by Stripe
                </p>
                <p className="wef-muted wef-fineprint">
                  You pay on Stripe&rsquo;s own page; we never see your card details. {org.legalName} is a 501(c)(3) nonprofit, EIN {org.ein}. {cfg.legal.noGoodsOrServices}
                </p>
              </div>
            </aside>
          </form>
        </div>
      </section>

      <section className="wef-section wef-section--white" aria-labelledby="why-title">
        <div className="wef-wrap wef-split">
          <div>
            <p className="wef-step">Why give</p>
            <h2 id="why-title" className="wef-h2">
              Your support changes lives
            </h2>
            <p className="wef-para">
              At the {org.legalName}, we believe talent is universal, but opportunity is not. Bright Beginnings brings early childhood education to underserved villages in
              Chitral, so children start school ready to learn.
            </p>
            <p className="wef-para">
              A gift of {centerPrice} equips one center with Montessori learning materials, furniture, a carpet, a Smart TV and technology. Sponsor a whole center, spread it
              over 12 or 24 months, or fund one part of it.
            </p>
            <ul className="wef-ticks">
              <li>Secure payment by Stripe</li>
              <li>Tax-deductible: 501(c)(3) organization, EIN {org.ein}</li>
              <li>A thank-you email and tax receipt, sent right away</li>
              <li>Monthly center plans stop by themselves</li>
            </ul>
          </div>
          <div className="wef-card wef-card--roomy">
            <h3 className="wef-h3">Where your {centerPrice} goes</h3>
            <p className="wef-muted wef-small">The cost of starting up one center.</p>
            <ul className="wef-breakdown">
              {cfg.centerParts.map((p) => (
                <li key={p.id}>
                  <span>
                    <span className="wef-breakdown-label">{p.label}</span>
                    <span className="wef-muted wef-small">{p.about}</span>
                  </span>
                  <span className="wef-breakdown-price">{donateMoney(p.priceCents)}</span>
                </li>
              ))}
              <li className="wef-breakdown-total">
                <span>One complete center</span>
                <span>{centerPrice}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <section className="wef-section" aria-labelledby="daf-title">
        <div className="wef-wrap wef-split wef-split--daf">
          <div>
            <p className="wef-step">Another way to give</p>
            <h2 id="daf-title" className="wef-h2">
              Give from a donor-advised fund
            </h2>
            <p className="wef-muted wef-para">
              Have a DAF at Fidelity Charitable, Schwab, Vanguard, a community foundation or another sponsor? Recommend a grant to us using these details. You can name Bright
              Beginnings in the grant note.
            </p>
          </div>
          <dl className="wef-card wef-card--roomy wef-facts">
            <div>
              <dt>Legal name</dt>
              <dd>{org.legalName}</dd>
            </div>
            <div>
              <dt>EIN</dt>
              <dd>{org.ein} (501(c)(3) public charity)</dd>
            </div>
            <div>
              <dt>Address</dt>
              <dd>
                {contact.address.map((line, i) => (
                  <span className="wef-block" key={i}>
                    {line}
                  </span>
                ))}
              </dd>
            </div>
          </dl>
        </div>
      </section>

      <section className="wef-section wef-section--white" aria-labelledby="faq-title">
        <div className="wef-wrap wef-split wef-split--faq">
          <div>
            <p className="wef-step">Questions</p>
            <h2 id="faq-title" className="wef-h2">
              Frequently asked questions
            </h2>
            <p className="wef-muted wef-para">
              Can&rsquo;t find your answer? Email{' '}
              <a className="wef-link" href={`mailto:${contact.email}`}>
                {contact.email}
              </a>{' '}
              or call{' '}
              <a className="wef-link wef-nowrap" href={tel}>
                {contact.phone}
              </a>
              .
            </p>
          </div>
          <div className="wef-faq">
            {faq.map(([q, a]) => (
              <details key={q}>
                <summary>{q}</summary>
                <div dangerouslySetInnerHTML={{ __html: a }} />
              </details>
            ))}
          </div>
        </div>
      </section>
    </SitePage>
  );
}
