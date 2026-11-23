/** Programme page helpers (port of includes/project-pages.php). */
import 'server-only';
import { blockRegistry, blockValue, renderBodyText } from './blocks';
import { getDb, dbFetchAll, dbFetchOne } from './db';
import { e, phpTrim } from './html';
import { uploadUrl } from './content';

type Parts = { lead: string; highlight: string; tail: string };

const chars = (s: string) => [...s];

/** mb_stripos */
function miStrpos(hay: string, needle: string): number {
  const h = chars(hay.toLowerCase());
  const n = chars(needle.toLowerCase());
  outer: for (let i = 0; i + n.length <= h.length; i++) {
    for (let j = 0; j < n.length; j++) if (h[i + j] !== n[j]) continue outer;
    return i;
  }
  return -1;
}
const mbSub = (s: string, start: number, len?: number) => chars(s).slice(start, len === undefined ? undefined : start + len).join('');

export function splitHighlight(text: string, wanted: string | null = null): Parts {
  text = phpTrim(text);
  if (wanted) {
    const at = miStrpos(text, wanted);
    if (at !== -1) {
      const wl = chars(wanted).length;
      return { lead: mbSub(text, 0, at), highlight: mbSub(text, at, wl), tail: mbSub(text, at + wl) };
    }
  }
  const words = text.split(/\s+/u);
  if (words.length < 2) return { lead: '', highlight: text, tail: '' };
  const take = Math.max(1, Math.ceil(words.length * 0.4));
  return { lead: words.slice(0, -take).join(' ') + ' ', highlight: words.slice(-take).join(' '), tail: '' };
}

export function projectTitleParts(title: string, highlight: string) {
  let kicker = '';
  let sub = '';
  let main = phpTrim(title);
  if (main.includes(':')) {
    const idx = main.indexOf(':');
    const first = phpTrim(main.slice(0, idx));
    const second = phpTrim(main.slice(idx + 1));
    if (second !== '' && miStrpos(second, highlight) !== -1 && miStrpos(first, highlight) === -1) {
      kicker = first + ':';
      main = second;
    } else if (second !== '') {
      main = first + ':';
      sub = second;
    }
  }
  return { kicker, main: splitHighlight(main, highlight), sub };
}

export function highlightHtml(parts: Parts, swoosh = false): string {
  const underline = swoosh
    ? '<svg class="pp-swoosh" viewBox="0 0 300 16" preserveAspectRatio="none" aria-hidden="true"><path d="M3 11 C 60 4, 120 3, 180 7 S 270 12, 297 6" /></svg>'
    : '';
  return e(parts.lead) + '<span class="pp-hl">' + e(parts.highlight) + underline + '</span>' + e(parts.tail);
}

const EYEBROWS: [RegExp, string][] = [
  [/transform|before|after/i, 'See the Change'],
  [/impact|results|outcome/i, 'Our Impact'],
  [/vision|future|ahead|2030|2035/i, 'Looking Ahead'],
  [/who benefits|beneficiar|who we serve/i, 'Who We Serve'],
  [/^why\b/i, 'The Need'],
  [/what we do|approach|how/i, 'Our Work'],
  [/fund|support|donor/i, 'Support'],
  [/partner/i, 'Together'],
  [/objective|goal/i, 'Our Goals'],
  [/track|program|course|diploma/i, 'Programs'],
  [/deliver|model|hybrid/i, 'How It Works'],
  [/women|entrepreneur/i, 'Empowering Women'],
  [/community|parent/i, 'Community'],
  [/technolog|digital/i, 'Technology'],
  [/montessori|learning|classroom/i, 'Learning'],
  [/feature|ecosystem/i, 'What Sets It Apart'],
];

export function projectEyebrow(heading: string, fallback: string): string {
  for (const [re, label] of EYEBROWS) if (re.test(heading)) return label;
  return fallback;
}

export type ProjectSection = { eyebrow: string; heading: string; html: string };

export async function projectSections(slug: string, shortName: string): Promise<ProjectSection[]> {
  const registry = blockRegistry()[slug]?.blocks ?? {};
  let text = '';
  for (const key of ['body_1', 'body_2', 'body_3']) {
    if (registry[key]) text += (await blockValue(slug, key)).replace(/\r\n/g, '\n') + '\n\n';
  }
  const sections: ProjectSection[] = [];
  const chunks = text.split(/^## /m);
  const intro = phpTrim(chunks.shift() ?? '');
  if (intro !== '') sections.push({ eyebrow: shortName, heading: 'About the Project', html: renderBodyText(intro) });
  for (const chunk of chunks) {
    const nl = chunk.indexOf('\n');
    const heading = phpTrim(nl === -1 ? chunk : chunk.slice(0, nl));
    const body = nl === -1 ? '' : chunk.slice(nl + 1);
    const html = renderBodyText(body);
    if (heading === '' && html === '') continue;
    sections.push({ eyebrow: projectEyebrow(heading, shortName), heading, html });
  }
  return sections;
}

export async function projectPhotos(program: Record<string, any> | null): Promise<{ src: string; caption: string }[]> {
  const photos: { src: string; caption: string }[] = [];
  const id = Number(program?.id ?? 0);
  if (!id) return photos;
  try {
    if (!(await getDb())) return photos;
    for (const sql of [
      'SELECT image_path, caption FROM program_photos WHERE program_id = :id ORDER BY display_order, id',
      `SELECT image_path, caption FROM gallery_items
       WHERE program_id = :id AND status = 'active' AND deleted_at IS NULL AND image_path IS NOT NULL
       ORDER BY display_order, caption`,
    ]) {
      for (const row of await dbFetchAll(sql, { id })) {
        const src = uploadUrl(row.image_path);
        if (src !== null && !photos.some((p) => p.src === src)) photos.push({ src, caption: phpTrim(row.caption ?? '') });
      }
    }
  } catch {
    /* fall back to what we have */
  }
  return photos;
}

export function projectCarouselPhotos(cover: string | null, own: { src: string; caption: string }[], projectName: string) {
  const slides: { src: string | null; caption: string }[] = [];
  if (cover !== null) slides.push({ src: cover, caption: projectName });
  for (const p of own) slides.push({ src: p.src, caption: p.caption !== '' ? p.caption : projectName });
  while (slides.length < 3) slides.push({ src: null, caption: 'More photos coming soon' });
  return slides;
}

export async function projectVideo(projectName: string): Promise<{ id: string; title: string } | null> {
  let row: Record<string, any> | null = null;
  try {
    if (!(await getDb())) return null;
    row = await dbFetchOne(
      `SELECT title, youtube_url FROM video_gallery
       WHERE status = 'active' AND deleted_at IS NULL AND LOWER(TRIM(title)) = LOWER(:name)
       ORDER BY display_order, id LIMIT 1`,
      { name: phpTrim(projectName) },
    );
  } catch {
    return null;
  }
  const m = row ? /(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([A-Za-z0-9_-]{6,20})/.exec(String(row.youtube_url)) : null;
  if (!row || !m) return null;
  return { id: m[1], title: String(row.title) };
}
