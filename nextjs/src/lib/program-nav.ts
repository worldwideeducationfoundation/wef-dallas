/** Programme back-links and prev/next pager (port of includes/program-nav.php). */
import 'server-only';
import pagesJson from '@/data/pages.json';
import { publishedPrograms } from './content';

type Hub = { href: string; label: string };
export const PROGRAM_HUBS = pagesJson.programHubs as Record<string, Hub>;
export const PROGRAM_JOURNEY = pagesJson.programJourney as string[];
export const PROGRAM_JOURNEY_FALLBACK_NAMES = pagesJson.programJourneyFallbackNames as Record<string, string>;

export function programBackLink(defaultHub: string, from: string | undefined): Hub {
  if (from && PROGRAM_HUBS[from]) return PROGRAM_HUBS[from];
  return PROGRAM_HUBS[defaultHub] ?? PROGRAM_HUBS['active-projects'];
}

export function programHubParam(hub: string): string {
  return PROGRAM_HUBS[hub] ? '?from=' + encodeURIComponent(hub) : '';
}

export async function programPagerLinks(currentSlug: string) {
  const index = PROGRAM_JOURNEY.indexOf(currentSlug);
  const count = PROGRAM_JOURNEY.length;
  if (index === -1 || count === 0) {
    const self = { slug: currentSlug, name: PROGRAM_JOURNEY_FALLBACK_NAMES[currentSlug] ?? '' };
    return { prev: self, next: self };
  }
  const names: Record<string, string> = {};
  for (const row of await publishedPrograms()) names[row.slug] = row.name;
  const resolve = (slug: string) => ({ slug, name: names[slug] ?? PROGRAM_JOURNEY_FALLBACK_NAMES[slug] ?? slug });
  return {
    prev: resolve(PROGRAM_JOURNEY[(index - 1 + count) % count]),
    next: resolve(PROGRAM_JOURNEY[(index + 1) % count]),
  };
}
