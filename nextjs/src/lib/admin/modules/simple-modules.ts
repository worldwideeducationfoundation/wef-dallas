/** The nine list+form modules whose heads differ only in settings (ports of their index.php/form.php heads). */
import 'server-only';
import { dbFetchAll } from '../../db';
import { simpleModule } from './simple';
import * as hero from './bodies/hero-slides';
import * as partners from './bodies/partners';
import * as stories from './bodies/success-stories';
import * as videos from './bodies/video-gallery';
import * as updates from './bodies/updates';
import * as gallery from './bodies/gallery';
import * as reports from './bodies/reports';
import * as mission from './bodies/mission-projects';
import * as milestones from './bodies/impact-milestones';

const base = (sortTitle: Record<string, string>) => ({ default: 'display_order', ...sortTitle, status: 'status', updated: 'updated_at' });

export const heroSlidesRoutes = simpleModule({
  resource: 'hero-slides',
  table: 'hero_slides',
  title: 'Home Slideshow',
  activeNav: 'Home Slideshow',
  sortable: base({ title: 'title_highlight' }),
  search: ['title_lead', 'title_highlight', 'description'],
  select: 'id, title_lead, title_highlight, description, image_path, display_order, status, updated_at',
  tieBreaker: 'title_highlight',
  placeholder: 'Search headline or description…',
  addTitle: 'Add Slide',
  editTitle: 'Edit Slide',
  notFound: 'That slide could not be found.',
  defaults: { title_lead: '', title_highlight: '', description: '', image_alt: '', display_order: 0, status: 'draft' },
  indexBody: hero.indexBody,
  formBody: hero.formBody,
  indexExtra: () => ({ slideTitle: (s: Record<string, any>) => `${s.title_lead} ${s.title_highlight}`.trim() }),
});

export const partnersRoutes = simpleModule({
  resource: 'partners',
  table: 'partners',
  title: 'Partner Logos',
  activeNav: 'Partner Logos',
  sortable: base({ name: 'name' }),
  search: ['name', 'link_url'],
  select: 'id, name, logo_path, link_url, display_order, status, updated_at',
  tieBreaker: 'name',
  placeholder: 'Search partner name…',
  addTitle: 'Add Partner',
  editTitle: 'Edit Partner',
  notFound: 'That partner could not be found.',
  defaults: { name: '', link_url: '', display_order: 0, status: 'draft' },
  indexBody: partners.indexBody,
  formBody: partners.formBody,
});

export const successStoriesRoutes = simpleModule({
  resource: 'success-stories',
  table: 'success_stories',
  title: 'Success Stories',
  activeNav: 'Success Stories',
  sortable: base({ title: 'title' }),
  search: ['title', 'tag'],
  select: 'id, title, tag, story_date, image_path, display_order, status, updated_at',
  tieBreaker: 'title',
  placeholder: 'Search title or tag…',
  addTitle: 'Add Success Story',
  editTitle: 'Edit Success Story',
  notFound: 'That success story could not be found.',
  defaults: { title: '', tag: 'SUCCESS STORIES', story_date: '', link_url: '', display_order: 0, status: 'draft' },
  indexBody: stories.indexBody,
  formBody: stories.formBody,
});

export const videoGalleryRoutes = simpleModule({
  resource: 'video-gallery',
  table: 'video_gallery',
  title: 'Video Gallery',
  activeNav: 'Video Gallery',
  sortable: base({ title: 'title' }),
  search: ['title', 'youtube_url'],
  select: 'id, title, youtube_url, display_order, status, updated_at',
  tieBreaker: 'title',
  placeholder: 'Search title or URL…',
  addTitle: 'Add Video',
  editTitle: 'Edit Video',
  notFound: 'That video could not be found.',
  defaults: { title: '', youtube_url: '', display_order: 0, status: 'draft' },
  indexBody: videos.indexBody,
  formBody: videos.formBody,
});

export const updatesRoutes = simpleModule({
  resource: 'updates',
  table: 'project_updates',
  title: 'Project Updates',
  activeNav: 'Project Updates',
  sortable: base({ title: 'title' }),
  search: ['title', 'content'],
  select: 'id, title, image_path, display_order, status, updated_at',
  tieBreaker: 'title DESC',
  placeholder: 'Search title or content…',
  addTitle: 'Add Project Update',
  editTitle: 'Edit Update',
  notFound: 'That update could not be found.',
  defaults: { title: '', slug: '', content: '', display_order: 0, status: 'draft' },
  indexBody: updates.indexBody,
  formBody: updates.formBody,
});

export const galleryRoutes = simpleModule({
  resource: 'gallery',
  table: 'gallery_items',
  title: 'Gallery',
  activeNav: 'Gallery',
  sortable: base({ caption: 'caption' }),
  search: ['caption'],
  select:
    'id, caption, image_path, display_order, status, updated_at, (SELECT p.name FROM programs p WHERE p.id = gallery_items.program_id AND p.deleted_at IS NULL) AS project_name',
  tieBreaker: 'caption',
  placeholder: 'Search captions…',
  addTitle: 'Add Photo',
  editTitle: 'Edit Photo',
  notFound: 'That photo could not be found.',
  defaults: { caption: '', program_id: null, display_order: 0, status: 'draft' },
  indexBody: gallery.indexBody,
  formBody: gallery.formBody,
  formExtra: async () => ({ projects: await dbFetchAll('SELECT id, name FROM programs WHERE deleted_at IS NULL ORDER BY display_order, name') }),
});

export const reportsRoutes = simpleModule({
  resource: 'reports',
  table: 'annual_reports',
  title: 'Annual Reports',
  activeNav: 'Annual Reports',
  sortable: base({ title: 'title' }),
  search: ['title', 'description'],
  select: 'id, title, cover_image_path, pdf_path, display_order, status, updated_at',
  tieBreaker: 'title DESC',
  placeholder: 'Search title or description…',
  addTitle: 'Add Annual Report',
  editTitle: 'Edit Annual Report',
  notFound: 'That report could not be found.',
  defaults: { title: '', description: '', display_order: 0, status: 'draft' },
  indexBody: reports.indexBody,
  formBody: reports.formBody,
});

export const missionProjectsRoutes = simpleModule({
  resource: 'mission-projects',
  table: 'mission_projects',
  title: 'Mission Projects',
  activeNav: 'Mission Projects',
  sortable: base({ title: 'what_header' }),
  search: ['what_header', 'what_sub'],
  select: 'id, what_header, what_sub, launch_year, display_order, status, updated_at',
  tieBreaker: 'what_header',
  placeholder: 'Search project name…',
  addTitle: 'Add Mission Project',
  editTitle: 'Edit Mission Project',
  notFound: 'That mission project could not be found.',
  defaults: {
    what_header: '',
    what_sub: '',
    launch_year: '',
    where_text: '',
    who_text: '',
    impact_text: '',
    notes_text: '',
    notes_highlight: '',
    display_order: 0,
    status: 'draft',
  },
  indexBody: mission.indexBody,
  formBody: mission.formBody,
});

export const impactMilestonesRoutes = simpleModule({
  resource: 'impact-milestones',
  table: 'impact_milestones',
  title: 'Impact Goals Roadmap',
  activeNav: 'Impact Goals Roadmap',
  sortable: base({ date: 'milestone_date' }),
  search: ['milestone_date'],
  select: 'id, milestone_date, centers_target, students_target, teachers_target, display_order, status, updated_at',
  tieBreaker: 'milestone_date',
  placeholder: 'Search date…',
  addTitle: 'Add Milestone',
  editTitle: 'Edit Milestone',
  notFound: 'That milestone could not be found.',
  defaults: { milestone_date: '', centers_target: 0, students_target: 0, teachers_target: 0, display_order: 0, status: 'draft' },
  indexBody: milestones.indexBody,
  formBody: milestones.formBody,
});
