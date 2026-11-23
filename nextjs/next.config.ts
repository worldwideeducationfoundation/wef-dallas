import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  poweredByHeader: false,
  reactStrictMode: true,
  // sharp and mysql2 are native/server-only; keep them out of the bundle.
  serverExternalPackages: ['sharp', 'mysql2'],
  experimental: {
    // Admin uploads (photos, PDFs) go through server actions / route handlers.
    serverActions: { bodySizeLimit: '25mb' },
  },
  async rewrites() {
    return {
      beforeFiles: [
        // The PHP site's endpoints keep their URLs (forms and Stripe post to them).
        { source: '/api/:name(contact|checkout|stripe-webhook|map-centers|index).php', destination: '/api/:name' },
      ],
      afterFiles: [],
      fallback: [],
    };
  },
  async headers() {
    const longCache = 'public, max-age=31536000, immutable';
    return [
      { source: '/css/:path*', headers: [{ key: 'Cache-Control', value: longCache }] },
      { source: '/js/:path*', headers: [{ key: 'Cache-Control', value: longCache }] },
      { source: '/assets/fonts/:path*', headers: [{ key: 'Cache-Control', value: longCache }] },
      { source: '/assets/images/:path*', headers: [{ key: 'Cache-Control', value: 'public, max-age=15552000' }] },
      { source: '/assets/PDF/:path*', headers: [{ key: 'Content-Disposition', value: 'inline' }] },
    ];
  },
};

export default nextConfig;
