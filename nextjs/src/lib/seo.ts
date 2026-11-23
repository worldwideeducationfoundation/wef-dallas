/**
 * <head> metadata (port of includes/layout/head-meta.php): title, description,
 * canonical, robots, Open Graph, Twitter, and the JSON-LD blocks.
 */
import 'server-only';
import type { Metadata } from 'next';
import { blockValue, SEO_PAGE_DEFAULTS } from './blocks';
import { phpTrim } from './html';
import { requestPath, siteOrigin } from './request';

export const SITE_NAME = 'Worldwide Education Foundation';
const DEFAULT_DESCRIPTION =
  'Worldwide Education Foundation invests in the potential of children and youth across underserved communities in Central and South Asia.';
const DEFAULT_IMAGE = 'assets/images/programs/model-schools-01.jpg';

export type SeoInput = {
  /** content_blocks page key; when it has SEO defaults, the admin-edited title/description win. */
  page?: string;
  title?: string;
  description?: string;
  image?: string;
  type?: string;
  noIndex?: boolean;
  /** Overrides for og:title / twitter:title when a page set them differently (home). */
  ogTitle?: string;
  twitterTitle?: string;
};

const PAGE_KEY_ALIASES: Record<string, string> = { index: 'home', 'active-projects': 'programs' };

export async function resolveSeo(input: SeoInput) {
  const path = await requestPath();
  const { origin, trustedHost } = await siteOrigin();
  let canonicalPath = path.replace(/\.php$/, '').replace(/\/index$/, '/') || '/';
  const pageKeyRaw = input.page ?? (canonicalPath === '/' ? 'index' : canonicalPath.replace(/^\//, ''));
  const pageKey = PAGE_KEY_ALIASES[pageKeyRaw] ?? pageKeyRaw;
  let title = input.title;
  let description = input.description;
  if (SEO_PAGE_DEFAULTS[pageKey]) {
    title = await blockValue(pageKey, 'seo_title');
    description = await blockValue(pageKey, 'seo_description');
  }
  title = phpTrim(title) !== '' ? title! : SITE_NAME;
  description = phpTrim(description) !== '' ? description! : DEFAULT_DESCRIPTION;
  const image = phpTrim(input.image) !== '' ? input.image! : DEFAULT_IMAGE;
  return {
    title,
    description,
    canonical: origin + canonicalPath,
    imageUrl: `${origin}/${image.replace(/^\/+/, '')}`,
    type: input.type ?? 'website',
    noIndex: Boolean(input.noIndex) || !trustedHost,
    origin,
  };
}

export async function pageMetadata(input: SeoInput): Promise<Metadata> {
  const s = await resolveSeo(input);
  return {
    title: { absolute: s.title },
    description: s.description,
    alternates: { canonical: s.canonical },
    robots: s.noIndex ? { index: false, follow: false } : { index: true, follow: true, 'max-image-preview': 'large' },
    icons: { icon: [{ url: '/assets/images/logo.png', type: 'image/png' }] },
    openGraph: {
      siteName: SITE_NAME,
      type: s.type as 'website',
      url: s.canonical,
      title: input.ogTitle ?? s.title,
      description: s.description,
      images: [s.imageUrl],
      locale: 'en_US',
    },
    twitter: {
      card: 'summary_large_image',
      site: '@WEFglobaledu',
      title: input.twitterTitle ?? s.title,
      description: s.description,
      images: [s.imageUrl],
    },
    other: { referrer: 'strict-origin-when-cross-origin' },
  };
}

export async function organizationJsonLd(description: string) {
  const { origin } = await siteOrigin();
  return [
    {
      '@context': 'https://schema.org',
      '@type': 'NGO',
      name: SITE_NAME,
      alternateName: 'WEF',
      url: origin + '/',
      logo: origin + '/assets/images/logo.png',
      description,
      email: 'info@wefdallas.org',
      telephone: '+1-214-502-8388',
      address: {
        '@type': 'PostalAddress',
        streetAddress: 'W159N4940 Graysland Drive',
        addressLocality: 'Menomonee Falls',
        addressRegion: 'WI',
        postalCode: '53051',
        addressCountry: 'US',
      },
      nonprofitStatus: 'Nonprofit501c3',
      foundingDate: '2021',
      taxID: '81-2135633',
      areaServed: ['Central Asia', 'South Asia'],
      sameAs: [
        'https://www.facebook.com/WorldwideEducationFoundationWEF',
        'https://x.com/WEFglobaledu',
        'https://www.instagram.com/worldwideeducationfoundation/',
        'https://www.linkedin.com/company/worldwide-education-foundation',
        'https://www.youtube.com/@wefdallasorg',
      ],
    },
    {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: SITE_NAME,
      alternateName: 'WEF',
      url: origin + '/',
      publisher: { '@type': 'NGO', name: SITE_NAME },
      inLanguage: 'en',
    },
  ];
}

export async function breadcrumbJsonLd(crumbs: { name: string; url?: string }[]) {
  const { origin } = await siteOrigin();
  const items: Record<string, unknown>[] = [{ '@type': 'ListItem', position: 1, name: 'Home', item: origin + '/' }];
  crumbs.forEach((c, i) => {
    if (!c.name) return;
    const item: Record<string, unknown> = { '@type': 'ListItem', position: i + 2, name: c.name };
    if (c.url) item.item = origin + '/' + c.url.replace(/^\/+/, '');
    items.push(item);
  });
  return { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: items };
}

/** JSON for a <script type="application/ld+json">, with <, >, & escaped like JSON_HEX_TAG|JSON_HEX_AMP. */
export function jsonLd(value: unknown): string {
  return JSON.stringify(value).replace(/</g, '\\u003C').replace(/>/g, '\\u003E').replace(/&/g, '\\u0026');
}
