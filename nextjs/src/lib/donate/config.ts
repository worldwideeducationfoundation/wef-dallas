/**
 * Donation settings (port of includes/donate/config.php). The prices and
 * plans live in src/data/donate-config.json — change amounts there only.
 */
import 'server-only';
import configJson from '@/data/donate-config.json';
import { siteSettings } from '../content';
import { phpTrim } from '../html';

export type Plan = { id: string; label: string; installments: number; perPaymentCents: number; [k: string]: unknown };
export type Part = { id: string; label: string; priceCents: number; about?: string; [k: string]: unknown };
export type DonateConfig = {
  currency: string;
  org: { legalName: string; ein: string; contactEmail: string; phone: string; mailingAddress: string[]; overheadNote?: string; [k: string]: unknown };
  impact: Record<string, unknown>;
  custom: { minCents: number; maxCents: number; presetsCents: number[]; [k: string]: unknown };
  maxCenters: number;
  center: { name: string; place: string; priceCents: number; plans: Plan[]; [k: string]: unknown };
  centerParts: Part[];
  legal: { noGoodsOrServices: string; [k: string]: unknown };
  invoiceForOneTimeGifts: boolean;
  [k: string]: unknown;
};

export function donateConfig(): DonateConfig {
  return configJson as unknown as DonateConfig;
}

export async function donateContact(): Promise<{ email: string; phone: string; address: string[] }> {
  const org = donateConfig().org;
  const s = await siteSettings();
  return {
    email: phpTrim(s.contact_email ?? '') || org.contactEmail,
    phone: phpTrim(s.contact_phone ?? '') || org.phone,
    address: org.mailingAddress,
  };
}

/** "$2,400" for whole dollars, "$12.50" otherwise. */
export function donateMoney(cents: number): string {
  const whole = cents % 100 === 0;
  return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
}

/** A setting from the environment (STRIPE_SECRET_KEY etc.). */
export function donateSetting(name: string): string {
  return (process.env[name] ?? '').trim();
}
