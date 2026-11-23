# Regression checklist

Last updated: 2026-10-14. Check these after any change, before handing work
over. Each line is something that works now and must keep working. Tick the
ones your change could touch; for shared code (layout, CSS, content layer,
admin shell), check all of them.

## Public site

- [ ] Home loads. Hero slides cycle; the hero name is "Bright Beginnings …" as set.
- [ ] Home map: centres show as pins; the Upper and Lower Chitral filter works;
      clicking a centre opens its popup.
- [ ] Impact Goals: stats are centred; the roadmap chart shows all points.
- [ ] Each of the nine project pages loads with its cover, impact line, sections,
      carousel and pager.
- [ ] Before/after slider appears on Bright Beginnings, Learning Centers, Model
      Schools, Language Labs and Skills Development, and on none of the other four.
- [ ] Project carousels show only that project's photos, or "picture coming soon".
- [ ] Video Gallery lists the project videos; a project's video appears beside its
      impact section.
- [ ] Gallery: the large picture changes between projects.
- [ ] Our Team: every active member shows, in their section; the banner photo shows.
- [ ] Contact and volunteer forms: a valid submission shows the success message and
      appears in the admin's Form Submissions.
- [ ] Newsletter: a valid address is accepted and stored.
- [ ] A non-existent page shows the styled "Page not found".
- [ ] No Donate or Explore buttons on project covers; no "Make a Difference Today".
- [ ] `/scholarships` does not exist.
- [ ] Mobile width (390 px): no sideways scrolling; the drawer opens and closes by
      touch; carousels swipe.

## Admin panel

- [ ] Sign-in works; a wrong password is refused; Log Out works.
- [ ] Dashboard loads with no error.
- [ ] Every sidebar link opens its page with no error.
- [ ] Editing a row and saving shows the change on the public site.
- [ ] Trash: a deleted row goes to Trash; it can be restored.
- [ ] Reordering and bulk actions still work on a list.
- [ ] Media Manager: upload, replace (the page using the file shows the new one),
      copy link, delete an unused file.
- [ ] Map Centers: add a centre by clicking the map; it appears on the public map;
      setting it to Draft removes it.
- [ ] Editor role: no Form Submissions, Settings or Users links.
- [ ] The sidebar Form Submissions badge shows the unread count.

## Security

- [ ] `/config/config.php`, `/includes/db.php`, `/storage/` return 403.
- [ ] `/.git/config` does not return 200.
- [ ] No Content-Security-Policy violations in the browser console on the home page
      and on an admin page.
- [ ] `php extras/tools/csp-hashes.php` reports both policies current.
- [ ] `/verify-centers.php` is not in the upload.

## Data and deployment

- [ ] `website/config/config.php` still holds the live values (the zip carries them).
- [ ] The zip from `build-deploy.ps1` lists `.htaccess`, `public/index.php`,
      `public/admin/login.php`, `config/config.php` and `includes/db.php`, and none
      of `verify-centers.php`, `verifier.js`, `*.log` or `*.bak`.
- [ ] `extras/deploy/` and `website/config/config.php` are not in `git status`.
