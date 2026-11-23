/** Every admin URL (relative to /admin/) and its handler. */
import 'server-only';
import type { Routes } from './util';
import { coreRoutes } from './core';
import { homeRoutes } from './home';
import { submissionsRoutes } from './submissions';
import { newsletterRoutes } from './newsletter';
import { mediaRoutes } from './media';
import { pageVisibilityRoutes, pagesRoutes } from './pages';
import { trashRoutes } from './trash';
import { activityRoutes, settingsRoutes, usersRoutes } from './admin-pages';
import { sponsorsRoutes } from './sponsors';
import { teamRoutes } from './team';
import {
  galleryRoutes,
  heroSlidesRoutes,
  impactMilestonesRoutes,
  missionProjectsRoutes,
  partnersRoutes,
  reportsRoutes,
  successStoriesRoutes,
  updatesRoutes,
  videoGalleryRoutes,
} from './simple-modules';
import { completedProjectsRoutes, donateOptionsRoutes, mapCentersRoutes, programsRoutes } from './complex-modules';

function mount(prefix: string, routes: Routes): Routes {
  return Object.fromEntries(Object.entries(routes).map(([k, v]) => [prefix + k, v]));
}

export const ADMIN_ROUTES: Routes = {
  ...coreRoutes,
  ...homeRoutes,
  ...mount('modules/submissions/', submissionsRoutes),
  ...mount('modules/newsletter/', newsletterRoutes),
  ...mount('modules/media/', mediaRoutes),
  ...mount('modules/team/', teamRoutes),
  ...mount('modules/hero-slides/', heroSlidesRoutes),
  ...mount('modules/partners/', partnersRoutes),
  ...mount('modules/success-stories/', successStoriesRoutes),
  ...mount('modules/video-gallery/', videoGalleryRoutes),
  ...mount('modules/updates/', updatesRoutes),
  ...mount('modules/gallery/', galleryRoutes),
  ...mount('modules/reports/', reportsRoutes),
  ...mount('modules/mission-projects/', missionProjectsRoutes),
  ...mount('modules/impact-milestones/', impactMilestonesRoutes),
  ...mount('modules/programs/', programsRoutes),
  ...mount('modules/completed-projects/', completedProjectsRoutes),
  ...mount('modules/map-centers/', mapCentersRoutes),
  ...mount('modules/donate-options/', donateOptionsRoutes),
  ...mount('modules/sponsors/', sponsorsRoutes),
  ...mount('modules/trash/', trashRoutes),
  ...mount('modules/activity/', activityRoutes),
  ...mount('modules/users/', usersRoutes),
  ...mount('modules/settings/', settingsRoutes),
  ...mount('modules/pages/', pagesRoutes),
  ...mount('modules/page-visibility/', pageVisibilityRoutes),
};
