/**
 * Body of every public page: header, <main>, footer, JSON-LD, and the site's
 * own scripts (deferred, same files the PHP pages loaded).
 */
import type { ReactNode } from 'react';
import { asset } from '@/lib/assets';
import { nonce } from '@/lib/request';
import { breadcrumbJsonLd, jsonLd, organizationJsonLd, resolveSeo, type SeoInput } from '@/lib/seo';
import SiteHeader from './SiteHeader';
import SiteFooter from './SiteFooter';
import SiteScripts from './SiteScripts';

type Props = {
  children: ReactNode;
  seo?: SeoInput;
  /** Extra stylesheets after styles.css, e.g. ['css/uiverse-cards.css']. */
  styles?: string[];
  /** Scripts before main.js / after main.js, e.g. ['js/map.js']. */
  scriptsBefore?: string[];
  scripts?: string[];
  /** Skip main.js (pages that never loaded it). */
  noMainJs?: boolean;
  tagline?: string;
  mainClassName?: string;
  /** Pages that render their own <main> (e.g. the donate page). */
  bare?: boolean;
  breadcrumbs?: { name: string; url?: string }[];
  /** Content after the footer (modals, lightboxes). */
  afterFooter?: ReactNode;
};

export default async function SitePage({
  children,
  seo = {},
  styles = [],
  scriptsBefore = [],
  scripts = [],
  noMainJs = false,
  tagline,
  mainClassName,
  bare = false,
  breadcrumbs,
  afterFooter,
}: Props) {
  const n = await nonce();
  const resolved = await resolveSeo(seo);
  const ld = await organizationJsonLd(resolved.description);
  const crumbs = breadcrumbs?.length ? await breadcrumbJsonLd(breadcrumbs) : null;
  const allScripts = [...scriptsBefore, ...(noMainJs ? [] : ['js/main.js']), ...scripts];

  return (
    <>
      {styles.map((href) => (
        <link key={href} rel="stylesheet" href={'/' + asset(href)} precedence="page" />
      ))}
      {ld.map((obj, i) => (
        <script key={i} type="application/ld+json" nonce={n} dangerouslySetInnerHTML={{ __html: jsonLd(obj) }} />
      ))}
      {crumbs && <script type="application/ld+json" nonce={n} dangerouslySetInnerHTML={{ __html: jsonLd(crumbs) }} />}
      <SiteHeader tagline={tagline} />
      {bare ? (
        children
      ) : (
        <main id="main-content" className={mainClassName}>
          {children}
        </main>
      )}
      <SiteFooter />
      {afterFooter}
      <SiteScripts srcs={allScripts.map((src) => '/' + asset(src))} nonce={n} />
    </>
  );
}
