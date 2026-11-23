# Coding standards

Last updated: 2026-10-14. Match the code around you. These are the conventions
the existing code follows, so new code fits in.

## General

- Write for the next reader. Comments explain **why** (a constraint, a past bug,
  a rule from the owner), not what the code does.
- Do not add a dependency for something PHP, CSS or the browser already does.
- Do not add a build step for the application.
- Prefer a small, local change over a broad refactor, unless the task is the refactor.
- Keep to the existing file layout (see `ARCHITECTURE.md`).

## PHP

- PHP 8.0 syntax at most (the local version). The host runs 8.2.
- Files: `kebab-case.php`. Functions: `camelCase`. Constants: `UPPER_SNAKE_CASE`.
- Each admin page starts with `require_once` of the bootstrap, then the
  capability check (`requireCapability('content.edit')`), and the layout header
  and footer include the page's `$pageTitle` and `$activeNav`.
- Every write: `requirePostWithCsrf()`, then a capability, then validation, then the
  write, then `logActivity()` and a flash message, then a redirect (`redirect()`).
- Output is escaped with `e()`. Attributes too. Never echo raw request values.
- Database access: PDO with prepared statements, through `getDb()`. Check for `null`
  (the database may be down), and use the static fallback on the public site.
- Soft delete (`deleted_at`) for anything a visitor could have seen. Hard delete
  only for rows that were never public and for files that nothing references.
- **Never write a literal PHP closing tag inside a `//` or `#` comment.** It ends
  PHP mode and the rest of the file is output as text. `php -l` does not catch it.
- Public pages read only through `includes/content.php` and `includes/content-blocks.php`.
  Neither contains write queries; keep it that way.
- Page copy comes from `BLOCK_REGISTRY` (`content-blocks.php`), not hardcoded HTML.

## CSS

- Use the tokens in `styles.css`: colours (`--clr-*`), spacing (`--space-N`,
  numeric, 4 px steps), radii, shadows, z-index (`--z-*`), durations and easings.
  If the scale lacks a value, add it to the scale.
- No new `!important`.
- Hover effects go inside `@media (hover: hover) and (pointer: fine)`.
- Every animation has a `prefers-reduced-motion` alternative.
- Touch targets are at least 44 px on phones. Text is at least 12 px.
- Class names are descriptive and lower-case with hyphens. Project pages use the
  `pp-` prefix. Admin classes are in `admin/assets/css/admin.css`.
- Run `extras/tools/build-critical-css.php` after changing CSS.

## JavaScript

- Vanilla JS, in an IIFE or a module-like closure. No global variables beyond what a
  file must expose.
- Wait for the DOM before touching it. Guard against a missing element (the same
  script runs on pages that lack it).
- Avoid inline event handlers (`onclick=""`) and inline scripts. An inline script
  needs a hash in the policy (see `SECURITY.md`), and it must be re-hashed after
  every edit.
- Respect `prefers-reduced-motion` and the hover gating above.

## Admin modules

- One folder per content type under `public/admin/modules/`, with the standard
  files: `index.php`, `form.php`, `save.php`, `delete.php`, `bulk.php`, `reorder.php`.
- Register the module in `admin/includes/layout-header.php` (sidebar), in
  `trash.php` (`TRASH_TYPES`), and in `bulk.php` and `reorder.php` (allowed tables).
- Give every file column an entry in `TRASH_TYPES` `fileColumns` so the media
  manager sees it as in use.

## Content and copy

- Use the names from `BUSINESS_RULES.md`, spelled exactly as there.
- Describe people as agents, not victims (see `context.md`).
- Dates for launches are years only.

## Git

- One logical change per commit. Message: a short imperative summary, then a body
  if the reason is not obvious.
- Commit only the files you changed, with an explicit file list.
- Never commit `website/config/config.php`, `extras/deploy/`, or secrets.
- Do not force-push or rewrite history without the owner's approval.
