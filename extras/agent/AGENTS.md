# AGENTS.md: rules for coding agents

These rules apply to every coding agent working on the WEF website. Read
them, then `context.md`, `TASKS.md` and `HANDOFF.md`, before changing
anything.

## Before you start

1. Read `context.md` for the goals and constraints, and `HANDOFF.md` for
   the state the last session left.
2. Check `git status`. Note any files you did not change. Leave them alone
   and do not commit them.
3. Verify before you repeat a fact. Older docs in `docs/` were wrong in
   places (see `docs/CONTEXT.md` section 9). Grep the code first.

## Scope

- Change only what was asked. If a fix needs a wider change, say so and
  wait for a yes.
- Do not change code for a question. Answer questions in words.
- Do not delete database rows, uploaded files or pages without asking.
  Use the soft-delete (Trash) path that the admin already has.

## Content and ethics (non-negotiable)

These come from the owner's project brief and are constraints, not style
preferences. The full list is in `context.md`.

- Photograph and describe people as agents, not victims.
- Never publish a child's face or name without documented guardian consent.
  Naming and being identifiable are two separate permissions.
- WEF is the facilitator; Chitral is the subject.

## Frontend

- No new `!important`. Adding one means the cascade is wrong; fix the cascade.
- Use the design tokens. Do not add magic numbers if the scale lacks a
  value; add the value to the scale.
- Every animation respects `prefers-reduced-motion`.
- `:hover` effects sit inside `@media (hover: hover) and (pointer: fine)`.
- Test layout changes at every breakpoint in `RESPONSIVE-PLAN.md`
  (320 to 1440 px). Changes that only look right in desktop emulation are not
  verified. Mobile is the donation path: about 43% of gifts, so
  it gets the most care.
- After any CSS change, run `php extras/tools/build-critical-css.php`.
- After changing an inline `<script>` or its bytes, run
  `php extras/tools/csp-hashes.php` and follow its output.

## Backend

- Public pages read only through `includes/content.php` and
  `includes/content-blocks.php`. They never include anything from `admin/`.
- Admin modules follow the existing pattern: `index.php`, `form.php`,
  `save.php`, `delete.php`, `bulk.php`, `reorder.php`, using the shared
  `listing.php`, `trash.php`, `bulk.php` and `reorder.php` helpers.
- Every admin write checks `requirePostWithCsrf()` and a capability with
  `requireCapability()`.
- Escape output with `e()`. Never trust `$_GET`/`$_POST` values.
- Never write a literal PHP closing tag inside a `//` or `#` comment. It ends
  PHP mode silently, and `php -l` does not catch it.

## Secrets and deployment

- Never commit `website/config/config.php`, `extras/deploy/`, or any
  database dump. They hold the database password and every form submission.
- Never push, force-push or rewrite history without the owner's approval.
- Never import a local `.sql` into the live database. Live content is
  edited in the live admin.
- Build the upload with `extras/tools/build-deploy.ps1` and check its output.

## Records

- When you finish a task, update `TASKS.md` (move the item to done),
  `CHANGELOG.md` (one line, dated), and `HANDOFF.md` (what changed, what was
  tested, what is next).
- When the structure changes, update `ARCHITECTURE.md`. When the schema
  changes, update `DATABASE.md`.
- Commit only the files you changed, with an explicit file list. Do not use
  `git add -A` over folders that hold uploads or other sessions' work.
