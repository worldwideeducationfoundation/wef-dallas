# WEF — Security Posture

What protects what, and what is deliberately still open. Written at the end of
Phase 2 (30 July 2026). Update when any control here changes.

## OWASP Top 10 (2021) status

| | Category | State | Notes |
|---|---|---|---|
| A01 | Broken Access Control | Good | Capability model drives both enforcement and UI. Closed capability set — an unknown capability string is denied, and throws in development. Directory denies on 7 paths. |
| A02 | Cryptographic Failures | Good | HTTPS redirect + HSTS; session cookie `Secure` derived from the request, not from config. `password_hash` with rehash-on-login. |
| A03 | Injection | Good | 100% parameterised; `PDO::ATTR_EMULATE_PREPARES => false`. The four interpolation points (table names, sort columns, LIMIT, label columns) are allow-listed or int-cast. |
| A04 | Insecure Design | Partial | Contact/volunteer/newsletter forms still have no handler. Public and admin tiers still share one DB credential. |
| A05 | Security Misconfiguration | Good | Full header set on both tiers. `WEF_DEBUG` cannot be true off-localhost. `X-Powered-By` removed. |
| A06 | Vulnerable Components | Good | One third-party dependency (Leaflet 1.9.4), pinned and SRI-hashed. No package manager, no transitive tree. |
| A07 | Auth Failures | Good | Per-email **and** per-IP throttling, 12-char password floor with deny list, idle + absolute session expiry, DB revalidation every 5 min. No MFA yet. |
| A08 | Integrity Failures | Good | Leaflet CSS and JS both carry `integrity` + `crossorigin`. Uploads MIME-sniffed, renamed, PHP execution disabled in the uploads directory. |
| A09 | Logging Failures | Partial | Both tiers log with rotation; login throttling and spraying are logged. **Nothing alerts** — no email or webhook on FATAL. |
| A10 | SSRF | N/A | The app makes no outbound requests. |

## Security headers

Set from `.htaccess` via `mod_headers`, not `<meta>`, because
`X-Frame-Options` and CSP `frame-ancestors` cannot be expressed in a meta tag —
which is why both tiers were framable until now.

| Header | Public | Admin |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'` + allow-listed CDN hosts, `frame-ancestors 'self'` | Same-origin only, `frame-ancestors 'none'` |
| `X-Frame-Options` | `SAMEORIGIN` | `DENY` |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | `no-referrer` |
| `Permissions-Policy` | geolocation/mic/camera/payment/usb off | same |
| `Strict-Transport-Security` | 1 year, `env=HTTPS` only | same |
| `Cross-Origin-Opener-Policy` | `same-origin` | `same-origin` |
| `Cache-Control: no-store` | — | on `*.php` |

The `<meta>` CSP in `includes/layout/head-meta.php` is kept as a fallback for
hosting that ignores `.htaccess` (e.g. nginx without Apache rules). When both are
present the browser enforces the **intersection**, so the source lists are
identical on purpose — **edit one, edit both**.

### Why `'unsafe-inline'` is still there

Not an oversight. The public pages carry 99 inline `style=""` attributes, 24
inline event handlers (`onclick`/`onerror`/`onchange`) and 2 inline `<script>`
blocks. Dropping `'unsafe-inline'` means rewriting all of those into external
handlers first — a code change with real regression risk, not a header change.
Hashes do not help: inline event handlers need `'unsafe-hashes'`, and 24 of them
would be unmaintainable. This is the single biggest remaining CSP win.

## HTTPS

`.htaccess` redirects to HTTPS, with three conditions that each matter:

- `%{HTTPS} off` — only redirect plain HTTP.
- `X-Forwarded-Proto != https` — Hostinger may terminate TLS at a proxy and
  forward over HTTP. Without this the rule redirects **forever**.
- host is not `localhost` / `127.0.0.1` / `*.local` — XAMPP has no certificate,
  so redirecting local development would break it outright.

Session cookies get `Secure` from `requestIsHttps()`, which checks `$_SERVER['HTTPS']`,
`X-Forwarded-Proto` and port 443. Previously `Secure` came only from
`APP_FORCE_HTTPS_COOKIE`, which is `false` in the shipped config — so a deploy
that did not flip that one line by hand would have sent the admin session cookie
over plaintext with no warning. `APP_FORCE_HTTPS_COOKIE` can still force it on;
it can no longer be the reason it is off.

## Debug output cannot leak in production

`APP_DEBUG` in config is now only an *input*. `includes/environment.php` ANDs it
with "is this host local" and publishes the result as `WEF_DEBUG`, which is the
only flag any code reads.

Verified with an identical config file: `localhost` shows the stack trace;
`Host: www.worldwideeducationfoundation.org` returns HTTP 503 with a generic
page and zero leaked internals. Deploying the development config to Hostinger is
no longer a disclosure.

Both config spellings are accepted (`APP_DEBUG_CONFIGURED`, and legacy
`APP_DEBUG`), so an existing `config.php` keeps working — with the host gate
applied either way.

## The public write endpoint — `api/contact.php`

The one place an **unauthenticated visitor can write to the database**. Until
the Contact and Volunteer forms were wired up, the public tier was read-only, so
this is a genuinely new surface and is worth stating plainly.

| Control | How |
|---|---|
| Input validation | `submissionValidate()` — server-side, always. The `required` / `type="email"` attributes help the person filling the form in and are absent for anything posting directly. |
| Allow-listed values | `form_type` and `subject` must exist in `SUBMISSION_FORMS`. An unrecognised `form_type` rejects the whole request rather than defaulting. |
| Flood control | 5 submissions per IP per 15 minutes, counted from `form_submissions` itself. |
| Bot filter | Hidden honeypot field, answered with a plain success — telling a bot it was detected is free tuning feedback. |
| Length caps | Enforced in PHP against the column widths, so an over-long value is a clear error rather than a strict-mode row rejection. |
| Open redirect | None possible. The no-JS redirect target is looked up from `SUBMISSION_FORMS` by `form_type`; there is no `return_to` field. |
| Reflected content | The `?form=<code>` result banner is a **key into a fixed message map**, never text from the URL. A visitor can only choose which of four fixed sentences appears. |
| Stored XSS | Messages are rendered with `e()` into a `white-space: pre-wrap` block — never `nl2br`, never any rich-text helper. |
| CSV injection | Export prefixes any cell starting `=`, `+`, `-`, `@` or a tab with `'`. Without it, a visitor could put a DDE payload in the message box and have it execute on the machine of whoever opens the export. |
| Error leakage | `includes/bootstrap.php` is required first, so a `PDOException` cannot print DB credentials to whoever posted the form. |

Deliberately **no CSRF token**. The public site has no session and the endpoint
performs no privileged action on anyone's behalf — a forged cross-site POST here
achieves exactly what a direct POST achieves, so a token would add a session
cookie to every public page in exchange for nothing. The flood limit is the
control that actually bounds abuse.

`X-Forwarded-For` is **not** trusted for the client address. Behind an unknown
proxy it is attacker-controlled, and honouring it would let one client fake a
new address per request and walk straight past the per-IP limit.

## Constant Contact — the one outbound integration

`includes/constant-contact.php` pushes newsletter signups to a Constant Contact
mailing list. It is the first thing this codebase does that **leaves the
server**, and the first **third-party credential it stores**, so the decisions
are recorded here rather than only in the file.

**There is no API key.** Constant Contact's v3 API offers no client-credentials
or machine-to-machine flow — every OAuth2 flow it supports requires a human to
approve in a browser. That single fact is why this integration has an admin
module, a callback, and stored tokens at all.

| Concern | How it is handled |
|---|---|
| Where secrets live | `CC_CLIENT_ID` / `CC_CLIENT_SECRET` in `config/config.php` (gitignored, not web-servable). Access + refresh tokens in the `constant_contact` table, because they rotate at runtime and config is not writable. |
| Backup exposure | Deliberate split. `admin/database/backup.php` produces a **downloadable dump**, so the refresh token is in backups — but a refresh token is inert without the client secret to Basic-auth the token request, and the secret is not in the database. **Do not move the secret into the DB.** |
| CSRF on the OAuth flow | The `state` parameter: 32 random bytes written to the session by `connect.php`, required back unchanged by `exchange.php`, compared with `hash_equals`, expiring after 15 minutes and consumed on first use. Without it, an attacker completes an authorisation against *their* account and tricks an admin into wiring WEF's signups to *their* mailing list. |
| `SameSite=Strict` vs the callback | The return trip from `constantcontact.com` is cross-site, so the session cookie is **not sent**. `callback.php` therefore includes nothing, starts no session (a fresh one would overwrite the admin's real cookie and log them out) and re-POSTs to `exchange.php` — a same-site request, where the cookie *is* sent and the capability check works normally. Relaxing the cookie to `Lax` would have weakened all 56 admin screens to simplify one. |
| Refresh token rotation | Each refresh invalidates the token used. `ccAccessToken()` refreshes inside a transaction holding `SELECT … FOR UPDATE` on the singleton row, so two concurrent signups cannot both spend the same refresh token and leave the dead one stored. |
| Token leakage into logs | `ccRedact()` strips JWTs and `"*_token":"…"` pairs from anything logged or written to `last_error`, which is rendered on the admin screen. |
| TLS | `CURLOPT_SSL_VERIFYPEER` and `VERIFYHOST` stay on, and redirects are not followed — following one would replay the `Authorization` header at another host. |
| Privilege | A separate `newsletter.manage` capability, granted to administrators and withheld from editors. Whoever holds it can point every future subscriber at a list of their choosing, which is not a content-editing decision. |
| Consent | Only `form_type = 'newsletter'` rows are ever pushed, enforced in `retry-sync.php` as well as the UI. Someone who used the Contact form asked a question; adding them to a mailing list would be the unsolicited subscription anti-spam law exists to prevent. |
| Failure containment | `ccSyncSubmission()` never throws and runs **after** the row is committed. Constant Contact being down costs a list membership that is retryable from the CMS, never the address itself. |

`tools/constant-contact-check.php` (CLI only) reports configuration, connection,
reachability and the unsynced backlog. Its credential probe sends a deliberately
invalid refresh token: `invalid_client` means the key/secret were rejected,
`invalid_grant` means they were accepted. Nothing is created or consumed.

## Rate limiting

| Scope | Ceiling | Window |
|---|---|---|
| Per email (login) | 5 failures | 15 min |
| Per IP, across all emails (login) | 20 failures | 15 min |
| Spray warning (log only) | 5 distinct emails | 15 min |
| Per IP, public form submissions | 5 submissions | 15 min |

The per-IP rule is the new one. The email rule alone left password **spraying**
unthrottled — one password against unlimited addresses never tripped a limit.
`login_attempts.ip_address` was already recorded and `idx_login_attempts_ip_time`
already existed for this query; nothing read it. The IP ceiling is higher than
the email one on purpose, because a whole office behind one NAT address shares it.

Both limits return identical wording — a distinct "your IP is blocked" message
would tell an attacker which limit they hit and how to pace around it.

## Passwords

`passwordPolicyError()` in `admin/includes/auth.php` is the single source of
truth, shared by the Users module and `database/create-admin.php`.

- 12-character minimum, measured with `mb_strlen`. The old check was
  `strlen($password) < 8` — bytes, so a passphrase with accented characters was
  measured longer than it is and could pass at fewer than 8 real characters.
- Rejects a deny list, repeated characters, consecutive sequences, and the
  account's own name or email local-part.
- **No composition rules** (must contain a symbol/digit/capital). They push
  people toward `P@ssw0rd1` without adding entropy; NIST SP 800-63B advises
  against them and recommends length plus a known-bad check instead.
- `password_needs_rehash()` runs on every successful login, so stored hashes
  follow PHP's current default cost. Verified: a cost-04 hash was upgraded to
  cost-10 on login.

Still missing: no self-service password change (an admin must ask a super admin
to reset it, which means the password travels over chat), no reset flow, no MFA.

## Logging

| Tier | File | Rotation |
|---|---|---|
| Public | `storage/logs/site-error.log` | 5 MB × 5 generations |
| Admin | `admin/logs/admin-error.log` | 5 MB × 5 generations |

Both directories are `Require all denied`. `admin/database/prune-logs.php` (CLI
only) trims `activity_logs` (365 days) and `login_attempts` (30 days) in batches;
cron it monthly. `activity_logs` is the table `migrate.php` itself calls "the one
table that grows without bound", and nothing bounded it until now.

**The gap: nothing alerts.** A FATAL is written to a file no one reads. An email
or webhook on FATAL/EXCEPTION is the next logging change worth making.

## Remaining work, in priority order

1. **Version control and backups** — still the largest risk overall, and not a
   Phase 2 item. No git history on the running code, no DB or upload backup.
2. **Drop `'unsafe-inline'`** — needs the 24 inline handlers and 2 inline scripts
   extracted first.
3. **Alerting on FATAL** — see above.
4. **Self-service password change + MFA for `super_admin`.**
5. **Separate DB credentials per tier** — the public site never writes;
   `includes/content.php` documents this and it was never provisioned.
6. **Wire up the forms** — contact, volunteer and newsletter still discard input.
7. `ServerTokens Prod` + `ServerSignature Off` in `httpd.conf` — Apache still
   advertises its version in the `Server` header, and that cannot be removed
   from `.htaccess`.
8. **`RewriteBase /WEF/`** must become `/` when deployed at a domain root.
