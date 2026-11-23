# extras/

Everything in the repository that is **not** part of the running site. The
site itself is `../website/`, and only that folder is uploaded to Hostinger
(see [DEPLOY-HOSTINGER.md](DEPLOY-HOSTINGER.md)).

Nothing here is loaded by the application. Locally it is not reachable over
HTTP either: the development `.htaccess` at the repository root forwards every
request into `website/`, where these paths do not exist.

| Path | What it is |
|---|---|
| `DEPLOY-HOSTINGER.md` | Step-by-step upload and first-run guide |
| `tools/` | Build and maintenance scripts, run from the repository root: `build-deploy.ps1` (upload zip + database export), `build-critical-css.php` (after any CSS change), `optimize-images.php`, `csp-hashes.php`, `responsive-audit.js`, `fetch-fonts.php` and others. They find the site through `../../website/`. |
| `agent/` | Files for coding agents: rules (`AGENTS.md`), project context, architecture, database, security, testing, known issues, handoff. Start at `agent/README.md`. |
| `agent/docs/` | Older reference documents (architecture, database, security, performance, admin notes). Where they disagree with the files above, those win. `PROJECT_STRUCTURE.md` is an older file-by-file tour. |
| `old-db-backups/` | The four backups that used to be committed in `storage/backups/`; read its README |
| `deploy/` | Output of `build-deploy.ps1`. Gitignored: it holds the live config and the whole database |
| `graphify-out/` | Generated code-graph cache (gitignored) |
| `wef-ceo-presentation.html` | Standalone slide deck, previously at `public/`. It loads Chart.js and Google Fonts, which the site's security policy blocks, so it no longer worked from the website. Open the file directly in a browser. |

The old scratch diff dumps (`bb_diff.txt`, `css_diff.txt`, `index_diff.txt`)
and the one-shot migration scripts (`replace_arrows.py`, `replace_cards.py`,
`revert_cinematic.py`) were deleted on 2026-11-22; git history has them.

### Vercel pipeline — removed

The site is hosted on **Hostinger (PHP + MySQL, serving `public/*.php`
directly)**. The old Vercel static-export pipeline (`vercel.json`,
`.vercelignore`, the contact JSON stubs and `build-static-site.php`) was
archived here on 2026-09-22 and deleted on 2026-09-26. It is recoverable
from git history if ever needed.

Also removed on 2026-09-22, confirmed stale and unreachable rather than
archived (git history has them if ever needed): root-level `admin/` (a byte-
identical mirror of `public/admin/uploads/`, hash-verified across all 195
files before deletion), and root-level `api/`, `assets/`, `css/`, `js/` — all
leftovers from before the `public/` restructure. The root `.htaccess` rewrites
every request into `public/`, so these were never reachable; the CSS files
were also found to be out of sync with their `public/` counterparts, which is
what confirmed nothing was still maintaining them.

## What stays at the repository root

- `website/` — the site.
- `.htaccess` — local development only: forwards http://localhost/WEF/ into
  `website/`. Not uploaded.
- `.editorconfig`, `.gitignore`, `README.md` — editor, git and orientation.
- `.agents/`, `.claude/`, `skills-lock.json`, `.graphifyignore` — AI tooling
  configuration, which those tools look for at the repository root.
