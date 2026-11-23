# Project context

Last updated: 2026-10-14. This is the first file to read. Where it disagrees
with a file in `docs/`, this file wins. The longer analysis behind it is in
`docs/CONTEXT.md`.

## What the project is

The Worldwide Education Foundation (WEF) is a Dallas, Texas 501(c)(3)
nonprofit (operating as the Worldwide Education Fund of The Dallas
Foundation). It funds education in **Chitral, Pakistan**, a mountain district
in the Hindu Kush in Khyber Pakhtunkhwa. Nearly all field work is in that one
valley.

Contact for the organisation: info@wefdallas.org.

The website is the foundation's public face and its main donation channel.

## Goals

1. **Donations first.** Every design decision should move a visitor toward
   giving. The owner confirmed this as the primary goal.
2. **Two audiences, one site:**
   - US individual donors (primary). They need an emotional connection, then
     proof that their money is well spent. This includes a Muslim-American
     segment for whom Zakat eligibility matters.
   - Institutions: grant-makers, universities and government partners. They
     need research, reports, transparency and proof of partnership. Serve them
     with depth on subpages, not by diluting the home page.
3. **Answer the two donor questions on the home page:** what WEF stands for,
   and how the money is spent. Few nonprofit sites answer the second.
4. **Keep the admin editable.** The owner must be able to change content,
   photos and map centres without a developer.

## Programmes

| Stage | Programmes |
|---|---|
| Early years | Bright Beginnings: Early Childhood Learning Centers |
| Primary | Model Schools; Community Learning Centers |
| Capability | Teacher Training; Language Labs |
| Secondary and beyond | Secondary Scholarships; AM Rising Stars Undergraduate Scholarship; Skills Development; Herzing Center for Skills, Innovation and Entrepreneurship; Research Hub |

The exact names above are the ones the owner asked for. The home page says
**100+** learning spaces, but the map holds 94 centres (see `KNOWN_ISSUES.md`).
Upper and Lower Chitral are the two districts the map filters by.

## Institutional partners

University of Chitral, University of Central Asia, AUCA, Friedrich Ebert
Stiftung, American Spaces Tajikistan, and the KP Elementary & Secondary
Education Department. These are WEF's strongest credibility asset.

## Voice and visuals

- **"Competent partnership", never "rescue".** The test for any element:
  does it make a donor feel like a co-investor in a capable local institution,
  or like the saviour of helpless children? The second is ethically wrong and
  commercially weaker.
- Visual language: Chitral's light, stone, snow, deep blues and warm ochres,
  with the existing green and orange brand. Typography is quiet and editorial;
  photography carries the emotion.
- Motion settles rather than bounces. No urgency pop-ups, no bouncing counters,
  no confetti.

## Ethical constraints (non-negotiable)

WEF's beneficiaries are mostly minors, many of them girls, in a conservative
rural region where girls' education is contested. Identifiability carries real
risk. The site follows the CONCORD / Dóchas Code of Conduct on Images and
Messages.

- Photograph people as **agents, not victims**: mid-activity, at eye level,
  engaged.
- **Never** show downcast eyes, dirt-smeared faces, bare feet as shorthand,
  outstretched hands, or a Western visitor at the centre of the frame.
- Never publish a child's face without documented guardian consent. Record
  two separate permissions per subject: being **named**, and being
  **identifiable**.
- Let subjects narrate their own stories and byline them.
- WEF is the facilitator, not the hero.

## Current state

- **Public site:** PHP 8 pages with a content layer over MySQL. Each page's
  copy and images can be edited in the admin, and the site falls back to
  built-in defaults when the database is unreachable.
- **Admin panel:** full create, edit, delete, trash, reorder and bulk actions
  for every content type, with three roles: super admin, administrator and
  editor (see `SECURITY.md`). Media Manager
  handles uploads, replacement (every reference switches at once) and
  deletion.
- **Map:** the home map reads the `map_centers` table (94 centres at the last
  import). Admins place centres by clicking on a Leaflet map.
- **Project pages:** nine project pages share one layout (cover, impact line,
  before/after slider, sections, own-photo carousel, pager). Teachers'
  Training, the two scholarships and the Herzing Research hub have no
  before/after slider.
- **Deployment:** Hostinger shared hosting, PHP and MySQL. The site is uploaded
  as the zip made by `extras/tools/build-deploy.ps1`, from the `website/`
  folder. It is running on a temporary `hostingersite.com` address; the real
  domain is not connected yet. See `extras/DEPLOY-HOSTINGER.md`.
- **Local development:** XAMPP on Windows, at http://localhost/WEF/.

## Constraints

- **Hosting:** Hostinger shared hosting. No shell access and no build step on
  the server, so everything the site needs must be in the uploaded zip. The
  host runs PHP 8.2 or newer; XAMPP here runs PHP 8.0, so do not use features
  newer than 8.0, because they cannot be tested locally.
- **No framework and no build step for the application.** Vanilla PHP, CSS
  and JavaScript, with Leaflet for maps (the public map loads it from unpkg;
  the admin has its own copy). The critical CSS is generated by a tool that must be re-run after CSS changes.
- **Security policy:** Content-Security-Policy with hashes for the few inline
  scripts. Inline `<script>` changes need the hashes regenerated.
- **Uploaded photos are committed to git on purpose.** `public/admin/uploads/`
  is the only backup of editor-uploaded images. Do not remove it from the
  repository.
- **Secrets stay local.** `website/config/config.php` is gitignored and holds
  the live database password. It must never be committed or pasted into a
  shared document.
- **Mobile is the donation path.** About 43% of gifts come from mobile, and
  mobile converts 30 to 40% worse. Test layout on real phone sizes, because
  sticky hover, tap delay, `100vh` and the software keyboard do not reproduce
  in desktop emulation.
- **Motion respects `prefers-reduced-motion`.**
