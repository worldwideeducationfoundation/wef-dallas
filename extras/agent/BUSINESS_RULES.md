# Business rules

Last updated: 2026-10-14. Rules the code enforces, and the rules the owner
set that the code does not yet enforce, are marked.

## Donations

- The goal of every page is a donation (see `context.md`).
- The header and home "Donate Now" buttons open the donate page (`/donate`,
  `public/donate.php`). Zeffy is no longer used.
- The donate page lets the donor pick a program (or "Where it's needed most"),
  a giving option and an amount, then pays through Donorbox: `donateUrl()`,
  by default the Bright Beginnings campaign (`donorbox.org/bright-beginnings-972649`)
  unless Settings has another link (`site_settings.donate_url`). A Donorbox
  campaign opens in a dialog over the page; any other link is simply opened.
- The Program dropdown comes from admin -> Donate Options (`donate_options`),
  in its order; `general` ("Where it's needed most") is selected by default.
  Linked options follow their project and keep its slug, which the project
  pages' Donate links use.
- Giving options are in `DONATION_PRESETS` in `donate.php` (by option or project slug). Only Bright
  Beginnings has presets ($2,400 one-time, or two installments of $1,200).
  Every program also has "Other amount" (more than $0, up to $100,000, two decimals).
- Donorbox gets the amount (`?amount=`) and always `default_interval=o`
  (one-time; Donorbox otherwise preselects Monthly). It does not get the
  program: the note asks the donor to mention it in the comment. Installments
  are not scheduled; the donor makes the second payment themselves.
- The campaign's donor wall is shown under the form; its progress meter is off
  (`DONORBOX_SHOW_METER`) until the campaign has a goal in Donorbox.
- Every program shares the one Donorbox campaign until the owner creates others.
- Project pages end with a "Support <project>" band linking to
  `donate?program=<slug>`.
- The "Make a Difference Today" section has been removed from the home page.
  Do not bring it back without the owner's request.
- Covers of project pages have **no** Donate or Explore buttons.

## Projects

- There are nine project pages. They share one layout
  (`includes/layout/project-page.php`).
- **Before/after slider:** shown on Bright Beginnings, Learning Centers, Model
  Schools, Language Labs and Skills Development. **Not** shown on Teachers'
  Training, Secondary Scholarships, AM Rising Stars Undergraduate Scholarship,
  or the Herzing Research hub (`research-hub`). Set in each page's
  `$projectPage['before_after']`.
- **Photos:** the carousel and the gallery show only that project's own photos
  (`program_photos`). A project with no photos shows "picture coming soon" in
  its carousel. Never fill a carousel with another project's photos.
- **Carousel:** autoplays every 4 seconds. It pauses when a visitor uses it and
  resumes when focus leaves it.
- **Video:** a Video Gallery entry whose title is the project's name appears
  beside the project's section about its impact. It also appears in the Video
  Gallery.
- **Launch dates** are shown as a year only, never a month.
- **Names** used in the site, exactly:
  - Bright Beginnings: Early Childhood Learning Centers
  - AM Rising Stars Undergraduate Scholarship
  - Herzing Center for Skills, Innovation and Entrepreneurship
  - Herzing Research hub (the Research Hub page)
  - Note: the owner's request spelled "Early" as "Earily" once. The site uses
    "Early". See `KNOWN_ISSUES.md`.

## Gallery

- The large picture changes between projects. `js/gallery.js` (`mixByProject`)
  shuffles the photos of each project, then takes them in turn across
  projects, so consecutive pictures come from different projects.

## Home map and learning centres

- Each centre is a row in `map_centers` with a village, a district, a type, a
  sponsor, an optional lead name, coordinates and a status.
- **Districts:** Upper Chitral and Lower Chitral only.
- **Types:** Entrepreneurial Model, Government School, Private School,
  Elementary School. Each type needs a colour in `js/map.js`.
- **Coordinates** must fall inside Chitral's bounding box (latitude 34.5 to 37.5,
  longitude 70.5 to 74.5). A pasted "lat, lng" is split. A swapped pair is
  refused.
- **Status:** `active` shows on the map; `draft` is hidden; a trashed row is
  hidden too.
- The map, the district filter and the Honor Board link all read these rows.
- The home page states "100+" learning spaces. The map holds 94 centres. Keep
  the two consistent (see `KNOWN_ISSUES.md`).

## Honor Board (sponsors)

- Sponsors are grouped by sponsor group. A sponsor's name should match exactly
  across the Honor Board and the map, because the map's sponsor filter and the
  Honor Board link both match on the name.

## Content visibility

- Only `status = active` rows that are not trashed appear on the public site.
- Trash keeps a row for restoring. Purging from Trash deletes its files too.
- An image that is replaced is first given a new file name, so a visitor with a
  cached copy of the old one is not affected. Replacing a file switches every
  page that used it at once.

## Form submissions and newsletter

- Contact, volunteer and newsletter forms store every submission in the
  database. The code does not email anything to staff (no mail sending exists in
  the project), so staff must check Form Submissions.
- A newsletter signup is sent to the Constant Contact list chosen in the admin.
  If that fails, the signup is still stored and the failure is shown with a
  retry button.
- Editors cannot read submissions. Administrators and super admins can.

## Roles

| Role | Can |
|---|---|
| Super admin | Everything |
| Administrator | All content, media, settings, form submissions, newsletter, activity log |
| Editor | Content and media (view, create, edit, upload). Not delete, not settings, not submissions |

Deleting content is an administrator action.

## Ethics (owner's constraints)

- Children are photographed as agents, not victims (see `context.md`).
- A child's face is published only with documented guardian consent.
- Two permissions are recorded per subject: **named** and **identifiable**.
  - Not yet enforced: the database has no consent field. Consent is recorded
    outside the system. Ask the owner before publishing any new child's photo.

## Money figures

- Mission project financial figures are shown as the owner supplied them, in
  dollars and rupees. Do not recalculate or round them without the owner's
  source.
