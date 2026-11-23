# Testing

Last updated: 2026-10-14.

The project has **no automated test suite**. Checking is done with the commands
below, a set of scripts in `extras/tools/`, and a manual pass in a browser.
Run the checks that match what you changed, then the manual pass for anything
visible.

## Quick checks (every change)

Run from the repository root `C:\xampp\htdocs\WEF`, with XAMPP's Apache and
MySQL running.

```powershell
# PHP syntax. Does not catch a stray PHP closing tag inside a comment (see CODING_STANDARDS.md).
C:\xampp\php\php.exe -l website\public\index.php

# A public page answers with 200.
curl.exe -s -o NUL -w "%{http_code}`n" http://localhost/WEF/our-team
```

## After specific changes

| You changed | Run |
|---|---|
| Any CSS | `C:\xampp\php\php.exe extras\tools\build-critical-css.php` (rebuilds the above-the-fold CSS) |
| Any inline `<script>`, or a file that holds one | `C:\xampp\php\php.exe extras\tools\csp-hashes.php`, then update the hashes it prints in the two `.htaccess` files and `includes/assets.php` |
| Images in `public/assets/images` | `C:\xampp\php\php.exe extras\tools\optimize-images.php` (check what it reports first) |
| Fonts | `C:\xampp\php\php.exe extras\tools\fetch-fonts.php` |
| Map Centers data | Admin → Map Centers, then `http://localhost/WEF/api/map-centers.php` should list the active centres as GeoJSON |
| Constant Contact | `C:\xampp\php\php.exe extras\tools\constant-contact-check.php` |
| Anything that will be deployed | `powershell -ExecutionPolicy Bypass -File extras\tools\build-deploy.ps1` and check the zip listing |

## Responsive audit (layout changes)

`extras/tools/responsive-audit.js` loads every public page at seven widths
(320, 375, 390, 768, 1024, 1280, 1440 px) in real emulated viewports and checks
for sideways scrolling, overflow, small tap targets and small text.

Setup, once, outside the repository:

```powershell
npm install --prefix "$env:TEMP\wef-pw" playwright-core
$env:NODE_PATH = "$env:TEMP\wef-pw\node_modules"
```

Run (XAMPP running):

```powershell
node extras\tools\responsive-audit.js audit        # writes audit.json and prints a summary
node extras\tools\responsive-audit.js shots our-team.php 390   # screenshot tiles
```

The audit should end clean except for the three documented slide-dot
exceptions in the hero (see `RESPONSIVE-PLAN.md`). A blank band in a full-page
capture is usually a scroll-reveal animation that had not run yet, not a bug.

Do **not** use `msedge --headless --window-size` for width checks: it does not
set the layout width reliably (see `docs/CONTEXT.md` section 10).

## Manual pass

Use a real phone for anything touch-related: sticky hover, tap delay, the
software keyboard and safe areas do not reproduce in desktop emulation.

### Public site

- Home: hero slides and name, the map (centres show, district filter works),
  the Impact Goals section (stats are centred), the partners, the newsletter form.
- Each project page: cover, impact line, before/after slider (where the project
  has one), sections with their video, the carousel (autoplays, shows only that
  project's photos, "picture coming soon" when there are none), the pager.
- Gallery: the large picture changes between projects.
- Our Team: banner photo, every person, sections.
- Contact and volunteer forms: submit one of each and check the admin's
  Form Submissions list.
- Newsletter: submit an address and check it appears in Submissions.
- A page that does not exist: the styled "Page not found".

### Admin

- Sign in with a valid account. Sign out (the Log Out button).
- Dashboard loads without an error.
- Open one list from each sidebar group (Website content, Projects & impact,
  People, Messages, Files, Administration) and check it loads without an error.
- Media Manager: upload one photo, copy its link, replace it, and confirm the
  page that used it now shows the new one. Delete an unused file.
- Map Centers: add a centre by clicking the map, move the pin, save, and
  confirm it appears on the public map. Then set it to Draft and confirm it
  disappears.
- Trash: move an item to Trash and restore it.
- As an editor: the Form Submissions and Settings links must not appear.

### Security spot checks

- `/config/config.php`, `/includes/db.php`, `/storage/` return 403.
- `/verify-centers.php` returns "Page not found" on the live site.
- Browser console on the home page and an admin page shows no
  Content-Security-Policy violations.

## Environment notes

- Local: XAMPP on Windows, Apache at `http://localhost/WEF/`, MariaDB 10.4,
  PHP 8.0.
- Live: Hostinger, PHP 8.2 or newer, MySQL. Test there after every upload.
- The local admin signs in with the account created by `create-admin.php`. Its
  password is not recorded in the repository.
