# WEF admin API

The API does everything the admin site does. It is meant for scripts and for
Claude, so content can be changed without logging in to the admin.

- **Base URL:** `https://www.worldwideeducationfoundation.org/api/admin/v1`
  (locally `http://localhost:3100/api/admin/v1`).
- **Auth:** send `Authorization: Bearer <ADMIN_API_KEY>` on every request.
- **The key:** the `ADMIN_API_KEY` environment variable on the server, at least 32
  random characters. If it is empty, the API is switched off. To revoke access,
  change or clear the key and restart the app.
- **Self-documenting:** `GET /api/admin/v1` returns every endpoint, every content type
  and every field, with its type, limits and allowed values.
- **Logging:** every change appears in the admin **Activity log**, marked `(API)`.

Generate a key with:
```
node -e "console.log(require('crypto').randomBytes(32).toString('base64url'))"
```

## Content types (`/resources/{type}`)

`programs`, `program-photos`, `completed-projects`, `team`, `gallery`, `hero-slides`,
`partners`, `reports`, `success-stories`, `updates`, `video-gallery`, `mission-projects`,
`impact-milestones`, `map-centers`, `donate-options`, `sponsor-groups`, `sponsors`.

| Do this | Request |
|---|---|
| List | `GET /resources/team?status=active&q=daya&limit=50` |
| Read one | `GET /resources/team/12` |
| Create | `POST /resources/team` |
| Change some fields | `PATCH /resources/team/12` |
| Move to Trash | `DELETE /resources/team/12` |
| Restore from Trash | `POST /resources/team/12/restore` |
| Delete permanently (must already be in Trash) | `DELETE /resources/team/12?purge=1` |
| Reorder | `POST /resources/team/reorder` with `{"ids":[5,2,9]}` |
| Publish, draft or trash several items | `POST /resources/team/bulk` with `{"action":"publish","ids":[5,2]}` |

The validation rules and error messages are the same as in the admin forms. A
failed request returns `{"ok": false, "errors": [...]}` with status 422.

### Files (photos, PDFs)

You can send a file in two ways:

- **multipart/form-data:** `-F image=@photo.jpg`
- **JSON:** use `"image": {"url": "https://example.org/photo.jpg"}`, or
  `"image": {"base64": "...", "filename": "photo.jpg"}`

Limits and behaviour:

- Images can be JPEG, PNG or WebP, up to 5MB. Resized copies are made automatically.
- PDFs can be up to 15MB.
- To remove an optional file, send `"remove_image": true`.

## Other areas

| Area | Endpoints |
|---|---|
| Page text (content blocks) | `GET /content-blocks?page=home`. `PATCH /content-blocks/home {"section1_title":"..."}`. Reset a block to its default with `{"reset":["section1_title"]}`. Image blocks and `header_image` are sent as files. |
| Site settings | `GET /settings`, `PATCH /settings {"contact_phone":"..."}` |
| Pages on/off | `GET /pages`, `PATCH /pages/video-gallery {"enabled": false}` |
| Form submissions | `GET /submissions?form_type=contact&status=new`, `GET /submissions/{id}`, `PATCH /submissions/{id} {"status":"replied","admin_note":"..."}`, `DELETE /submissions/{id}`, `POST /submissions/{id}/restore`, `GET /submissions/export.csv` |
| Trash | `GET /trash` |
| Activity log | `GET /activity?limit=50` |
| Admin users | `GET /users`, `POST /users {"name","email","role","status","password"}`, `PATCH /users/{id}`, `DELETE /users/{id}` |
| Newsletter (Constant Contact) | `GET /newsletter`, `GET /newsletter/lists`, `PATCH /newsletter {"list_id":"..."}` |
| Upload a file only | `POST /uploads` with `folder` and `file`. Returns `{"path":"pages/abc.jpg"}`. |

## Examples

```bash
API=https://www.worldwideeducationfoundation.org/api/admin/v1
KEY=...   # ADMIN_API_KEY

# Add a team member with a photo
curl -H "Authorization: Bearer $KEY" -F name="Jane Doe" -F role="Advisor" \
     -F section="Advisory Board Members" -F status=active -F image=@jane.jpg \
     $API/resources/team

# Change the home page "Who We Are" heading
curl -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
     -X PATCH -d '{"section1_title":"About WEF"}' $API/content-blocks/home

# Publish a project update
curl -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
     -d '{"title":"New center opens in Booni","content":"...","status":"active"}' \
     $API/resources/updates
```
