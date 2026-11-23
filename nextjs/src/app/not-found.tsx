import type { Metadata } from 'next';
import { headers } from 'next/headers';
import NotFoundPage from '@/components/site/NotFoundPage';
import { NOT_FOUND_HEADER } from '@/lib/route-gate';

export const metadata: Metadata = {
  title: { absolute: 'Page not found — Worldwide Education Foundation' },
  description: 'The requested page could not be found.',
  robots: { index: false, follow: true },
};

/** Every 404 lands here (see src/lib/route-gate.ts); the proxy says which text to show. */
export default async function NotFound() {
  if ((await headers()).get(NOT_FOUND_HEADER) === 'project') {
    return <NotFoundPage title="Project not found" backLink="completed-projects" backLabel="Back to Completed Projects" />;
  }
  return <NotFoundPage />;
}
