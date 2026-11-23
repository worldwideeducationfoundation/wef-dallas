# Changelog

The project has no version numbers. Entries are dated, newest first, with the
commit that holds the change. Add one line per finished task.

## 2026-10-19

- **Donate Options (new admin section):** the Donate page's Program dropdown is now edited in the admin (`donate_options`, `admin/modules/donate-options/`): rename, add, hide, reorder, delete to Trash. An option can be linked to a project (follows its name, photo, description and numbers unless overridden; hidden while the project is unpublished). Filled with today's 10 options by `extras/deploy/donate-options-20261019.sql` (also run for live); without the table the page falls back to "Where it's needed most" plus every published project.
- **Donorbox replaces PayPal** on the Donate page: same UI; the button opens the Donorbox form in a dialog with the amount and One-time filled in (`?amount=`, `default_interval=o`), the Donorbox page without JS. Donor wall below the form; meter off until a goal is set (`DONORBOX_SHOW_METER`). CSP `frame-src` allows `https://donorbox.org`. The card on the program photo is frosted glass like the header.
- **Hero:** "Worldwide Education Foundation — Active Projects" label removed (markup, CSS, Page Content field); on phones the text slider now pushes the block to the bottom.
- **Home text:** the Vision / Mission / Belief cards and the home page's other grey body text are black and 1pt larger (scoped to the home sections).
- **Semi Govt Schools:** the map's "Elementary School" centres renamed (database enum, map, filters, admin types, old GeoJSON; `semi-govt-schools-20261019.sql` for live). The government department's name "Elementary and Secondary Education Department" is unchanged.
- **Projects carousel back arrow fixed:** the decorative `.projects::before` glow lay over it and took its clicks (`pointer-events: none`).
- **Project card stats** wrap instead of overflowing the card (the third stat ran 26-38px past the edge around 1100px wide, e.g. a 1366px laptop at 125%).
- **Honor Board redesigned (Apple design principles):** a 5px green border (`--clr-primary-light`, 28px corners) around the board, no other decoration; large sentence-case "Honor Board" (tight tracking) with "Circle of Friends, Chitral Pakistan" as subtitle; white rounded tiles with near-black semibold names and tinted green capsules ("04 Centers"); instant press feedback (0.97) and a gentle hover lift, overriding the section's freeze rule by specificity; tinted Show More; two columns on phones; reduced motion and high contrast handled. Map links and Show More unchanged.
- **Icons:** home Let's Connect emoji replaced by the Contact page's line icons; icons green on both pages; magnifier and warning emoji replaced by line icons.
- **Footer:** tagline beside the logo under the name; logo 84px, as tall as both.
- **Gallery** in WEF at a Glance order (photos, filters, Highlights opening on Bright Beginnings).
- **Less "generated" look:** the thick coloured stripe down the left edge removed from the donate notice (now a tinted box with a fine outline), the Impact Goals milestone cards on phones and the Our Mission vision/mission/belief boxes.
- **Skeleton loading:** photos still loading show a shimmering placeholder (`initImageSkeletons()` in main.js).

## 2026-10-16

- **Home hero shade:** the outgoing photo no longer snaps back from its zoom or pan at each slide change (`main.js` holds its transform for the 1.2s fade). The dark shade over the photos is removed at every width: the `.hero-gradient-overlay` element and its five rules, and the `.hero-glass-panel::after` oval behind the text (with its tablet variant). The text keeps its own text-shadow. Slide changes now zoom slightly: the incoming photo fades in while settling from 106% to full size, the outgoing one drifts to 106% as it fades (`.hero-slide` transform, 1.8s ease-in-out; off with reduced motion). No pale wash mid-change: the outgoing photo stays solid underneath (`.is-leaving`, z-index below `.active`) while the new one fades in; fading both had let up to 25% of the off-white page show through. Tablet and desktop: the copy sits at the foot of the photo about 24px above the dots, parts 12/12/28px apart (was 20/20/36-67px), only the active text slide in flow as on phones; each title on one line (3.35cqi, sized so the longest of the nine fits), the description on one line from 1200px (1.33cqi), the buttons centred. The copy (label, title, description) is centred at every width; the faint quote mark off its left edge is gone. The green typing cursor (it kept blinking beside the finished name) is removed: markup, `.typed-cursor` CSS and its handling in `main.js`; the name still types out. The short green line before the hero label is removed (`.eyebrow-accent-line`, markup and CSS). Removed the older `width: fit-content !important` panel rule that sized the column to its content. A light black gradient rises from the bottom (`.hero-slideshow::after`, .55 at the foot, clear by about 60%).
- **Pages On/Off (new admin section):** a switch per public page (`includes/page-visibility.php`, `admin/modules/page-visibility/`, column `pages.is_enabled`). Off: out of the header menu (an emptied dropdown goes too; About and Impact stay as links to their home sections), every link to it becomes a faded `<span class="link-disabled">` via an output filter started in `bootstrap.php`, and its address answers the site's 404 (`enforcePageVisibility()` in `head-meta.php`). Home cannot be switched off. Live database: import `extras/deploy/page-switches-20261016.sql` (or run `migrate.php`); until then every page stays on.
- **Project pages on phones:** near-square cover photo under the header, with the name, location and badge on it over a light bottom shade; tighter spacing throughout (pages about 20% shorter); colour stand-in panels 2:1; the three stats stay on one line; previous/next project as two cards side by side ("Previous" / "Next" on phones); carousel dots shrink to fit when there are many photos. Desktop unchanged.
- **Label above value, site-wide:** project-page stats, project cards (home, Active Projects), home impact numbers, Completed Projects cards and details sidebar, Impact Goals phone list. Values stay level when a label wraps. Location tag removed from the project cover (it is the first stat; added there if a program's stats lack it). Skills Development stat reworded to "Serving" / "Youth & Job Seekers" (`extras/deploy/stat-labels-20261016.sql` for the live database).
- **Project cards one size:** the home page's equal-height and equal-width card rules now also apply on Active Projects (`.projects-grid.card-carousel-track`, was `#projects` only).
- **Labels top-aligned** in every stat row (cards, project pages, completed projects, impact goals, home impact cards), replacing the bottom alignment from earlier the same day.
- **Project pages:** the "Since" badge moved from the cover into the stats line after Location (a year, not counted up); the green rules under headings removed (markup and CSS); the photo caption on phones is one smaller line with an ellipsis; centred headings and the Support band text smaller on phones; Support band uses the home page's Donate button (`.button`, coin icon, "Donate Now").
- **Project-page stats smaller:** numbers 46 → 35px on desktop, about 23 → 18px on phones; place names on two lines (`.pp-stat--text`, 14ch, balanced); on phones a number keeps its own width and the words wrap, so "52,000" and "$120,000" no longer overflow.
- **Honor Board badge on phones:** "ECD Sponsors" wraps in a phone tile (needs 106px, gets 87-97px); it is now a compact two-line tag (45 → 37px, tighter lines, 10px corners). One-line tags 22px; at 380px and below the side padding is 4px so "04 Centers" stays on one line.
- **Home map on phones:** zoom (side by side), View All Centers and map style in one row along the foot of the map, above the credits; District and Category filters on one row, Sponsor full width below (grid, replaces the stacked rows under 420px). The District pin icon hides under 400px; under 360px the control reads "All Centers" (`.map-see-all-verb` in `map.js`) and the two selects are 15px.
- **Home Project Updates:** full titles (the 3-line clamp and fixed 70px height cut them to "... See the..."); 3-line minimum keeps summaries level. The first card's admin-uploaded photo (`updates/...` path) now goes through `uploadUrl()`; it was requested from the site root and showed its alt text instead.
- **Less white space:** project pages on desktop (section padding 72 → 52px), Our Mission page end (112 → about 48px), Completed Projects card summary.
- **Skills:** `.claude/skills/` updated from `emilkowalski/skills` (13 replaced, `animation-performance` and `break-ui` added).

## 2026-10-14

- **Usability audit fixes (CSS only):** form controls inherit the page font and colour; stray font sizes, text colours and corner radii mapped onto the existing tokens (home page: 8 sizes, 10 colours, 5 radii); newsletter button matches the main pill button; map controls themed; header tagline, footer tagline and Honor Board group headings no longer all-caps. Commit with `git -c core.autocrlf=false` to keep the styles.css diff small (the file has mixed line endings).
- **Donate page:** new `/donate` (program selector, giving options, validation, PayPal continue); header/home buttons and a project-page Support band link to it. Zeffy fallback replaced by PayPal.
- **Agent files:** created `extras/agent/` with the rules and reference files
  for coding agents. Moved `.agents/AGENTS.md` and `extras/docs/` (with
  `PROJECT_STRUCTURE.md`) into it. No code changed.

## 2026-09-30

- `ea34b1c` Deploy: `noindex` on any address other than the real domain (for the
  Hostinger temporary address); the zip build leaves out the pin verifier and
  checks its contents; the Hostinger guide covers the temporary domain.
- `a323834` Media Manager: upload, replace (every reference switches), delete,
  copy link. Map Centers section with a Leaflet pin picker and the 94 centres
  moved into the database. Public map reads the database.
- `d85d889` Admin-uploaded photos: new programme pictures and a replaced team photo.

## 2026-09-29

- `f74c9cf` Team page changes, local pin verifier tool (since left out of the
  upload), Leaflet files and the locations data (commit message: "all done i think").
- `3eb9db6` Admin dashboard crash fixed (CSRF helper loaded in the shared shell);
  admin lists show 100 per page (team members were hidden on page 2); page
  pictures editable in Page Content.
- `853f36d` Before/after sliders on five projects; project videos beside their
  impact sections; carousels that use only the project's own photos; renames.
- `f9bf442` Project pages restyled in the site's colours and fonts; cover photo first.
- `5b5c551` Project pages redesigned; photo sets per project; `/scholarships` removed.
- `328836c` Equal-size project cards; carousel fixes; thick map outline; centred
  impact stats; gallery grouped by project; district filter.

## 2026-09-28

- `60545a0` Repository split into `website/` (the upload) and `extras/` (everything else).

## 2026-09-26

- `edea41b` Removed `'unsafe-inline'` from script sources on the public site and the admin.
- `cc5692d` Security hardening from the audit (findings 2 and 4 to 11).
- `fb96de0`, `cb4beea`, `085e4a5` Page header and Core Purpose banners uploaded
  through the admin, with sized variants.
- `77aa0f4` Admin: grouped sidebar, fits phones, aligned tables, working toggles.
- `8cbe629` Admin: Home Slideshow, Partner Logos, Donate link, SEO titles.
- `c15187d` Removed duplicate and unused CSS from `styles.css`.
- `275b954` Removed the leftover Vercel deployment files.
- `3ca2437` Admin tables filled from the built-in data; survive missing tables.

## 2026-09-22 to 2026-09-25

- Responsive pass across phones, tablets and desktop: header drawer on tablets,
  hero layout, carousels on phones, tap targets of at least 44 px, text floor of
  12 px, hover effects only with a fine pointer, reduced-motion support.
- Responsive audit tool added (`extras/tools/responsive-audit.js`).
- Completed Projects, Success Stories, Video Gallery, Mission Projects and
  Impact Goals Roadmap moved into the database and admin (commits `b0408a7`
  to `2a0d259`).
- `5cf6f1d` List and grid view toggle in the admin.
- `eb83cfa` Fix: the completed projects were silently reading a fallback array
  after a column was dropped.
- `a1687f0` Project context document added (`docs/CONTEXT.md`).
- `d6de0b3` Phase 1 cleanup: duplicate and dead trees removed, Vercel pipeline archived.

## Before 2026-09-22

- `f16a935` (2026-07-30) Full rebuild: PHP site with an admin panel.
- `219faf0`, `df1dd7e` (2026-09-03) Static deployment prepared for Vercel
  (since abandoned for Hostinger).
- `9c9d179` (2026-07-02) First interactive Leaflet map of the early-childhood
  centres in Chitral.
