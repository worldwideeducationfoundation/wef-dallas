# PHP → Next.js port: status

The goal is a 1:1 clone of `website/` (public site + admin) in Next.js, using the
**same MySQL database and schema**, the **same CSS/JS/images**, and the **same
URLs**. When everything below is ticked and checked side by side, `website/`
can be retired.

## How the port is built

- **Same look:** `public/css`, `public/js`, `public/assets`, `public/admin/assets` are
  copied unchanged from `website/public`. Pages render the same HTML markup, so the same
  stylesheets and scripts work.
- **Same data:** `src/lib/db.ts` talks to the same MySQL tables. With no database
  (`DB_HOST` empty), the public site uses the built-in fallback content, as the PHP
  site does.
- **Same defaults:** `scripts/export-php-data.php` exports the PHP site's content-block
  registry, SEO defaults, static fallback data, completed projects, programme-page
  settings and donate config to `src/data/*.json`.
- **Uploads:** admin uploads live in `uploads/` (or `UPLOADS_DIR`). They are served at
  `/admin/uploads/...` by `src/app/admin/uploads/[...path]/route.ts`, and survive
  redeploys.
- **Site scripts:** `js/main.js` and the other site scripts are loaded *after* React
  hydrates (`components/site/SiteScripts.tsx`). If they ran earlier, React would undo
  their changes (count-ups, sliders).
- **Old URLs:** `x.php` redirects to `x` with a 301, and `/api/*.php` keeps working
  through a rewrite. `.htaccess` rules and security headers are in `src/proxy.ts`.

## Public site

Pages with an inline <style> in PHP have it moved verbatim to `public/css/pages/<page>.css`.

| PHP page | Next.js | Status |
|---|---|---|
| layout: head-meta, site-header, footer-full | `app/(site)/layout.tsx`, `components/site/*` | done |
| 404 / not-found (404.php) | `app/not-found.tsx`, `lib/route-gate.ts` (proxy decides 404s so the page is server-rendered) | done |
| 9 programme pages (layout/project-page.php) | `app/(site)/[slug]/page.tsx` | done |
| success-stories.php | `app/(site)/success-stories` | done |
| video-gallery.php | `app/(site)/video-gallery` | done |
| annual-reports.php | `app/(site)/annual-reports` | done |
| our-team.php | `app/(site)/our-team` | done |
| gallery.php | `app/(site)/gallery` | done |
| contact.php, volunteer.php | `app/(site)/contact`, `volunteer` | done |
| api/contact.php (+ Constant Contact sync) | `app/api/contact/route.ts` | done (needs DB test) |
| index.php (home) | `app/(site)/page.tsx` | done |
| active-projects.php | `app/(site)/active-projects` | done |
| completed-projects.php, completed-project-details.php | `app/(site)/completed-projects`, `completed-project-details` | done |
| our-mission.php | `app/(site)/our-mission` | done |
| impact-goals.php | `app/(site)/impact-goals` | done |
| donate.php, donate-thank-you.php, api/checkout.php, api/stripe-webhook.php | `app/(site)/donate`, `donate-thank-you`, `app/api/checkout`, `app/api/stripe-webhook`, `lib/donate/*` | done (Stripe test payment still to run with a test key) |
| verify-centers.php | `app/verify-centers/route.ts` | done |
| api/map-centers.php | `app/api/map-centers/route.ts` | done |
| Pages On/Off link filter (page-visibility.php) | `lib/visibility.ts`, `components/A.tsx` | done (all pages) |
| WebP negotiation for `/assets` images (.htaccess) | `proxy.ts` (webpTwin) | done |

## Admin API (`/api/admin/v1`), new, not in the PHP site

**Done and tested locally.** It covers every admin area: content types, page text,
settings, Pages On/Off, submissions, trash, activity, users and newsletter. The
logic lives in `src/lib/admin/{resources,crud,site-admin,uploads}.ts`, which the
admin screens will reuse. Docs: [docs/ADMIN-API.md](docs/ADMIN-API.md).

## Admin (`/admin`)

Same URLs (`/admin/login.php`, `/admin/modules/<module>/<page>.php`), same markup, CSS and JS as the PHP admin, served by `app/admin/[[...path]]/route.ts`. Sessions are stored in `storage/sessions/` (keep `STORAGE_DIR` outside the deploy folder on Hostinger). Existing admin passwords keep working.

| PHP | Status |
|---|---|
| login, logout, account, dashboard, auth (sessions, roles, lockout, login_attempts) | done (`lib/admin/session.ts`, `modules/core.ts`, `modules/home.ts`) |
| includes: csrf, errors/log, listing, bulk, reorder, trash, upload (+ image derivatives), media-specs | done (`lib/admin/{context,ui,generic,crud,uploads}.ts`) |
| modules: activity, completed-projects, donate-options, gallery, hero-slides, impact-milestones, map-centers, media, mission-projects, newsletter (OAuth), page-visibility, pages (content blocks), partners, programs (+photos), reports, settings, sponsors (+groups), submissions (+export, retry sync), success-stories, team, trash, updates, users, video-gallery | done (`lib/admin/modules/*`), every page crawled and the main flows tested |
| database tools | `db/schema.sql` and `npm run create-admin`; backup/restore are done with Hostinger's database backups / phpMyAdmin |

## Checks before switching over

- [ ] Every page compared side by side with the PHP site (desktop and 390px)
- [ ] Admin: every module creates, edits, reorders, bulk-acts, deletes to trash and restores
- [ ] Forms store submissions; newsletter syncs to Constant Contact
- [ ] Stripe test payment, plus the webhook limiting the 12- and 24-month plans
- [ ] Deployed on Hostinger as a Node.js app, with env vars set and `uploads/` in place

## Verified parity (markup diff vs the PHP site)

Identical markup: every public page, the 404 page (vs 404.php) and the
"Project not found" page. Intentional differences, same on screen:

- Home: the inline `<style>` block lives in `public/css/pages/index.css` (CSP).
- completed-project-details: the hero background is an inline `style` on the div
  instead of a `<style>` in `<head>`.
- Programme pages: the "Support" button links to `donate?program=<this page>`.
  The PHP site links every one to `donate?program=volunteer` (its header nav loop
  overwrites `$slug`). The donate page ignores the parameter.

When adding a top-level page, add its folder name to `STATIC_ROUTES` in
`src/lib/route-gate.ts`, or it will answer 404.
