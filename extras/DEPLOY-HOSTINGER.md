# Deploying to Hostinger

The whole site is the **`website/`** folder. Its contents go into
`public_html` exactly as they are. Nothing in `extras/` is uploaded.

```
website/            ->  public_html/
  .htaccess               forwards every request into public/, refuses the rest
  public/                 the pages, CSS, JS, images, admin panel, uploads
  includes/               PHP libraries           (refused over the web)
  config/config.php       database password etc. (refused over the web)
  storage/                logs, form uploads      (refused over the web)
```

Visitors never see `/public/` in an address: `/our-team` is served by
`public/our-team.php`, `/admin/` by `public/admin/`.

## Temporary domain first

Hostinger gives the website a temporary address such as
`something-123456.hostingersite.com` until the real domain is connected. The
site works there unchanged. While it runs on any address other than
worldwideeducationfoundation.org, every page carries
`<meta name="robots" content="noindex">`, so Google does not list the
temporary copy. That switches off by itself on the real domain.

## First deployment

1. **Create the database.** hPanel -> Websites -> *Dashboard* of the website ->
   Databases -> *Management* (MySQL Databases). Create a database, a user and
   a password. Hostinger shows the full names with a prefix, e.g.
   `u123456789_wef`.

2. **Put those details in the config.** Open `website/config/config.php` and
   fill in the three `u000000000_wef` / `CHANGE-ME` lines of the **LIVE**
   block (`DB_HOST` stays `localhost`). The same file keeps working on this
   computer: it picks the local database on Windows and the live one on
   Hostinger. Doing this here rather than in hPanel means every later zip
   carries the right settings.

3. **Build the upload.** From the repository folder, with XAMPP's MySQL running:

   ```
   powershell -ExecutionPolicy Bypass -File extras\tools\build-deploy.ps1
   ```

   It writes two files to `extras\deploy\` (use the newest pair):
   - `wef-website-….zip`: the contents of `website/`
   - `wef-database-….sql`: all site content, admin accounts included

   The local pin checker (`verify-centers.php`) is left out on purpose: it has
   no sign-in and would let anyone change the map file. Map pins are edited in
   Admin -> Map Centers.

4. **PHP settings.** hPanel -> Advanced -> *PHP Configuration*: PHP **8.2 or
   newer**. On the extensions tab make sure `pdo_mysql`, `gd`, `fileinfo`,
   `exif` and `mbstring` are ticked (they are by default).

5. **Upload the site.** hPanel -> Files -> *File Manager* -> `public_html`.
   Delete Hostinger's placeholder files (`default.php`, `index.html`) if
   present. Upload the zip, right-click it -> *Extract* -> extract into
   `public_html` itself (leave the folder name empty / `.`). Check that
   `.htaccess`, `public`, `includes`, `config` and `storage` sit directly
   inside `public_html`, not in a sub-folder. Delete the zip.

6. **Import the content.** hPanel -> Databases -> *phpMyAdmin* -> *Enter
   phpMyAdmin* next to the new database -> *Import* tab -> choose the `.sql`
   file -> *Import*. It should end with "Import has been successfully
   finished".

7. **Backups folder.** In File Manager go one level **up** from
   `public_html` and create a folder named `wef-backups`. Admin backups go
   there, where no web request can reach them.

8. **HTTPS.** Temporary `hostingersite.com` addresses come with a
   certificate. If the browser says "Not secure", hPanel -> Security -> *SSL*
   -> install the free certificate. The http:// to https:// redirect is
   already in `.htaccess`; there is no need to switch on Hostinger's
   "Force HTTPS" as well.

## Check it

Replace `DOMAIN` with the temporary address:

- `https://DOMAIN/`: the home page, with the map showing 94 centres
- `/our-team`, `/bright-beginnings`, `/gallery`: pages load with photos and styling
- `/admin/`: sign-in page; the admin accounts are the same as locally
- `/config/config.php`, `/includes/db.php`, `/storage/`: must say **403 Forbidden**
- `/verify-centers.php`: must say "Page not found"
- `/some-page-that-does-not-exist`: the styled "Page not found"

If every page shows "Something went wrong", the database details in
`config/config.php` do not match hPanel, or the `.sql` was not imported.

## Moving to the real domain later

1. hPanel -> the website's Dashboard -> *Connect domain* (or *Change
   domain*), enter `worldwideeducationfoundation.org`, and point the domain's
   nameservers or DNS where Hostinger says. The files and database stay as
   they are.
2. Install the SSL certificate for the real domain (Security -> *SSL*).
3. Nothing in the code changes: the noindex marker disappears on its own
   and the site becomes visible to search engines. Submit
   `https://www.worldwideeducationfoundation.org/sitemap.xml` in Google
   Search Console.
4. If the site also answers on another domain (e.g. wefdallas.org), add it
   to `APP_SITE_HOSTS` in `config/config.php` (the commented line in the
   LIVE block).
5. **Constant Contact** (newsletter, optional). In the Constant Contact
   developer app, add this Redirect URI:
   `https://www.worldwideeducationfoundation.org/admin/modules/newsletter/callback.php`
   then connect again from Admin -> Newsletter.

## Later updates

- **Content** is edited in the live admin from now on. Do **not** import a
  local `.sql` again: it would replace everything edited on the live site.
- **Code changes:** upload only the changed files through File Manager, or
  build a new zip and extract it over `public_html`. Extracting only adds and
  overwrites files. Photos uploaded through the live admin stay, because
  their names never clash with the zip's.
- **Data fixes made locally** (e.g. the Honor Board badges) come as small
  update files in `extras\deploy\`, named after what they change
  (`honor-board-update-….sql`, `stat-labels-….sql`, `page-switches-….sql`,
  `semi-govt-schools-….sql`, `donate-options-….sql`).
  Import each one in
  phpMyAdmin (live database -> *Import*). They change only the rows they
  name and can safely be imported twice.
- If the database password was changed in hPanel, put the new one in the
  LIVE block of `config/config.php` before building the zip (or edit the
  file in File Manager after extracting), or every page shows "Something
  went wrong".
- **Donate page (Stripe):** the Stripe keys go in the server's existing
  `config/config.php`. See [DONATE-STRIPE.md](DONATE-STRIPE.md). If your local
  `config/config.php` only holds test settings, leave it out of the zip.
- If you edit one of the inline scripts named in `website/includes/assets.php`,
  run `php extras\tools\csp-hashes.php` and follow what it prints, or the
  browser will block that script.
