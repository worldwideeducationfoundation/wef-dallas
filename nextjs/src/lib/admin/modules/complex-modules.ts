/** Programs, Completed Projects, Map Centers and Donate Options (ports of their index.php/form.php heads + programs/_photos.php). */
import 'server-only';
import { dbExecute, dbFetchAll, dbFetchColumn } from '../../db';
import { logActivity } from '../activity';
import type { Ctx } from '../context';
import { MAP_CENTER_DISTRICTS, MAP_CENTER_MODELS } from '../resources';
import { flashSet } from '../session';
import { deleteUploadedFile, handleUpload } from '../uploads';
import { STATUS_FILTER, simpleModule } from './simple';
import * as programs from './bodies/programs';
import * as completed from './bodies/completed-projects';
import * as centers from './bodies/map-centers';
import * as donate from './bodies/donate-options';

const CATEGORY_LABELS = { early_childhood: 'Early Childhood', programs: 'Programs', scholarships: 'Scholarships' };

/** saveProgramPhotos(): captions, order, replace, remove, and new uploads (max 12 per save). */
async function saveProgramPhotos(c: Ctx, programId: number): Promise<{ added: number; errors: string[] }> {
  const errors: string[] = [];
  let added = 0;
  const owned = new Map<number, string>(
    (await dbFetchAll('SELECT id, image_path FROM program_photos WHERE program_id = :pid', { pid: programId })).map((r) => [Number(r.id), String(r.image_path)]),
  );
  // photos[<id>][caption|order|remove]
  const fields = new Map<number, Record<string, string>>();
  for (const [k, v] of Object.entries(c.post)) {
    const m = /^photos\[(\d+)\]\[(\w+)\]$/.exec(k);
    if (m) fields.set(Number(m[1]), { ...(fields.get(Number(m[1])) ?? {}), [m[2]]: v });
  }
  for (const [photoId, f] of fields) {
    if (!owned.has(photoId)) continue;
    if (f.remove) {
      await dbExecute('DELETE FROM program_photos WHERE id = :id AND program_id = :pid', { id: photoId, pid: programId });
      deleteUploadedFile(owned.get(photoId)!);
      continue;
    }
    let path = owned.get(photoId)!;
    const replacement = c.files['photo_replace_' + photoId];
    if (replacement) {
      const r = await handleUpload(replacement, 'programs', 'image', path);
      if (r.ok && r.path !== null) path = r.path;
      else if (!r.ok) errors.push('Photo not replaced: ' + r.error);
    }
    const caption = [...(f.caption ?? '').trim()].slice(0, 160).join('');
    await dbExecute('UPDATE program_photos SET caption = :caption, display_order = :ord, image_path = :path WHERE id = :id AND program_id = :pid', {
      caption: caption === '' ? null : caption,
      ord: Math.trunc(Number(f.order ?? 0)) || 0,
      path,
      id: photoId,
      pid: programId,
    });
  }
  const fresh = c.fileLists['new_photos[]'] ?? [];
  if (fresh.length) {
    let nextOrder = Number(await dbFetchColumn('SELECT COALESCE(MAX(display_order), 0) FROM program_photos WHERE program_id = :pid', { pid: programId }, 0));
    for (const file of fresh) {
      if (added >= 12) {
        errors.push('Only 12 photos can be added per save; save again for the rest.');
        break;
      }
      const up = await handleUpload(file, 'programs', 'image', null);
      if (!up.ok || up.path === null) {
        errors.push(`${file.name ?? 'photo'}: ${up.ok ? 'not saved' : up.error}`);
        continue;
      }
      nextOrder += 10;
      await dbExecute('INSERT INTO program_photos (program_id, image_path, display_order) VALUES (:pid, :path, :ord)', { pid: programId, path: up.path, ord: nextOrder });
      added++;
    }
  }
  return { added, errors };
}

export const programsRoutes = simpleModule({
  resource: 'programs',
  table: 'programs',
  title: 'Programs',
  activeNav: 'Programs',
  sortable: { default: 'category', name: 'name', category: 'category', status: 'status', updated: 'updated_at' },
  search: ['name', 'slug', 'summary'],
  select: 'id, slug, category, name, image_path, display_order, status, updated_at',
  tieBreaker: (p) => (p.sort === 'default' ? 'display_order, name' : 'id'),
  filterKeys: ['status', 'category'],
  filterColumns: { status: 'status', category: 'category' },
  toolbarFilters: async () => ({ ...STATUS_FILTER, category: { label: 'Categories', options: CATEGORY_LABELS } }),
  reorderScope: ['category'],
  dupeGroup: 'category',
  placeholder: 'Search name, slug, or summary…',
  addTitle: 'Add Program',
  editTitle: 'Edit Program',
  notFound: 'That program could not be found.',
  defaults: {
    slug: '', category: 'programs', name: '', summary: '', description: '', badge_text: '', location_text: '', year_launched: '',
    stat1_value: '', stat1_label: '', stat2_value: '', stat2_label: '', stat3_value: '', stat3_label: '', display_order: 0, status: 'draft',
  },
  indexBody: programs.indexBody,
  formBody: programs.formBody,
  indexExtra: () => ({ categoryLabels: CATEGORY_LABELS }),
  formExtra: async (ctx) => {
    const id = ctx.query.has('id') ? Math.trunc(Number(ctx.query.get('id'))) || 0 : null;
    let projectPhotos: Record<string, any>[] = [];
    if (id !== null) {
      try {
        projectPhotos = await dbFetchAll('SELECT id, image_path, caption, display_order FROM program_photos WHERE program_id = :id ORDER BY display_order, id', { id });
      } catch {
        projectPhotos = [];
      }
    }
    return { categories: CATEGORY_LABELS, projectPhotos };
  },
  afterSave: async (c, programId, isEdit) => {
    const { added, errors } = await saveProgramPhotos(c, programId);
    const name = String(c.post.name ?? '').trim();
    if (added > 0) await logActivity(c.user.id, 'update', 'programs', `${name}: ${added} photo(s) added`, c.meta);
    const saved = isEdit ? 'Program updated.' : 'Program created.';
    if (errors.length) flashSet(c.session, 'error', saved + ' ' + errors.join(' '));
    else if (added > 0) flashSet(c.session, 'success', `${saved} ${added} photo${added === 1 ? '' : 's'} added - add captions below if you like.`);
    return added > 0 || errors.length ? `form.php?id=${programId}` : 'index.php';
  },
});

export const completedProjectsRoutes = simpleModule({
  resource: 'completed-projects',
  table: 'completed_projects',
  title: 'Completed Projects',
  activeNav: 'Completed Projects',
  sortable: { default: 'category', title: 'title', category: 'category', status: 'status', updated: 'updated_at' },
  search: ['title', 'slug', 'summary', 'location'],
  select: 'id, slug, category, title, image_path, completion_year, display_order, status, updated_at',
  tieBreaker: (p) => (p.sort === 'default' ? 'display_order, title' : 'id'),
  filterKeys: ['status', 'category'],
  filterColumns: { status: 'status', category: 'category' },
  toolbarFilters: async () => {
    const cats = (await dbFetchAll(
      "SELECT DISTINCT category FROM completed_projects WHERE category IS NOT NULL AND category != '' ORDER BY category",
    )).map((r) => String(r.category));
    return { ...STATUS_FILTER, category: { label: 'Categories', options: Object.fromEntries(cats.map((c) => [c, c])) } };
  },
  reorderScope: ['category'],
  dupeGroup: 'category',
  placeholder: 'Search title, slug, summary, or location…',
  addTitle: 'Add Completed Project',
  editTitle: 'Edit Completed Project',
  notFound: 'That completed project could not be found.',
  defaults: {
    slug: '', category: '', title: '', summary: '', description: '', location: '', completion_year: '', video_url: '',
    stat1_value: '', stat1_label: '', stat2_value: '', stat2_label: '', stat3_value: '', stat3_label: '', display_order: 0, status: 'draft',
  },
  indexBody: completed.indexBody,
  formBody: completed.formBody,
});

export const mapCentersRoutes = simpleModule({
  resource: 'map-centers',
  table: 'map_centers',
  title: 'Map Centers',
  activeNav: 'Map Centers',
  sortable: { default: 'display_order', village: 'village', district: 'district', model: 'center_model', sponsor: 'sponsor', status: 'status' },
  search: ['village', 'sponsor', 'lead_name'],
  select: 'id, lead_name, sponsor, village, district, center_model, latitude, longitude, display_order, status',
  tieBreaker: 'village',
  filterKeys: ['status', 'district', 'center_model'],
  filterColumns: { status: 'status', district: 'district', center_model: 'center_model' },
  toolbarFilters: async () => ({
    ...STATUS_FILTER,
    district: { label: 'Districts', options: Object.fromEntries(MAP_CENTER_DISTRICTS.map((x) => [x, x])) },
    center_model: { label: 'Types', options: Object.fromEntries(MAP_CENTER_MODELS.map((x) => [x, x])) },
  }),
  placeholder: 'Search village, sponsor or center lead…',
  addTitle: 'Add Center',
  editTitle: 'Edit Center',
  notFound: 'That center could not be found.',
  defaults: async () => ({
    lead_name: '', sponsor: '', village: '', district: 'Lower Chitral', center_model: 'Entrepreneurial Model', latitude: '', longitude: '',
    display_order: Number(await dbFetchColumn('SELECT COALESCE(MAX(display_order), 0) + 1 FROM map_centers WHERE deleted_at IS NULL', [], 1)),
    status: 'active',
  }),
  indexBody: centers.indexBody,
  formBody: centers.formBody,
  formExtra: async () => ({
    sponsorNames: (await dbFetchAll('SELECT DISTINCT sponsor FROM map_centers WHERE deleted_at IS NULL ORDER BY sponsor')).map((r) => String(r.sponsor)),
    districts: MAP_CENTER_DISTRICTS,
    models: MAP_CENTER_MODELS,
  }),
  formStyles: ['assets/vendor/leaflet/leaflet.css'],
  formScripts: ['assets/vendor/leaflet/leaflet.js', 'assets/js/map-picker.js'],
});

export const donateOptionsRoutes = simpleModule({
  resource: 'donate-options',
  table: 'donate_options',
  title: 'Donate Options',
  activeNav: 'Donate Options',
  sortable: { default: 'display_order', label: 'label', status: 'status', updated: 'updated_at' },
  search: ['label', 'slug', 'summary'],
  select: 'id, label, slug, program_id, summary, image_path, display_order, status, updated_at',
  tieBreaker: 'slug',
  placeholder: 'Search options…',
  addTitle: 'Add Donate Option',
  editTitle: 'Edit Donate Option',
  notFound: 'That option could not be found.',
  defaults: { label: '', slug: '', program_id: null, summary: '', display_order: 0, status: 'active' },
  indexBody: donate.indexBody,
  formBody: donate.formBody,
  indexExtra: async () => {
    const rows = await dbFetchAll('SELECT id, name, image_path, status, deleted_at FROM programs');
    const progs: Record<string, any> = Object.fromEntries(rows.map((r) => [String(r.id), r]));
    return {
      programs: progs,
      optionName: (o: Record<string, any>) => {
        const label = String(o.label ?? '').trim();
        if (label !== '') return label;
        const p = o.program_id !== null ? progs[String(o.program_id)] : undefined;
        return p ? String(p.name) : '(no label)';
      },
      projectNote: (o: Record<string, any>) => {
        if (o.program_id === null) return '';
        const p = progs[String(o.program_id)];
        if (!p || p.deleted_at !== null) return 'Its project was deleted, so it is not shown.';
        return p.status !== 'active' ? 'Its project is unpublished, so it is not shown.' : '';
      },
    };
  },
  formExtra: async () => ({ programs: await dbFetchAll('SELECT id, name, slug, status FROM programs WHERE deleted_at IS NULL ORDER BY display_order, name') }),
});
