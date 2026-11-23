# API

Last updated: 2026-10-14. The site has no general public API. It has three
endpoints under `website/public/` and a few admin handlers.

## Public endpoints

### `POST /api/contact.php`: contact, volunteer and newsletter forms

Used by the contact page, the volunteer page and the footer newsletter form.

**Request:** `application/x-www-form-urlencoded` or multipart.

| Field | Required | Notes |
|---|---|---|
| `form_type` | yes | `contact`, `volunteer` or `newsletter`. Any other value is rejected. |
| `name` | contact, volunteer | Whitespace is collapsed |
| `email` | yes | Validated as an email address |
| `subject` | contact, volunteer | One of the subjects offered for that form in `includes/form-submissions.php` (`SUBMISSION_FORMS`) |
| `message` | contact, volunteer | Length limits apply |
| `website` | no | Honeypot. A human leaves it empty. If it has a value, the request is answered as a success and nothing is stored. |

**Responses**

- Send `Accept: application/json` to get JSON:
  ```json
  { "ok": true, "code": "ok", "message": "…", "errors": {} }
  ```
  `code` is one of `ok` (201; also 200 for a honeypot hit), `invalid` (422,
  with per-field messages in `errors`), `throttled` (429) or `method` (405).
- Without that header (a plain browser form post) the endpoint answers with
  `303 See Other` back to the form's own page, adding `?form=<code>`. The page
  shows its own message for each code.
- The return page comes from `SUBMISSION_FORMS`, never from the request, so
  the endpoint cannot be used as an open redirect.

**Side effects:** the row goes into `form_submissions` first. A newsletter
signup is then sent to Constant Contact by `includes/constant-contact.php`. The
outcome is written back to the row (`newsletter_sync_error`).

**Rate limit:** `submissionFloodLimitReached()` limits how many submissions one
IP can send in a short window.

### `GET /api/map-centers.php`: the home map

Returns a GeoJSON `FeatureCollection` of active, non-trashed rows from
`map_centers`, ordered by `display_order`.

```json
{ "type": "Feature",
  "geometry": { "type": "Point", "coordinates": [71.79128, 35.83871] },
  "properties": { "name": "…", "sponsor": "…", "village": "…",
                  "district": "Lower Chitral", "centerModel": "…", "sno": 1 } }
```

Coordinates are `[longitude, latitude]`, as GeoJSON requires.

If the table is empty or the database is unreachable, the endpoint returns the
static file `public/assets/data/locations_map1.geojson` instead. That file is
an old copy and does not reflect later edits (see `KNOWN_ISSUES.md`).

Response type: `application/geo+json; charset=utf-8`. No cache header of its
own; `public/.htaccess` sends `no-cache` for PHP responses, so admin edits show
on the next load.

### `GET /api/index.php`

Reserves the `/api/` namespace. Returns `404` with `{"error":"Not found"}`.

## Admin handlers

These are not for outside callers. Every state-changing admin request needs a
signed-in session, a capability, and a CSRF token (`requirePostWithCsrf()`).

| Path | Method | Purpose |
|---|---|---|
| `admin/login.php` | POST | Sign in. Lockout and throttling apply (see `SECURITY.md`). |
| `admin/logout.php` | POST | Sign out. Must be a POST (CSRF) |
| `admin/modules/<name>/save.php` | POST | Create or update one row |
| `admin/modules/<name>/delete.php` | POST | Move one row to Trash |
| `admin/modules/<name>/bulk.php` | POST | Bulk actions on selected rows |
| `admin/modules/<name>/reorder.php` | POST | Save a new display order |
| `admin/modules/media/upload-save.php` | POST | Upload files to `uploads/library/` |
| `admin/modules/media/replace-save.php` | POST | Replace a file and switch every reference to it |
| `admin/modules/media/delete.php` | POST | Delete an unused file (or a Library file) |
| `admin/modules/newsletter/callback.php` | GET | Constant Contact redirect target. It includes nothing and re-posts to `exchange.php` (see the comments at the top of both). |
| `admin/modules/newsletter/exchange.php` | POST | Exchanges the Constant Contact authorisation code for tokens |
| `admin/modules/newsletter/connect.php`, `disconnect.php`, `save.php` | POST | Start the connection, remove it, and save the list choice |

The redirect URI registered with Constant Contact must match
`CC_REDIRECT_URI` in `config.php` exactly, including the scheme and no trailing
slash.

## Stability

Nothing outside the site calls these endpoints except the browser pages that
ship with the site and Constant Contact's callback. Change them freely, but
keep `api/contact.php`'s response codes and field names in step with
`js/main.js` and the contact pages.
