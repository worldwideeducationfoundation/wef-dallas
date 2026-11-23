/**
 * Validation and pricing for donations (port of includes/donate/pricing.php,
 * itself a port of the tested Next.js handoff). Same rules, same messages,
 * same output shapes. Prices come only from donate-config.json.
 */
import 'server-only';
import { donateConfig, donateContact, donateMoney } from './config';

type Errors = Record<string, string>;
export type Selection =
  | { option: 'center'; plan: string; centers: number; wantsNaming: boolean; centerName?: string }
  | { option: 'parts'; items: string[]; centers: number; otherAmountCents?: number }
  | { option: 'custom'; frequency: 'once' | 'monthly'; amountCents: number };
export type Details = {
  donorName: string;
  donorEmail: string;
  recognitionName: string;
  honorType: 'none' | 'honor' | 'memory';
  honorName: string;
  updatesOptIn: boolean;
};
export type Donation = { selection: Selection; details: Details };
export type QuoteLine = { label: string; name: string; description: string; unitAmountCents: number; quantity: number };
export type Quote = {
  mode: 'payment' | 'subscription';
  lines: QuoteLine[];
  perPaymentCents: number;
  installments: number | null;
  totalCents: number | null;
  summary: string;
  donationType: string;
};

const isNumber = (v: unknown): v is number => typeof v === 'number' && Number.isFinite(v);
const isWhole = (v: unknown) => isNumber(v) && Number.isInteger(v);
const has = (o: object, k: string) => Object.prototype.hasOwnProperty.call(o, k);
const isObj = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v);

export const DONATE_EMAIL_PATTERN = /^(?!\.)(?!.*\.\.)([A-Za-z0-9_'+\-.]*)[A-Za-z0-9_+-]@([A-Za-z0-9][A-Za-z0-9-]*\.)+[A-Za-z]{2,}$/;

function amountError(v: unknown, typeMessage: string): string | null {
  const c = donateConfig().custom;
  if (!isNumber(v)) return typeMessage;
  if (!isWhole(v)) return 'Enter an amount in dollars and cents.';
  if (v < c.minCents) return `The smallest gift is ${donateMoney(c.minCents)}.`;
  if (v > c.maxCents) return `Online gifts are limited to ${donateMoney(c.maxCents)}. Please contact us for a larger gift.`;
  return null;
}

function centersError(v: unknown): string | null {
  const max = donateConfig().maxCenters;
  if (!isNumber(v)) return 'Choose how many centers.';
  if (!isWhole(v)) return 'Choose a whole number of centers.';
  if (v < 1) return 'Choose at least one center.';
  if (v > max) return `Please contact us to support more than ${max} centers.`;
  return null;
}

function str(v: unknown, max: number, tooLong: string, typeMessage: string): { value: string; error: string | null } {
  if (typeof v !== 'string') return { value: '', error: typeMessage };
  const t = v.trim();
  return { value: t, error: [...t].length > max ? tooLong : null };
}

export function validateSelection(raw: unknown): { ok: true; selection: Selection } | { ok: false; errors: Errors } {
  const cfg = donateConfig();
  const errors: Errors = {};
  const add = (k: string, m: string | null) => {
    if (m !== null && !(k in errors)) errors[k] = m;
  };
  if (!isObj(raw) || !['center', 'parts', 'custom'].includes(raw.option as string)) return { ok: false, errors: { option: 'Choose how you would like to give.' } };

  if (raw.option === 'center') {
    if (!cfg.center.plans.some((p) => p.id === raw.plan)) add('plan', 'Choose how you would like to give.');
    add('centers', centersError(raw.centers));
    if (typeof raw.wantsNaming !== 'boolean') add('wantsNaming', 'Tell us whether you would like to help name your center.');
    let centerName: string | null = null;
    if (has(raw, 'centerName')) {
      const r = str(raw.centerName, 100, 'That name is too long.', 'That name is not valid.');
      centerName = r.value;
      add('centerName', r.error);
    }
    if (Object.keys(errors).length) return { ok: false, errors };
    const sel: Selection = { option: 'center', plan: raw.plan as string, centers: raw.centers as number, wantsNaming: raw.wantsNaming as boolean };
    if (centerName !== null) sel.centerName = centerName;
    return { ok: true, selection: sel };
  }

  if (raw.option === 'parts') {
    const partIds = cfg.centerParts.map((p) => p.id);
    let items: unknown[] = [];
    if (!Array.isArray(raw.items)) add('items', 'Choose at least one part of a center, or enter another amount.');
    else {
      items = raw.items;
      for (const id of items) if (!partIds.includes(id as string)) add('items', 'That item is not available.');
      if (!('items' in errors) && new Set(items).size !== items.length) add('items', 'Each part can only be chosen once.');
    }
    add('centers', centersError(raw.centers));
    let other: unknown = null;
    if (has(raw, 'otherAmountCents')) {
      add('otherAmountCents', amountError(raw.otherAmountCents, 'Enter an amount in dollars and cents.'));
      other = raw.otherAmountCents;
    }
    if (!Object.keys(errors).length && items.length === 0 && other === null) add('items', 'Choose at least one part of a center, or enter another amount.');
    if (Object.keys(errors).length) return { ok: false, errors };
    const sel: Selection = { option: 'parts', items: items as string[], centers: raw.centers as number };
    if (other !== null) sel.otherAmountCents = other as number;
    return { ok: true, selection: sel };
  }

  if (!['once', 'monthly'].includes(raw.frequency as string)) add('frequency', 'Choose one-time or monthly.');
  add('amountCents', amountError(raw.amountCents, 'Enter an amount.'));
  if (Object.keys(errors).length) return { ok: false, errors };
  return { ok: true, selection: { option: 'custom', frequency: raw.frequency as 'once' | 'monthly', amountCents: raw.amountCents as number } };
}

export function validateDetails(rawIn: unknown): { ok: true; details: Details } | { ok: false; errors: Errors } {
  const raw = isObj(rawIn) ? rawIn : {};
  const errors: Errors = {};
  const add = (k: string, m: string | null) => {
    if (m !== null && !(k in errors)) errors[k] = m;
  };
  const tooLong = 'That name is too long.';
  const donor = str(raw.donorName, 100, tooLong, 'Please enter your name.');
  add('donorName', donor.error);
  if (donor.error === null && donor.value === '') add('donorName', 'Please enter your name.');
  const email = raw.donorEmail;
  if (typeof email !== 'string' || !DONATE_EMAIL_PATTERN.test(email.trim())) add('donorEmail', 'Please enter a valid email address.');
  else if (Buffer.byteLength(email.trim()) > 254) add('donorEmail', 'That email address is too long.');
  const recognition = str(raw.recognitionName, 100, tooLong, 'That name is not valid.');
  add('recognitionName', recognition.error);
  if (!['none', 'honor', 'memory'].includes(raw.honorType as string)) add('honorType', 'Choose whether this gift is in honor or in memory of someone.');
  const honor = str(raw.honorName, 100, tooLong, 'That name is not valid.');
  add('honorName', honor.error);
  if (typeof raw.updatesOptIn !== 'boolean') add('updatesOptIn', 'Tell us whether you would like updates.');
  if (Object.keys(errors).length) return { ok: false, errors };
  return {
    ok: true,
    details: {
      donorName: donor.value,
      donorEmail: (email as string).trim(),
      recognitionName: recognition.value,
      honorType: raw.honorType as Details['honorType'],
      honorName: honor.value,
      updatesOptIn: raw.updatesOptIn as boolean,
    },
  };
}

export function validateDonation(raw: unknown): { ok: true; donation: Donation } | { ok: false; errors: Errors } {
  const body = isObj(raw) ? raw : {};
  const sel = validateSelection(body.selection);
  const det = validateDetails(body.details);
  let errors: Errors = {};
  if (!sel.ok) errors = { ...errors, ...sel.errors };
  if (!det.ok) errors = { ...errors, ...det.errors };
  if (det.ok) {
    if (sel.ok && sel.selection.option === 'center' && det.details.recognitionName === '')
      errors.recognitionName = 'Please tell us how your name should appear on the center plaque.';
    if (det.details.honorType !== 'none' && det.details.honorName === '') errors.honorName = 'Please enter the name of the person.';
  }
  if (Object.keys(errors).length || !sel.ok || !det.ok) return { ok: false, errors };
  return { ok: true, donation: { selection: sel.selection, details: det.details } };
}

export function buildQuote(selection: Selection): Quote {
  const cfg = donateConfig();
  const center = cfg.center;
  if (selection.option === 'center') {
    const plan = center.plans.find((p) => p.id === selection.plan);
    if (!plan) throw new Error('Unknown plan');
    const monthly = plan.installments > 1;
    const perPayment = plan.perPaymentCents * selection.centers;
    return {
      mode: monthly ? 'subscription' : 'payment',
      lines: [
        {
          label: 'Bright Beginnings Center',
          name: center.name,
          description: `${center.place}. ${plan.label}.`,
          unitAmountCents: plan.perPaymentCents,
          quantity: selection.centers,
        },
      ],
      perPaymentCents: perPayment,
      installments: plan.installments,
      totalCents: perPayment * plan.installments,
      summary: monthly ? `${donateMoney(perPayment)} a month for ${plan.installments} months` : `${donateMoney(perPayment)}, one time`,
      donationType: monthly ? `center_monthly_${plan.installments}` : 'center_lump',
    };
  }
  if (selection.option === 'parts') {
    const lines: QuoteLine[] = selection.items.map((id) => {
      const part = cfg.centerParts.find((p) => p.id === id);
      if (!part) throw new Error('Unknown part');
      return {
        label: part.label,
        name: `${part.label} for a Bright Beginnings center`,
        description: `${center.place}.`,
        unitAmountCents: part.priceCents,
        quantity: selection.centers,
      };
    });
    if (selection.otherAmountCents !== undefined) {
      lines.push({
        label: 'Other amount',
        name: 'Additional gift toward Bright Beginnings centers',
        description: `${center.place}.`,
        unitAmountCents: selection.otherAmountCents,
        quantity: 1,
      });
    }
    const total = lines.reduce((s, l) => s + l.unitAmountCents * l.quantity, 0);
    return { mode: 'payment', lines, perPaymentCents: total, installments: 1, totalCents: total, summary: `${donateMoney(total)}, one time`, donationType: 'center_parts' };
  }
  const monthly = selection.frequency === 'monthly';
  const amount = selection.amountCents;
  return {
    mode: monthly ? 'subscription' : 'payment',
    lines: [{ label: 'Donation', name: `Donation to ${cfg.org.legalName}`, description: 'Education in Chitral, Pakistan and Central Asia.', unitAmountCents: amount, quantity: 1 }],
    perPaymentCents: amount,
    installments: monthly ? null : 1,
    totalCents: monthly ? null : amount,
    summary: monthly ? `${donateMoney(amount)} a month, until you stop it` : `${donateMoney(amount)}, one time`,
    donationType: monthly ? 'custom_monthly' : 'custom_once',
  };
}

export function buildMetadata(donation: Donation, quote: Quote): Record<string, string> {
  const { selection, details } = donation;
  const noHonor = details.honorType === 'none';
  const entries: Record<string, string> = {
    source: 'wef-donate',
    donation_type: quote.donationType,
    donor_name: details.donorName,
    recognition_name: details.recognitionName,
    honor_type: noHonor ? '' : details.honorType,
    honor_name: noHonor ? '' : details.honorName,
    updates_opt_in: details.updatesOptIn ? 'yes' : 'no',
    installments: (quote.installments ?? 0) > 1 ? String(quote.installments) : '',
  };
  if (selection.option === 'center') {
    entries.centers = String(selection.centers);
    entries.name_my_center = selection.wantsNaming ? 'yes' : 'no';
    if (selection.wantsNaming && (selection.centerName ?? '') !== '') entries.suggested_center_name = selection.centerName!;
  }
  if (selection.option === 'parts') {
    if (selection.items.length > 0) {
      entries.centers = String(selection.centers);
      entries.parts = selection.items.join(',');
    }
    if (selection.otherAmountCents !== undefined) entries.other_amount_cents = String(selection.otherAmountCents);
  }
  return Object.fromEntries(Object.entries(entries).filter(([, v]) => v !== ''));
}

export function receiptDescription(quote: Quote): string {
  const cfg = donateConfig();
  const what = quote.lines.map((l) => (l.quantity > 1 ? `${l.quantity} x ` : '') + l.name).join('; ');
  return `Tax-deductible donation to ${cfg.org.legalName} (EIN ${cfg.org.ein}): ${what}. ${cfg.legal.noGoodsOrServices}`;
}

export async function invoiceFooter(): Promise<string> {
  const cfg = donateConfig();
  const email = (await donateContact()).email;
  return (
    `${cfg.org.legalName} is a 501(c)(3) nonprofit, EIN ${cfg.org.ein}. Your contribution is tax-deductible to the extent allowed by law. ${cfg.legal.noGoodsOrServices}` +
    (email !== '' ? ` Questions: ${email}` : '')
  );
}
