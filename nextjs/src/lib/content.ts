/**
 * Public-site content readers (port of includes/content.php).
 * Each reads the database and falls back to the built-in content when the
 * database is missing, down, or empty, exactly like the PHP version.
 */
import 'server-only';
import { cache } from 'react';
import staticDataJson from '@/data/static-data.json';
import completedProjectsJson from '@/data/completed-projects.json';
import { tryFetchAll, tryFetchOne } from './db';
import { e, phpTrim } from './html';
import { fileExists, imageVariant } from './assets';
import { uploadsPath } from './paths';

type Row = Record<string, any>;
const staticData = staticDataJson as unknown as Record<string, Row[]>;

export function uploadUrl(relativePath: unknown): string | null {
  if (!relativePath) return null;
  return 'admin/uploads/' + String(relativePath);
}

export function safeYoutubeUrl(url: unknown): string | null {
  const u = phpTrim(url);
  if (u === '') return null;
  let host = '';
  try {
    host = new URL(u).host;
  } catch {
    return null;
  }
  return ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'].includes(host) ? u : null;
}

export function publishedOnly(rows: Row[]): Row[] {
  return rows.filter((r) => (r.status ?? 'active') === 'active' && (r.deleted_at ?? null) === null);
}

export function getStaticData(key: string): Row[] {
  return (staticData[key] ?? []) as Row[];
}

const nonEmptyRows = (rows: Row[] | null): rows is Row[] => Array.isArray(rows) && rows.length > 0;

export const publishedTeamBySection = cache(async (sectionOrder: string[]): Promise<Record<string, Row[]>> => {
  let rows = await tryFetchAll(
    `SELECT name, role, section, bio, image_path FROM team_members
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, name`,
  );
  if (!nonEmptyRows(rows)) rows = publishedOnly(getStaticData('team_members'));
  const grouped: Record<string, Row[]> = Object.fromEntries(sectionOrder.map((s) => [s, []]));
  for (const m of rows) {
    const s = m.section ?? '';
    if (s in grouped) grouped[s].push(m);
  }
  return Object.fromEntries(Object.entries(grouped).filter(([, v]) => v.length > 0));
});

export const publishedGalleryItems = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT g.caption, g.image_path, p.name AS project_name
     FROM gallery_items g
     LEFT JOIN programs p ON p.id = g.program_id AND p.status = 'active' AND p.deleted_at IS NULL
     WHERE g.status = 'active' AND g.deleted_at IS NULL AND g.image_path IS NOT NULL
     ORDER BY g.display_order, g.caption`,
  );
  if (nonEmptyRows(rows)) return rows;
  return publishedOnly(getStaticData('gallery_items'));
});

export type GalleryPhoto = { caption: string; image_path: string; project: string };

export const galleryPhotos = cache(async (): Promise<GalleryPhoto[]> => {
  const photos: GalleryPhoto[] = [];
  const seen = new Set<string>();
  const projectsWithPhotos = new Set<string>();
  for (const item of await publishedGalleryItems()) {
    const project = phpTrim(item.project_name ?? '') || 'General';
    photos.push({ caption: String(item.caption ?? ''), image_path: String(item.image_path), project });
    seen.add(item.image_path);
    projectsWithPhotos.add(project);
  }
  for (const program of await publishedPrograms()) {
    const p = String(program.image_path ?? '');
    const name = String(program.name);
    if (p === '' || seen.has(p) || projectsWithPhotos.has(name)) continue;
    photos.push({ caption: name, image_path: p, project: name });
    seen.add(p);
  }
  const rank = new Map<string, number>();
  (await publishedMissionProjects()).forEach((row, i) => rank.set(phpTrim(row.what_header), i));
  const firstSeen = new Map<string, number>();
  photos.forEach((ph, i) => {
    if (!firstSeen.has(ph.project)) firstSeen.set(ph.project, i);
  });
  const key = (ph: GalleryPhoto): [number, number] =>
    ph.project === 'General' ? [2, 0] : rank.has(ph.project) ? [0, rank.get(ph.project)!] : [1, firstSeen.get(ph.project)!];
  return photos
    .map((ph, i) => ({ k: key(ph), i, ph }))
    .sort((a, b) => a.k[0] - b.k[0] || a.k[1] - b.k[1] || a.i - b.i)
    .map((x) => x.ph);
});

export const publishedReports = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT title, description, cover_image_path, pdf_path FROM annual_reports
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title DESC`,
  );
  if (nonEmptyRows(rows)) return rows;
  return publishedOnly(getStaticData('annual_reports'));
});

const PROGRAM_COLUMNS = `slug, category, name, summary, description, badge_text, location_text, year_launched,
  stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label, image_path`;

export const publishedPrograms = cache(async (category: string | null = null): Promise<Row[]> => {
  let sql = `SELECT ${PROGRAM_COLUMNS} FROM programs WHERE status = 'active' AND deleted_at IS NULL`;
  if (category !== null) sql += ' AND category = :category';
  sql += ' ORDER BY category, display_order, name';
  const rows = await tryFetchAll(sql, category !== null ? { category } : {});
  if (nonEmptyRows(rows)) return rows;
  let all = publishedOnly(getStaticData('programs'));
  if (category !== null) all = all.filter((p) => (p.category ?? '') === category);
  const categoryRank: Record<string, number> = { early_childhood: 0, programs: 1, scholarships: 2 };
  return [...all].sort((a, b) => {
    const ra = categoryRank[a.category ?? ''] ?? 99;
    const rb = categoryRank[b.category ?? ''] ?? 99;
    return (
      ra - rb ||
      (a.display_order ?? 0) - (b.display_order ?? 0) ||
      (String(a.name ?? '') < String(b.name ?? '') ? -1 : String(a.name ?? '') > String(b.name ?? '') ? 1 : 0)
    );
  });
});

export const publishedProgramBySlug = cache(async (slug: string): Promise<Row | null> => {
  const row = await tryFetchOne(
    `SELECT id, ${PROGRAM_COLUMNS}, before_image_path, after_image_path FROM programs
     WHERE slug = :slug AND status = 'active' AND deleted_at IS NULL LIMIT 1`,
    { slug },
  );
  if (row) return row;
  return publishedOnly(getStaticData('programs')).find((p) => (p.slug ?? null) === slug) ?? null;
});

export function programCompareSlider(program: Row | null, placeholders = false): string {
  let before = uploadUrl(program?.before_image_path ?? null);
  let after = uploadUrl(program?.after_image_path ?? null);
  if (placeholders) {
    before ??= 'assets/images/placeholders/before-coming-soon.svg';
    after ??= 'assets/images/placeholders/after-coming-soon.svg';
  }
  if (before === null || after === null) return '';
  const name = String(program?.name ?? '');
  return (
    '<div class="compare-slider"' +
    ` data-before="${e(before)}" data-before-label="Before" data-before-alt="${e(phpTrim(name + ' before WEF'))}"` +
    ` data-after="${e(after)}" data-after-label="After" data-after-alt="${e(phpTrim(name + ' after WEF'))}"></div>`
  );
}

const STAT_ICONS: Record<string, string> = {
  Location: 'Location',
  Students: 'Students',
  Children: 'Students',
  Trained: 'Students',
  Centers: 'Schools',
  Schools: 'Schools',
  Labs: 'Lab',
  Focus: 'Research',
  Recipients: 'Scholarship',
  Invested: 'Investement',
  '& Job Seekers': 'Youth',
  Serving: 'Youth',
  'By 2030': 'Diploma',
  Programs: 'Diploma',
};

export function statIcon(label: unknown): string {
  const icon = STAT_ICONS[phpTrim(label)] ?? 'Diploma';
  return `assets/icons/SVG/Cart%20Icons/${icon}.svg`;
}

function attachSponsors(groups: Row[], sponsors: Row[]): Row[] {
  const byGroup = new Map<string, Row[]>();
  for (const s of sponsors) {
    const k = String(s.group_id);
    byGroup.set(k, [...(byGroup.get(k) ?? []), s]);
  }
  return groups.map((g) => ({ ...g, sponsors: byGroup.get(String(g.id)) ?? [] }));
}

export const publishedSponsorGroups = cache(async (): Promise<Row[]> => {
  const groups = await tryFetchAll(
    `SELECT id, title, description FROM sponsor_groups
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title`,
  );
  if (nonEmptyRows(groups)) {
    const sponsors =
      (await tryFetchAll(
        `SELECT group_id, name, badge_text FROM sponsors
         WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, name`,
      )) ?? [];
    return attachSponsors(groups, sponsors);
  }
  return attachSponsors(publishedOnly(getStaticData('sponsor_groups')), publishedOnly(getStaticData('sponsors')));
});

export const publishedUpdates = cache(async (limit = 0): Promise<Row[]> => {
  let sql = `SELECT title, slug, content, tag, link_url, image_path FROM project_updates
             WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title`;
  if (limit > 0) sql += ' LIMIT ' + Math.trunc(limit);
  const rows = await tryFetchAll(sql);
  if (nonEmptyRows(rows)) return rows;
  const fallback = publishedOnly(getStaticData('project_updates'));
  return limit > 0 ? fallback.slice(0, limit) : fallback;
});

const COMPLETED_COLUMNS = `slug, title, summary, description, location, completion_year, category,
  image_path, secondary_image_path, tertiary_image_path, map_image_path, video_url,
  stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label`;

export const publishedCompletedProjects = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT ${COMPLETED_COLUMNS} FROM completed_projects
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title`,
  );
  if (nonEmptyRows(rows)) return rows;
  return completedProjectsJson as Row[];
});

export const publishedCompletedProjectBySlug = cache(async (slug: string): Promise<Row | null> => {
  const row = await tryFetchOne(
    `SELECT ${COMPLETED_COLUMNS} FROM completed_projects
     WHERE slug = :slug AND status = 'active' AND deleted_at IS NULL LIMIT 1`,
    { slug },
  );
  if (row) return row;
  return (completedProjectsJson as Row[]).find((p) => p.slug === slug) ?? null;
});

export const publishedHeroSlides = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT id, title_lead, title_highlight, description, image_path, image_alt FROM hero_slides
     WHERE status = 'active' AND deleted_at IS NULL AND image_path IS NOT NULL ORDER BY display_order, id`,
  );
  if (nonEmptyRows(rows)) return rows.map((r) => ({ ...r, image_src: uploadUrl(r.image_path) }));
  return publishedOnly(getStaticData('hero_slides'));
});

export const publishedPartners = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT id, name, logo_path, link_url FROM partners
     WHERE status = 'active' AND deleted_at IS NULL AND logo_path IS NOT NULL ORDER BY display_order, id`,
  );
  if (nonEmptyRows(rows)) return rows.map((r) => ({ ...r, logo_src: uploadUrl(r.logo_path) }));
  return publishedOnly(getStaticData('partners'));
});

export const publishedSuccessStories = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT id, title, tag, story_date, image_path, link_url FROM success_stories
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title`,
  );
  if (nonEmptyRows(rows)) return rows;
  return publishedOnly(getStaticData('success_stories'));
});

export const publishedVideos = cache(async (limit = 0): Promise<Row[]> => {
  let sql = `SELECT id, title, youtube_url FROM video_gallery
             WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, title`;
  if (limit > 0) sql += ' LIMIT ' + Math.trunc(limit);
  const rows = await tryFetchAll(sql);
  if (nonEmptyRows(rows)) return rows;
  const fallback = publishedOnly(getStaticData('video_gallery'));
  return limit > 0 ? fallback.slice(0, limit) : fallback;
});

export const publishedMissionProjects = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT id, what_header, what_sub, launch_year, where_text, who_text, impact_text, notes_text, notes_highlight
     FROM mission_projects WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, what_header`,
  );
  if (nonEmptyRows(rows)) return rows;
  return publishedOnly(getStaticData('mission_projects'));
});

export const publishedImpactMilestones = cache(async (): Promise<Row[]> => {
  const rows = await tryFetchAll(
    `SELECT id, milestone_date, centers_target, students_target, teachers_target FROM impact_milestones
     WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, milestone_date`,
  );
  if (nonEmptyRows(rows)) return rows;
  return publishedOnly(getStaticData('impact_milestones'));
});

export function missionBulletList(text: unknown): string {
  const lines = String(text ?? '')
    .split('\n')
    .map((l) => phpTrim(l))
    .filter((l) => l !== '');
  if (!lines.length) return '';
  return '<ul class="pdf-ul-list">' + lines.map((l) => '<li>' + e(l) + '</li>').join('') + '</ul>';
}

export const PAGE_HEADER_IMAGE_PAGES = [
  'programs',
  'completed-projects',
  'our-team',
  'gallery',
  'video-gallery',
  'success-stories',
  'annual-reports',
  'contact',
  'volunteer',
  'home',
];

const pageHeaderImages = cache(async (): Promise<Record<string, string>> => {
  const rows = await tryFetchAll<{ page_key: string; header_image: string }>(
    'SELECT page_key, header_image FROM pages WHERE header_image IS NOT NULL',
  );
  return Object.fromEntries((rows ?? []).map((r) => [r.page_key, r.header_image]));
});

export async function pageHeaderImage(page: string): Promise<string | null> {
  if (!PAGE_HEADER_IMAGE_PAGES.includes(page)) return null;
  const p = String((await pageHeaderImages())[page] ?? '');
  if (!/^[a-z0-9_-]+\/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$/.test(p)) return null;
  if (!fileExists(uploadsPath(p))) return null;
  return uploadUrl(p);
}

export type PageHeroInfo = { className: string; style: string; hasImage: boolean };

/** class/style for a .page-hero, with the admin-set header photo if any (port of pageHero()). */
export async function pageHero(page: string, existingStyle = ''): Promise<PageHeroInfo> {
  const image = await pageHeaderImage(page);
  let style = phpTrim(existingStyle);
  if (image === null) return { className: '', style, hasImage: false };
  const large = imageVariant(image, [1600, 1200]) ?? image;
  const small = imageVariant(image, [768, 1200]) ?? large;
  style = phpTrim(`${style} --bg-image: url('/${large}'); --bg-image-sm: url('/${small}');`);
  return { className: ' has-bg-image', style, hasImage: true };
}

export const siteSettings = cache(async (): Promise<Row> => {
  const row = await tryFetchOne('SELECT * FROM site_settings WHERE id = 1');
  if (row) return row;
  return (
    getStaticData('site_settings')[0] ?? {
      contact_address: 'W159N4940 Graysland Drive, Menomonee Falls, WI 53051 United States',
      contact_phone: '+1 (214) 502-8388',
      contact_email: 'info@wefdallas.org',
      facebook_url: 'https://www.facebook.com/WorldwideEducationFoundationWEF',
      twitter_url: 'https://x.com/WEFglobaledu',
      instagram_url: 'https://www.instagram.com/worldwideeducationfoundation/',
      linkedin_url: 'https://www.linkedin.com/company/worldwide-education-foundation',
      youtube_url: 'https://www.youtube.com/@wefdallasorg',
    }
  );
});

export async function donateUrl(): Promise<string> {
  const url = phpTrim((await siteSettings()).donate_url ?? '');
  if (url !== '' && /^https?:\/\//i.test(url)) {
    try {
      new URL(url);
      return url;
    } catch {
      /* fall through */
    }
  }
  return 'https://donorbox.org/bright-beginnings-972649';
}
