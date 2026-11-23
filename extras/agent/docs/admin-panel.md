# WEF — Admin Panel

State of the CMS after the Phase 5 review (31 July 2026). Every module was
reviewed; this records what was wrong, what was added, and what is still open.

## Bugs found and fixed

### 1. Media manager offered to delete images that were still in use

`referencedUploadPaths()` listed five (table, column) pairs by hand and had
fallen behind: **`project_updates` was missing**, even though that module
uploads images and `TRASH_TYPES` had declared its `fileColumns` correctly all
along.

Every image attached to a project update appeared in Media as unreferenced,
with a delete button next to it. `delete.php`'s second-chance re-check
consulted the same incomplete list, so it failed identically — both lines of
defence had the same hole.

**Fix:** the list is now *derived* from `TRASH_TYPES` instead of duplicated, so
adding a content type cannot leave it behind. Coverage went from 5 hardcoded
columns to 6 derived ones.

**Verified:** created a project update with an image, confirmed Media reports
it as referenced, and confirmed `delete.php` refuses with *"That file is still
in use"* while the file survives on disk.

### 2. Project-update images were filed under `programs/`

`updates/save.php` called `handleImageUpload('image', 'programs', …)` — a
copy-paste from the module it was cloned from. Every project-update image went
into `admin/uploads/programs/`, mixed in with a different content type.

**Verified:** a new upload now lands in `updates/`.

### 3. The bulk menu said "Delete permanently" and did the opposite

`bulkToolbar()` rendered *"Delete permanently"* while the action set
`deleted_at` and the confirmation said *"moved to Trash"*. Wrong in the more
dangerous direction too: it invites someone to avoid a reversible action
believing it is not.

**Fix:** the option now reads **"Move to Trash"**.

### 4. The dashboard counted trashed content as live

None of the dashboard's `COUNT(*)`s filtered `deleted_at IS NULL`. Because soft
delete stamps `deleted_at` without touching `status`, a trashed-but-published
item was counted as **live**.

Measured before the fix: the dashboard reported **10 programmes when 9 were
real**, and **26 live team members when the site was showing 25**. The number an
admin checks to answer "what is on the site right now" was the one number that
could not be trusted.

**Verified:** counts now read 9 programmes and 25 live team members, matching
the public site.

## Features completed

### Trash pagination

Trash was the only listing with no pager. `trashedItems()` fetched **every**
soft-deleted row from all seven tables, sorted the lot in PHP, and rendered all
of it.

Now paginated with the same `listParams()` / `listPagination()` helpers the
content modules use. The per-table `LIMIT` matters as much as the pager: the
globally newest N rows can only come from each table's own newest N, so asking
each table for at most `offset + perPage` keeps the **fetch** bounded, not just
the render.

**Verified** against interleaved fixtures across three tables, deliberately
lopsided so one table owned the newest ten rows — the case a naive per-table
limit breaks. Walked every page at six page sizes (1, 2, 3, 5, 7, 20): order
matched the unpaginated reference exactly, with no dropped or duplicated rows.

### Self-service password change — `admin/account.php`

There was no way to change your own password. The only route was asking a Super
Admin to set one through the Users module — meaning **somebody else chooses your
password and sends it to you over chat**. That is how shared and reused
passwords happen, and it made the 12-character policy added in Phase 2 largely
theoretical, since nobody rotates a password they cannot change.

Deliberately **not** in `modules/users/`, which is gated behind `users.manage`
(Super Admins only). This page acts only on `currentUser()['id']` — the account
id is never read from the request — so every role can reach it safely.

Reached by clicking your own name in the topbar, which is where people look.

Behaviour:

- Requires the **current** password. Without it, anyone finding an unlocked
  machine could lock the real owner out of their own account.
- Same `passwordPolicyError()` as the Users module and `create-admin.php`.
- Rejects a new password identical to the current one.
- `session_regenerate_id(true)` on success, so any other session holding this
  one's cookie is left with an id the server no longer honours.
- No `flashInputSet` on failure — repopulating a password field would put the
  value back into the next response's HTML.
- Writes a `password_change` row to the activity log.

**Verified end-to-end:** all five rejection paths produce the right message;
the success path changes the stored hash, the **old password stops working**,
the new one logs in, and the change is audit-logged.

## Accessibility

Audited across ten rendered admin pages.

| Check | Result |
|---|---|
| Images with `alt` | **17 / 17** |
| Visible form controls without a label or `aria-label` | **0** |
| Landmarks (`main`, `nav`, `header`) | present |
| Skip link | present |
| `aria-current` on active nav | present |
| `lang` + viewport | present |
| Pages with exactly one `<h1>` | 9 / 10 → **10 / 10** |

The one failure was the **dashboard, which had no `<h1>` at all** — it opened at
`<h2>`, so its heading outline began one level down and a screen reader landing
there got no page title.

Fixed with a visually hidden `<h1>Dashboard</h1>` rather than a visible one:
the greeting below it is the design's heading, and adding a second visible
title would have been a redesign to fix a structural problem.

The new topbar link also needed real link semantics — `:hover` background and a
`:focus-visible` outline — since it is a keyboard user's route to their own
password.

## Form Submissions — new module

The Contact and Volunteer forms were **decoration**. `js/main.js` caught the
submit event, called `preventDefault()`, waited 1.5 s, showed *"✓ Message
Sent!"* and reset the fields. Nothing was posted, stored or emailed. Every
enquiry sent since launch was discarded, and the sender was thanked for it.

Now: `api/contact.php` receives both forms and writes to `form_submissions`,
and this module is the read side.

| Piece | What it does |
|---|---|
| `index.php` | Search, filter by form and status, sort, pager, bulk triage |
| `view.php` | Full message, status, internal note, `mailto:` reply |
| `status.php` | Status + note, in one transaction |
| `bulk.php` | Read / Replied / Archived / New / Move to Trash |
| `delete.php` | Soft delete via `trashItems()` |
| `export.php` | CSV honouring the current search and filters |
| `retry-sync.php` | Sends one newsletter signup to the Constant Contact list by hand |

Design decisions worth recording:

- **Its own capabilities.** `submissions.view` and `submissions.manage`, granted
  to Administrator and above — *not* `content.*`. These rows are names, email
  addresses and messages belonging to members of the public, not site content
  an Editor happens to be able to publish. Editors deliberately get neither.
- **One table for both forms.** They collect the same four fields and differ
  only in the subjects offered, so `form_type` separates them. Two tables would
  mean two lists and two exports to keep in step for no gain.
- **A triage workflow, not draft/active.** `new → read → replied → archived`.
  Reusing the publishing words would suggest a submission can go public.
- **Opening a `new` submission marks it `read`.** That is what "new" means, and
  leaving it to a button guarantees a queue of items marked new that everyone
  has already read.
- **Reply is a `mailto:`**, not send-from-the-CMS. Sending would need SMTP
  credentials, a sent-items store and a bounce story, and would put WEF's
  replies somewhere their own mailbox cannot see.
- **CSV export is formula-injection safe.** A message beginning `=`, `+`, `-` or
  `@` is treated as a formula by Excel, LibreOffice and Sheets — an anonymous
  visitor could put a DDE payload in the message box and have it run on the
  machine of whoever opens the export. Those cells are prefixed with `'`.

## Newsletter — Constant Contact connection

The footer newsletter form was the third fake form, and wiring it to
`api/contact.php` fixed only half of it: addresses were stored, but somebody
still had to copy each one into Constant Contact by hand. This module closes
that gap.

| Piece | What it does |
|---|---|
| `index.php` | Connection status, the last error, the mailing-list picker |
| `connect.php` | POST-only. Mints the OAuth `state` and redirects to Constant Contact |
| `callback.php` | The registered Redirect URI. Includes **nothing** and starts **no session** — see below |
| `exchange.php` | Verifies `state`, redeems the code, stores the tokens |
| `save.php` | Stores the chosen list, re-validated against the live account |
| `disconnect.php` | Forgets the tokens locally |

Design decisions worth recording:

- **It is a module, not a Settings field.** The actions here are an OAuth round
  trip to a third party, a list read from live data, and a failure state that
  needs explaining — not "edit a field and save". Bolting it onto the Settings
  form would have meant a Save button that sometimes redirects to
  `constantcontact.com`.
- **Its own capability**, `newsletter.manage`. Whoever holds it can point every
  future subscriber at a mailing list of their choosing. That is not a
  content-editing decision, so Editors do not get it.
- **`callback.php` is almost empty, and must stay that way.** The admin session
  cookie is `SameSite=Strict`, so the return trip from Constant Contact arrives
  with **no session at all**. Starting one there would mint a fresh empty
  session and overwrite the administrator's real cookie mid-flow — logging them
  out. So it re-posts to `exchange.php` instead, which is a same-site request
  where the cookie *is* sent and the normal capability check applies. A form
  POST rather than a redirect keeps the single-use code out of browser history.
- **`exchange.php` skips `requirePostWithCsrf()` on purpose.** The page that
  posts to it had no session and so could not embed a CSRF token. The OAuth
  `state` check is the stronger substitute here — it proves both that the flow
  started on this site *and* that the same session is finishing it.
- **The list is re-validated on save.** The dropdown only offers real options,
  but a POST is what arrives, not a dropdown. Without the check any string could
  be stored and signups would fail silently against a list that does not exist.
- **Failures are visible where the work is.** Submissions rows carry a badge and
  a retry button, and `php tools/constant-contact-check.php` reports config,
  reachability, credentials and the unsynced backlog from the CLI.

### Trash capability leak found while wiring this up

Registering `submissions` in `TRASH_TYPES` exposed an assumption baked into
Trash: it checked `content.view` / `content.edit` / `content.delete` for
**every** type, because until now every type was site content. With submissions
registered, an **Editor** — who has `content.view` and deliberately no
`submissions.view` — would have seen every trashed enquirer's name on the Trash
page and been able to restore it, straight past the capability that exists to
prevent exactly that.

**Fix:** each `TRASH_TYPES` entry now declares `viewCap` / `restoreCap` /
`purgeCap`. `trashedItems()` and `trashCount()` filter through
`visibleTrashTypes()`, the row buttons check the row's own type, and
`restore.php` / `purge.php` resolve the type first and then enforce its
capability. Trash can no longer be a way around the permission on the module an
item came from.

**Verified** with a capability matrix across all three roles: Editor sees the
seven content types and no submissions; Administrator and Super Admin see all
eight.

## Module coverage

| Module | Search | Filter | Pager | Bulk | Reorder | Trash |
|---|---|---|---|---|---|---|
| Programs, Updates, Team, Gallery, Reports | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Sponsors | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Submissions** | **✓** | **✓** | **✓** | **✓** | — | **✓** |
| Media | ✓ | ✓ | ✓ | — | — | n/a |
| Users | ✓ | ✓ | ✓ | — | — | n/a |
| Activity | ✓ | ✓ | ✓ | — | — | n/a |
| Trash | — | — | ✓ | — | — | n/a |
| Pages, Settings | — | — | — | — | — | n/a |

The remaining gaps are deliberate, not oversights: Pages and Settings are fixed
short registries (7 pages, 8 settings), and bulk/reorder are meaningless for
Users, Activity and Media. Submissions has no reorder because they are ordered
by arrival — Export CSV takes reorder's place in the toolbar.

## Still open

- **Media has no bulk delete.** Clearing a large number of orphans is one click
  each. Worth adding if the uploads directory ever gets messy; the shared
  `processBulkAction()` does not fit as-is because Media acts on files, not rows.
- **No notifications beyond flash messages.** A `FATAL` still writes to a log
  nobody reads. Email-on-FATAL needs SMTP settings this project does not have
  yet; an in-panel "recent errors" badge would be testable without them.
- **No MFA** for `super_admin`.
- The codebase is **still not in version control**.
