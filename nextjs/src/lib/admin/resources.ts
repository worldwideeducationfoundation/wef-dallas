/**
 * Every editable content type in the admin, in one place. Used by both the
 * admin API (src/app/api/admin/v1) and the admin screens, so the rules can
 * never drift apart. The rules and messages are the ones in each PHP module's
 * save.php; the trash settings come from admin/includes/trash.php.
 */
import 'server-only';
import { dbFetchColumn, dbFetchOne } from '../db';
import { isValidEmail } from '../forms';

export const TEAM_SECTIONS = ['Advisory Board Members', 'Chitral Team', 'Collaborating Partners'];
export const MAP_CENTER_DISTRICTS = ['Upper Chitral', 'Lower Chitral'];
export const MAP_CENTER_MODELS = ['Entrepreneurial Model', 'Government School', 'Private School', 'Semi Govt School'];
export const MAP_CENTER_LAT_RANGE = [34.5, 37.5];
export const MAP_CENTER_LNG_RANGE = [70.5, 74.5];
export const PROGRAM_CATEGORIES = ['early_childhood', 'programs', 'scholarships'];
const YOUTUBE_HOSTS = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];
const SLUG_RE = /^[a-z0-9]+(-[a-z0-9]+)*$/;

export type FieldType = 'text' | 'textarea' | 'int' | 'enum' | 'decimal' | 'fk';
export type Field = {
  name: string;
  label: string;
  type: FieldType;
  /** Empty string becomes NULL (the PHP `?: null`). */
  nullable?: boolean;
  options?: string[];
  default?: unknown;
  max?: number;
  help?: string;
};
export type UploadSpec = {
  field: string;
  column: string;
  folder: string;
  kind: 'image' | 'pdf';
  label: string;
  /** Message when missing on create (required upload). */
  requiredOnCreate?: string;
  removable: boolean;
};
export type Ctx = { id: number | null; isEdit: boolean };
export type Resource = {
  key: string;
  table: string;
  module: string;
  label: string;
  plural: string;
  labelColumn: string;
  fields: Field[];
  uploads: UploadSpec[];
  validate: (input: Record<string, any>, ctx: Ctx) => Promise<string[]>;
  /** Normalise before validation (slug from title etc.). */
  prepare?: (input: Record<string, any>) => Promise<void> | void;
  softDelete: boolean;
  hasStatus: boolean;
  hasOrder: boolean;
  defaultSort: string;
  searchColumns: string[];
  /** Parent filter (e.g. sponsors by group_id). */
  filters?: string[];
  /** Child rows whose files are deleted on purge. */
  childFiles?: { table: string; foreignKey: string; column: string }[];
  createdMessage: string;
  updatedMessage: string;
};

const len = (v: unknown) => [...String(v ?? '')].length;
const status = (input: Record<string, any>, errors: string[]) => {
  if (!['draft', 'active'].includes(input.status)) errors.push('Please choose a valid status.');
};
const isUrl = (v: string) => {
  try {
    const u = new URL(v);
    return Boolean(u.protocol && u.host);
  } catch {
    return false;
  }
};
const hostOf = (v: string) => {
  try {
    return new URL(v).host;
  } catch {
    return null;
  }
};
async function slugTaken(table: string, slug: string, id: number | null): Promise<boolean> {
  return (await dbFetchOne(`SELECT id FROM ${table} WHERE slug = :slug AND id != :id LIMIT 1`, { slug, id: id ?? 0 })) !== null;
}
async function programExists(id: number): Promise<boolean> {
  return Number(await dbFetchColumn('SELECT COUNT(*) FROM programs WHERE id = :id AND deleted_at IS NULL', { id }, 0)) > 0;
}

const STATUS: Field = { name: 'status', label: 'Status', type: 'enum', options: ['draft', 'active'], default: 'draft' };
const ORDER: Field = { name: 'display_order', label: 'Display order', type: 'int', default: 0 };
const stats = (): Field[] =>
  [1, 2, 3].flatMap((i) => [
    { name: `stat${i}_value`, label: `Stat ${i} value`, type: 'text' as const, nullable: true, max: 40 },
    { name: `stat${i}_label`, label: `Stat ${i} label`, type: 'text' as const, nullable: true, max: 60 },
  ]);
const img = (field: string, column: string, folder: string, label: string, removable = true, requiredOnCreate?: string): UploadSpec => ({
  field,
  column,
  folder,
  kind: 'image',
  label,
  removable,
  requiredOnCreate,
});

const base = { softDelete: true, hasStatus: true, hasOrder: true, defaultSort: 'display_order', filters: [] as string[] };

export const RESOURCES: Record<string, Resource> = {
  programs: {
    ...base,
    key: 'programs',
    table: 'programs',
    module: 'programs',
    label: 'Program',
    plural: 'Programs',
    labelColumn: 'name',
    searchColumns: ['name', 'slug', 'summary'],
    childFiles: [{ table: 'program_photos', foreignKey: 'program_id', column: 'image_path' }],
    createdMessage: 'Program created.',
    updatedMessage: 'Program updated.',
    fields: [
      { name: 'slug', label: 'Slug', type: 'text', max: 80 },
      { name: 'category', label: 'Category', type: 'enum', options: PROGRAM_CATEGORIES, default: '' },
      { name: 'name', label: 'Name', type: 'text', max: 160 },
      { name: 'summary', label: 'Summary', type: 'textarea', max: 500 },
      { name: 'description', label: 'Full description', type: 'textarea' },
      { name: 'badge_text', label: 'Badge', type: 'text', nullable: true, max: 40 },
      { name: 'location_text', label: 'Location', type: 'text', nullable: true, max: 120 },
      { name: 'year_launched', label: 'Year launched', type: 'int', nullable: true },
      ...stats(),
      ORDER,
      STATUS,
    ],
    uploads: [
      img('image', 'image_path', 'programs', 'Main photo'),
      img('before_image', 'before_image_path', 'programs', 'Before photo'),
      img('after_image', 'after_image_path', 'programs', 'After photo'),
    ],
    async validate(i, ctx) {
      const e: string[] = [];
      if (i.name === '' || len(i.name) > 160) e.push('Program name is required (max 160 characters).');
      if (i.slug === '' || !SLUG_RE.test(i.slug)) e.push('Slug is required and may only contain lowercase letters, numbers, and hyphens.');
      if (!PROGRAM_CATEGORIES.includes(i.category)) e.push('Please choose a valid category.');
      if (i.summary === '' || len(i.summary) > 500) e.push('Summary is required (max 500 characters).');
      if (i.description === '') e.push('Full description is required.');
      status(i, e);
      if (i.year_launched !== null && (i.year_launched < 1900 || i.year_launched > 2100)) e.push('Year launched must be between 1900 and 2100.');
      if (!e.length && (await slugTaken('programs', i.slug, ctx.id))) e.push('That slug is already in use by another program.');
      return e;
    },
  },

  'completed-projects': {
    ...base,
    key: 'completed-projects',
    table: 'completed_projects',
    module: 'completed-projects',
    label: 'Completed project',
    plural: 'Completed projects',
    labelColumn: 'title',
    searchColumns: ['title', 'slug', 'location', 'category'],
    createdMessage: 'Completed project created.',
    updatedMessage: 'Completed project updated.',
    fields: [
      { name: 'slug', label: 'Slug', type: 'text', max: 120 },
      { name: 'category', label: 'Category', type: 'text', nullable: true, max: 80 },
      { name: 'title', label: 'Title', type: 'text', max: 200 },
      { name: 'summary', label: 'Summary', type: 'textarea', max: 500 },
      { name: 'description', label: 'Full description', type: 'textarea' },
      { name: 'location', label: 'Location', type: 'text', nullable: true, max: 160 },
      { name: 'completion_year', label: 'Completion year', type: 'text', nullable: true, max: 20 },
      { name: 'video_url', label: 'YouTube video URL', type: 'text', nullable: true, max: 255 },
      ...stats(),
      ORDER,
      STATUS,
    ],
    uploads: [
      img('image', 'image_path', 'completed-projects', 'Main photo', false, 'A main photo is required.'),
      img('secondary_image', 'secondary_image_path', 'completed-projects', 'Second photo'),
      img('tertiary_image', 'tertiary_image_path', 'completed-projects', 'Third photo'),
      img('map_image', 'map_image_path', 'completed-projects', 'Map image'),
    ],
    async validate(i, ctx) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 200) e.push('Project title is required (max 200 characters).');
      if (i.slug === '' || !SLUG_RE.test(i.slug)) e.push('Slug is required and may only contain lowercase letters, numbers, and hyphens.');
      if (i.summary === '' || len(i.summary) > 500) e.push('Summary is required (max 500 characters).');
      if (i.description === '') e.push('Full description is required.');
      status(i, e);
      if (i.video_url !== null && (!isUrl(i.video_url) || !YOUTUBE_HOSTS.includes(hostOf(i.video_url) ?? '')))
        e.push('Video URL must be a youtube.com or youtube-nocookie.com link.');
      if (!e.length && (await slugTaken('completed_projects', i.slug, ctx.id))) e.push('That slug is already in use by another completed project.');
      return e;
    },
  },

  team: {
    ...base,
    key: 'team',
    table: 'team_members',
    module: 'team',
    label: 'Team member',
    plural: 'Team',
    labelColumn: 'name',
    searchColumns: ['name', 'role', 'section'],
    filters: ['section'],
    createdMessage: 'Team member added.',
    updatedMessage: 'Team member updated.',
    fields: [
      { name: 'name', label: 'Name', type: 'text', max: 160 },
      { name: 'role', label: 'Role', type: 'text', max: 160 },
      { name: 'section', label: 'Section', type: 'enum', options: TEAM_SECTIONS, default: TEAM_SECTIONS[0] },
      { name: 'bio', label: 'Bio', type: 'textarea', nullable: true },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'team', 'Photo')],
    async validate(i) {
      const e: string[] = [];
      if (i.name === '' || len(i.name) > 160) e.push('Name is required (max 160 characters).');
      if (i.role === '' || len(i.role) > 160) e.push('Role is required (max 160 characters).');
      if (!TEAM_SECTIONS.includes(i.section)) e.push('Please choose a valid section.');
      status(i, e);
      return e;
    },
  },

  gallery: {
    ...base,
    key: 'gallery',
    table: 'gallery_items',
    module: 'gallery',
    label: 'Gallery photo',
    plural: 'Gallery',
    labelColumn: 'caption',
    searchColumns: ['caption'],
    filters: ['program_id'],
    createdMessage: 'Photo added.',
    updatedMessage: 'Photo updated.',
    fields: [
      { name: 'caption', label: 'Caption', type: 'text', max: 160 },
      { name: 'program_id', label: 'Project', type: 'fk', nullable: true, help: 'id from /resources/programs' },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'gallery', 'Photo', true, 'A photo is required.')],
    async validate(i) {
      const e: string[] = [];
      if (i.caption === '' || len(i.caption) > 160) e.push('Caption is required (max 160 characters).');
      status(i, e);
      if (i.program_id !== null && !(await programExists(i.program_id))) e.push('Please choose a valid project.');
      return e;
    },
  },

  'hero-slides': {
    ...base,
    key: 'hero-slides',
    table: 'hero_slides',
    module: 'hero-slides',
    label: 'Hero slide',
    plural: 'Hero slides',
    labelColumn: 'title_highlight',
    searchColumns: ['title_lead', 'title_highlight', 'description'],
    createdMessage: 'Slide created.',
    updatedMessage: 'Slide updated.',
    fields: [
      { name: 'title_lead', label: 'Headline (first part)', type: 'text', max: 120 },
      { name: 'title_highlight', label: 'Headline (highlighted part)', type: 'text', max: 120 },
      { name: 'description', label: 'Description', type: 'textarea', nullable: true, max: 400 },
      { name: 'image_alt', label: 'Photo description (alt text)', type: 'text', nullable: true, max: 200 },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'hero', 'Photo')],
    async validate(i) {
      const e: string[] = [];
      if (i.title_lead === '' || len(i.title_lead) > 120) e.push('The first part of the headline is required (max 120 characters).');
      if (i.title_highlight === '' || len(i.title_highlight) > 120) e.push('The second part of the headline is required (max 120 characters).');
      if (i.description !== null && len(i.description) > 400) e.push('Description must be 400 characters or fewer.');
      status(i, e);
      return e;
    },
  },

  partners: {
    ...base,
    key: 'partners',
    table: 'partners',
    module: 'partners',
    label: 'Partner logo',
    plural: 'Partners',
    labelColumn: 'name',
    searchColumns: ['name', 'link_url'],
    createdMessage: 'Partner created.',
    updatedMessage: 'Partner updated.',
    fields: [{ name: 'name', label: 'Name', type: 'text', max: 200 }, { name: 'link_url', label: 'Website link', type: 'text', nullable: true, max: 255 }, ORDER, STATUS],
    uploads: [img('logo', 'logo_path', 'partners', 'Logo')],
    async validate(i) {
      const e: string[] = [];
      if (i.name === '' || len(i.name) > 200) e.push('Partner name is required (max 200 characters).');
      status(i, e);
      if (i.link_url !== null && (!isUrl(i.link_url) || !/^https?:\/\//i.test(i.link_url))) e.push('Website link must be a full http(s):// address.');
      return e;
    },
  },

  reports: {
    ...base,
    key: 'reports',
    table: 'annual_reports',
    module: 'reports',
    label: 'Annual report',
    plural: 'Annual reports',
    labelColumn: 'title',
    searchColumns: ['title', 'description'],
    createdMessage: 'Report created.',
    updatedMessage: 'Report updated.',
    fields: [{ name: 'title', label: 'Title', type: 'text', max: 160 }, { name: 'description', label: 'Description', type: 'textarea', nullable: true }, ORDER, STATUS],
    uploads: [
      img('cover_image', 'cover_image_path', 'reports', 'Cover image'),
      { field: 'pdf', column: 'pdf_path', folder: 'reports', kind: 'pdf', label: 'Report PDF', removable: false, requiredOnCreate: 'A report PDF is required.' },
    ],
    async validate(i) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 160) e.push('Title is required (max 160 characters).');
      status(i, e);
      return e;
    },
  },

  'success-stories': {
    ...base,
    key: 'success-stories',
    table: 'success_stories',
    module: 'success-stories',
    label: 'Success story',
    plural: 'Success stories',
    labelColumn: 'title',
    searchColumns: ['title', 'tag', 'story_date'],
    createdMessage: 'Success story created.',
    updatedMessage: 'Success story updated.',
    fields: [
      { name: 'title', label: 'Title', type: 'text', max: 200 },
      { name: 'tag', label: 'Tag', type: 'text', nullable: true, max: 40 },
      { name: 'story_date', label: 'Date', type: 'text', nullable: true, max: 40 },
      { name: 'link_url', label: 'Read More link', type: 'text', nullable: true, max: 255 },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'success-stories', 'Photo')],
    async validate(i) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 200) e.push('Title is required (max 200 characters).');
      status(i, e);
      if (i.link_url !== null && !isUrl(i.link_url)) e.push('Read More link must be a valid URL.');
      return e;
    },
  },

  updates: {
    ...base,
    key: 'updates',
    table: 'project_updates',
    module: 'updates',
    label: 'Project update',
    plural: 'Project updates',
    labelColumn: 'title',
    searchColumns: ['title', 'slug', 'content'],
    createdMessage: 'Project Update created.',
    updatedMessage: 'Project Update updated.',
    fields: [
      { name: 'title', label: 'Title', type: 'text', max: 160 },
      { name: 'slug', label: 'Slug', type: 'text', max: 160, help: 'Left empty, it is made from the title.' },
      { name: 'content', label: 'Content', type: 'textarea' },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'updates', 'Photo')],
    prepare(i) {
      if (i.slug === '' && i.title !== '') i.slug = String(i.title).replace(/[^A-Za-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase();
    },
    async validate(i, ctx) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 160) e.push('Title is required (max 160 characters).');
      if (i.slug === '' || len(i.slug) > 160) e.push('Slug is required (max 160 characters).');
      if (i.content === '') e.push('Content is required.');
      status(i, e);
      if (await slugTaken('project_updates', i.slug, ctx.id)) e.push('That slug is already in use by another update.');
      return e;
    },
  },

  'video-gallery': {
    ...base,
    key: 'video-gallery',
    table: 'video_gallery',
    module: 'video-gallery',
    label: 'Video',
    plural: 'Video gallery',
    labelColumn: 'title',
    searchColumns: ['title', 'youtube_url'],
    createdMessage: 'Video created.',
    updatedMessage: 'Video updated.',
    fields: [{ name: 'title', label: 'Title', type: 'text', max: 200 }, { name: 'youtube_url', label: 'YouTube URL', type: 'text', max: 255 }, ORDER, STATUS],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 200) e.push('Title is required (max 200 characters).');
      status(i, e);
      if (i.youtube_url === '' || !isUrl(i.youtube_url) || !YOUTUBE_HOSTS.includes(hostOf(i.youtube_url) ?? ''))
        e.push('YouTube URL is required and must be a youtube.com or youtube-nocookie.com link.');
      return e;
    },
  },

  'mission-projects': {
    ...base,
    key: 'mission-projects',
    table: 'mission_projects',
    module: 'mission-projects',
    label: 'Mission project',
    plural: 'Mission projects (At a Glance)',
    labelColumn: 'what_header',
    searchColumns: ['what_header', 'what_sub'],
    createdMessage: 'Mission project created.',
    updatedMessage: 'Mission project updated.',
    fields: [
      { name: 'what_header', label: 'Project name', type: 'text', max: 200 },
      { name: 'what_sub', label: 'Subtitle', type: 'text', nullable: true, max: 400 },
      { name: 'launch_year', label: 'Launch year', type: 'text', nullable: true, max: 20 },
      { name: 'where_text', label: 'Where', type: 'textarea', nullable: true },
      { name: 'who_text', label: 'Who', type: 'textarea', nullable: true },
      { name: 'impact_text', label: 'Impact (one point per line)', type: 'textarea', nullable: true },
      { name: 'notes_text', label: 'Notes', type: 'textarea', nullable: true },
      { name: 'notes_highlight', label: 'Notes highlight', type: 'textarea', nullable: true },
      ORDER,
      STATUS,
    ],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.what_header === '' || len(i.what_header) > 200) e.push('Project name is required (max 200 characters).');
      status(i, e);
      return e;
    },
  },

  'impact-milestones': {
    ...base,
    key: 'impact-milestones',
    table: 'impact_milestones',
    module: 'impact-milestones',
    label: 'Roadmap milestone',
    plural: 'Impact roadmap',
    labelColumn: 'milestone_date',
    searchColumns: ['milestone_date'],
    createdMessage: 'Milestone created.',
    updatedMessage: 'Milestone updated.',
    fields: [
      { name: 'milestone_date', label: 'Target date', type: 'text', max: 40 },
      { name: 'centers_target', label: 'Centers', type: 'int', default: 0 },
      { name: 'students_target', label: 'Students', type: 'int', default: 0 },
      { name: 'teachers_target', label: 'Teachers', type: 'int', default: 0 },
      ORDER,
      STATUS,
    ],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.milestone_date === '' || len(i.milestone_date) > 40) e.push('Target date is required (max 40 characters).');
      status(i, e);
      if (['centers_target', 'students_target', 'teachers_target'].some((f) => i[f] < 0)) e.push('Targets cannot be negative.');
      return e;
    },
  },

  'map-centers': {
    ...base,
    key: 'map-centers',
    table: 'map_centers',
    module: 'map-centers',
    label: 'Map center',
    plural: 'Map centers',
    labelColumn: 'village',
    searchColumns: ['village', 'sponsor', 'lead_name'],
    filters: ['district', 'center_model'],
    createdMessage: 'Center added.',
    updatedMessage: 'Center updated.',
    fields: [
      { name: 'lead_name', label: 'Center lead', type: 'text', max: 160 },
      { name: 'sponsor', label: 'Sponsor', type: 'text', max: 255 },
      { name: 'village', label: 'Village', type: 'text', max: 255 },
      { name: 'district', label: 'District', type: 'enum', options: MAP_CENTER_DISTRICTS, default: '' },
      { name: 'center_model', label: 'Type', type: 'enum', options: MAP_CENTER_MODELS, default: '' },
      { name: 'latitude', label: 'Latitude', type: 'decimal' },
      { name: 'longitude', label: 'Longitude', type: 'decimal' },
      ORDER,
      { ...STATUS, default: 'active' },
    ],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.village === '' || len(i.village) > 255) e.push('Village is required (max 255 characters).');
      if (i.sponsor === '' || len(i.sponsor) > 255) e.push('Sponsor is required (max 255 characters).');
      if (len(i.lead_name) > 160) e.push('Center lead must be 160 characters or fewer.');
      if (!MAP_CENTER_DISTRICTS.includes(i.district)) e.push('Please choose a district.');
      if (!MAP_CENTER_MODELS.includes(i.center_model)) e.push('Please choose a type.');
      status(i, e);
      const lat = Number(i.latitude);
      const lng = Number(i.longitude);
      if (String(i.latitude).trim() === '' || String(i.longitude).trim() === '' || !Number.isFinite(lat) || !Number.isFinite(lng)) {
        e.push('Latitude and longitude must be numbers, e.g. 35.838713 and 71.791280.');
      } else {
        if (lat < MAP_CENTER_LAT_RANGE[0] || lat > MAP_CENTER_LAT_RANGE[1] || lng < MAP_CENTER_LNG_RANGE[0] || lng > MAP_CENTER_LNG_RANGE[1])
          e.push('Those coordinates are not in Chitral. Check that latitude (about 35-36) and longitude (about 71-73) are not swapped.');
        i.latitude = Math.round(lat * 1e6) / 1e6;
        i.longitude = Math.round(lng * 1e6) / 1e6;
      }
      return e;
    },
  },

  'donate-options': {
    ...base,
    key: 'donate-options',
    table: 'donate_options',
    module: 'donate-options',
    label: 'Donate option',
    plural: 'Donate options',
    labelColumn: 'slug',
    searchColumns: ['label', 'slug', 'summary'],
    createdMessage: 'Option added.',
    updatedMessage: 'Option updated.',
    fields: [
      { name: 'label', label: 'Label', type: 'text', nullable: true, max: 200 },
      { name: 'slug', label: 'Name in the address', type: 'text', max: 100 },
      { name: 'program_id', label: 'Linked project', type: 'fk', nullable: true },
      { name: 'summary', label: 'Description', type: 'textarea', nullable: true, max: 1000 },
      ORDER,
      STATUS,
    ],
    uploads: [img('image', 'image_path', 'donate', 'Photo')],
    prepare(i) {
      i.slug = String(i.slug ?? '').toLowerCase();
    },
    async validate(i, ctx) {
      const e: string[] = [];
      let program: Record<string, any> | null = null;
      if (i.program_id !== null) {
        program = await dbFetchOne('SELECT id, name, slug FROM programs WHERE id = :id AND deleted_at IS NULL LIMIT 1', { id: i.program_id });
        if (program === null) e.push('Please choose a project from the list.');
      }
      if (i.label === null && i.program_id === null) e.push('Give the option a label, or link it to a project.');
      if (i.label !== null && len(i.label) > 200) e.push('The label can be at most 200 characters.');
      if (i.summary !== null && len(i.summary) > 1000) e.push('The description can be at most 1000 characters.');
      status(i, e);
      if (i.slug === '') {
        const b = program !== null ? String(program.slug) : String(i.label ?? '');
        i.slug = b.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      }
      if (i.slug === '' || i.slug.length > 100 || !SLUG_RE.test(i.slug)) {
        e.push('The name in the address may use only lowercase letters, numbers and single dashes.');
      } else {
        const clash = await dbFetchOne('SELECT id, deleted_at FROM donate_options WHERE slug = :slug AND id <> :id LIMIT 1', { slug: i.slug, id: ctx.id ?? 0 });
        if (clash)
          e.push(
            clash.deleted_at !== null
              ? `The name "${i.slug}" belongs to an option in Trash. Restore that one, or choose another name.`
              : `Another option already uses the name "${i.slug}" in the address.`,
          );
      }
      return e;
    },
  },

  'sponsor-groups': {
    ...base,
    key: 'sponsor-groups',
    table: 'sponsor_groups',
    module: 'sponsors',
    label: 'Sponsor group',
    plural: 'Sponsor groups (Honor Board)',
    labelColumn: 'title',
    searchColumns: ['title', 'description'],
    createdMessage: 'Sponsor group created.',
    updatedMessage: 'Sponsor group updated.',
    fields: [{ name: 'title', label: 'Title', type: 'text', max: 255 }, { name: 'description', label: 'Description', type: 'textarea', nullable: true }, ORDER, STATUS],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.title === '' || len(i.title) > 255) e.push('Group title is required (max 255 characters).');
      status(i, e);
      return e;
    },
  },

  sponsors: {
    ...base,
    key: 'sponsors',
    table: 'sponsors',
    module: 'sponsors',
    label: 'Sponsor',
    plural: 'Sponsors (Honor Board)',
    labelColumn: 'name',
    searchColumns: ['name', 'badge_text'],
    filters: ['group_id'],
    createdMessage: 'Sponsor added.',
    updatedMessage: 'Sponsor updated.',
    fields: [
      { name: 'name', label: 'Name', type: 'text', max: 255 },
      { name: 'badge_text', label: 'Badge', type: 'text', nullable: true, max: 60 },
      { name: 'group_id', label: 'Sponsor group', type: 'int', default: 0, help: 'id from /resources/sponsor-groups' },
      ORDER,
      STATUS,
    ],
    uploads: [],
    async validate(i) {
      const e: string[] = [];
      if (i.name === '' || len(i.name) > 255) e.push('Sponsor name is required (max 255 characters).');
      status(i, e);
      if (i.group_id <= 0) e.push('Please choose a sponsor group.');
      else if (!(await dbFetchOne('SELECT id FROM sponsor_groups WHERE id = :id LIMIT 1', { id: i.group_id }))) e.push('That sponsor group no longer exists.');
      return e;
    },
  },

  'program-photos': {
    key: 'program-photos',
    table: 'program_photos',
    module: 'programs',
    label: 'Program photo',
    plural: 'Program photos',
    labelColumn: 'caption',
    searchColumns: ['caption'],
    filters: ['program_id'],
    softDelete: false,
    hasStatus: false,
    hasOrder: true,
    defaultSort: 'display_order',
    createdMessage: 'Photo added.',
    updatedMessage: 'Photo updated.',
    fields: [
      { name: 'program_id', label: 'Program', type: 'int', default: 0 },
      { name: 'caption', label: 'Caption', type: 'text', nullable: true, max: 160 },
      ORDER,
    ],
    uploads: [img('image', 'image_path', 'programs', 'Photo', false, 'A photo is required.')],
    async validate(i) {
      const e: string[] = [];
      if (!(i.program_id > 0) || !(await dbFetchOne('SELECT id FROM programs WHERE id = :id LIMIT 1', { id: i.program_id }))) e.push('Please choose a valid program.');
      if (i.caption !== null && len(i.caption) > 160) e.push('Caption must be 160 characters or fewer.');
      return e;
    },
  },
};

/** Site settings (single row, id = 1), from modules/settings/save.php. */
export const SETTINGS_FIELDS = [
  'contact_email',
  'contact_phone',
  'contact_address',
  'facebook_url',
  'twitter_url',
  'instagram_url',
  'linkedin_url',
  'youtube_url',
  'donate_url',
] as const;

export function validateSettings(i: Record<string, string | null>): string[] {
  const e: string[] = [];
  if (i.contact_email !== null && !isValidEmail(i.contact_email)) e.push('Contact email must be a valid email address.');
  for (const f of ['facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url', 'youtube_url']) {
    if (i[f] !== null && !isUrl(i[f]!)) {
      const label = f.replace('_url', '').replace('twitter', 'X (Twitter)');
      e.push(`${label.charAt(0).toUpperCase() + label.slice(1)} must be a valid URL.`);
    }
  }
  if (i.donate_url !== null && (!isUrl(i.donate_url) || !/^https?:\/\//i.test(i.donate_url))) e.push('Donate link must be a full web address starting with https://.');
  return e;
}

export function getResource(key: string): Resource | null {
  return Object.prototype.hasOwnProperty.call(RESOURCES, key) ? RESOURCES[key] : null;
}
