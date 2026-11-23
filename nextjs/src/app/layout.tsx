/**
 * Root layout for the public site (the <head> part of includes/layout/head-meta.php).
 * Page-specific <title>, description, canonical, Open Graph etc. come from each
 * page's generateMetadata (src/lib/seo.ts).
 */
import type { Viewport } from 'next';
import type { ReactNode } from 'react';
import { asset } from '@/lib/assets';
import { nonce } from '@/lib/request';

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  viewportFit: 'cover',
  interactiveWidget: 'resizes-content',
};

// (dynamic: pages read headers per request)

export default async function SiteRootLayout({ children }: { children: ReactNode }) {
  const n = await nonce();
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        {/* Marks the document as JS-capable before first paint (see styles.css .reveal). */}
        <script nonce={n} dangerouslySetInnerHTML={{ __html: "document.documentElement.classList.add('js');" }} />
        <base href="/" />
        <link rel="preload" as="font" type="font/woff2" crossOrigin="" href={'/' + asset('assets/fonts/inter-400-latin.woff2')} />
        <link rel="preload" as="font" type="font/woff2" crossOrigin="" href={'/' + asset('assets/fonts/plus-jakarta-sans-700-latin.woff2')} />
        <link rel="stylesheet" href={'/' + asset('css/fonts.css')} />
        <link rel="stylesheet" href={'/' + asset('css/styles.css')} />
      </head>
      <body suppressHydrationWarning>{children}</body>
    </html>
  );
}
