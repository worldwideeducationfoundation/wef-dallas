# Donate page (Stripe) — handoff for the PHP developer

This folder is a drop-in **Stripe donation page** for the existing WEF PHP website
(the one in `website/`, hosted on Hostinger shared hosting). It replaces the old
Donorbox page at `/donate`. No Composer, no Stripe SDK: it talks to Stripe with cURL.

Everything is in `files/`, laid out exactly like the site. Copy each file to the same
path in the site (`files/public/donate.php` -> `website/public/donate.php`, and so on).

Donors pay on **Stripe Checkout** (Stripe's own hosted page). Card details never touch
our server, so there is no PCI scope beyond that.

---

## 1. What the page does

Three ways to give, in a 3-step form:

1. **Sponsor a Bright Beginnings center**: $2,400 once, **$200/month x 12**, or
   **$100/month x 24** (the monthly plans stop by themselves, see section 6).
2. **Fund part of a center**: Montessori materials, furniture, technology, teacher
   training, transport, plus an optional "other amount".
3. **Give any amount**: one-time or monthly, $1 to $10,000.

Every option also takes: in honor / in memory of, name for recognition, updates opt-in,
donor name and email. The thank-you page (`/donate-thank-you`) reads the payment back
from Stripe and shows first name, amount and dedication (never the email).

**Prices are always worked out on the server** from `includes/donate/config.php`. The
browser sends only ids and counts (plus the amount for "any amount" / "other amount").
To change a price, edit `config.php` only.

## 2. Files and where they go

| Copy this | To this (inside the site) | What it is |
|---|---|---|
| `files/includes/donate/config.php` | `includes/donate/config.php` | Prices, plans, org details, wording. **Edit amounts here only.** |
| `files/includes/donate/pricing.php` | `includes/donate/pricing.php` | Validation and the quote (the money rules). |
| `files/includes/donate/stripe.php` | `includes/donate/stripe.php` | Stripe API calls, webhook signature check, fixed-term schedule, `donateSetting()`. |
| `files/public/donate.php` | `public/donate.php` | The page (replaces the old Donorbox one). |
| `files/public/donate-thank-you.php` | `public/donate-thank-you.php` | Thank-you page after payment. |
| `files/public/api/checkout.php` | `public/api/checkout.php` | `POST` JSON -> creates the Checkout Session, returns its URL. |
| `files/public/api/stripe-webhook.php` | `public/api/stripe-webhook.php` | Stripe webhook (`checkout.session.completed`). |
| `files/public/js/donate.js` | `public/js/donate.js` | Form behaviour, running total. |
| `files/public/css/donate.css` | `public/css/donate.css` | Styles (on top of `css/styles.css`). |
| `files/public/assets/images/donate/bright-beginnings.webp` | same path | Hero image. |

`includes/` sits **outside** the web root, `public/` is the web root. Keep it that way:
`includes/donate/` must never be reachable from the browser.

## 3. What the site must already have

These files use helpers that the WEF site already provides. Check they exist on the
live site before copying anything (they do in the current repo):

- `includes/content.php` with `e()` (HTML escape) and `siteSettings()`
- `includes/assets.php` with `asset()`, `siteBasePath()`, `asyncStylesheet()`
- `includes/bootstrap.php` (used by the two `api/` files)
- `includes/layout/head-meta.php`, `site-header.php`, `footer-full.php` (the page is
  dressed in the normal header and footer)
- `public/css/styles.css` and `public/js/main.js`
- `storage/logs/` writable by PHP (the page logs safe summaries to `storage/logs/donate.log`;
  it is created automatically)
- PHP 8.0+ with the **cURL** and **mbstring** extensions (Hostinger shared hosting has both)

If the live site is an older copy of the repo and one of these is missing, copy it from
`website/` in this repository.

**Clean URLs.** `/donate`, `/donate-thank-you` and the API URLs work through the
existing `.htaccess` rule that serves `page.php` for `/page`. If the live site has
no such rule, use `/donate.php` and `/api/checkout.php` links instead.

**Header link.** `includes/layout/site-header.php` already links the "Donate Now"
button to `donate`. If the live header sends donors to a Donorbox URL (from
admin -> Settings -> "Donate link"), point it at `donate` instead. That setting is no
longer used by this page.

## 4. Where the Stripe secrets go

Secrets live **only on the server**, in the site's existing `config/config.php` (the
file that already holds the database password). That file is not in git and must never
be uploaded over the live one from a laptop.

Add these three lines to the **server's** `config/config.php`:

```php
define('STRIPE_SECRET_KEY',     'sk_test_...');   // start with a TEST key; sk_live_... later
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');     // from the webhook endpoint (section 5)
define('DONATE_BASE_URL',       'https://www.worldwideeducationfoundation.org');
```

- `STRIPE_SECRET_KEY`: Stripe Dashboard -> Developers -> API keys -> Secret key.
  Use the **test** key (`sk_test_...`, with the dashboard in Test mode) until the
  whole flow works, then swap in the **live** key.
- `STRIPE_WEBHOOK_SECRET`: shown after you create the webhook endpoint (next section).
  Test mode and live mode each have their own, so there is one per key.
- `DONATE_BASE_URL`: the public address of the site, no trailing slash. Stripe sends
  donors back to `<this>/donate-thank-you` and `<this>/donate?canceled=1`.

If you cannot edit `config.php`, the same three names also work as environment variables
(for example `SetEnv STRIPE_SECRET_KEY sk_test_...` in `.htaccess`, or the host's panel).
`config.php` wins if both are set. Without `STRIPE_SECRET_KEY` the page still shows, but
"Continue to payment" answers with an error.

**Never** put a key in a PHP file in the repository, in JavaScript, in a commit, or in a
chat message. Only the three lines above, on the server.

## 5. Stripe Dashboard setup

1. **Webhook** (Developers -> Webhooks -> Add endpoint):
   - URL: `https://www.worldwideeducationfoundation.org/api/stripe-webhook.php`
   - Event: **`checkout.session.completed`** (only this one)
   - Copy the endpoint's **Signing secret** (`whsec_...`) into `STRIPE_WEBHOOK_SECRET`.
2. **Settings -> Customer emails**: turn on emails for successful payments (this is the
   donor's receipt).
3. **Settings -> Public details**: legal name "Worldwide Education Foundation", support
   email, website.
4. **Settings -> Billing -> Invoices**: default footer with the EIN 81-2135633 and
   "No goods or services were provided in exchange for this contribution." (the code
   also sets this on each invoice).
5. **Settings -> Payment methods**: switch off any method WEF does not want
   (Klarna, Cash App, ...).

Do steps 1-5 once in **Test mode**, then repeat in **Live mode** (live mode has its own
webhook endpoint and its own signing secret).

## 6. Why the webhook matters

The 12- and 24-month plans are created as ordinary monthly subscriptions. After the
first payment the webhook turns each one into a Subscription Schedule that **cancels
itself after the last payment**. **Without a working webhook, a "12 months" donor would
be charged every month forever.** The webhook must return 200; check it in Stripe
(Webhooks -> the endpoint -> recent deliveries).

The webhook checks Stripe's signature against the **raw** request body. If you put the
site behind something that rewrites the body (a WAF rule, a proxy that re-encodes),
signature checks will fail with 400.

## 7. Testing (test mode)

1. Put `sk_test_...` and the test `whsec_...` in the server `config.php`.
2. Open `/donate`. Pay with card `4242 4242 4242 4242`, any future date, any CVC, for:
   - a one-time center ($2,400)
   - **$200 x 12**: in Stripe the subscription must show a schedule that ends after 12
     payments, and the webhook delivery must show **200**
   - a part with an "other amount"
   - "any amount", monthly, "in memory of"
3. The thank-you page must show the right first name and amount. Cancelling on Stripe's
   page must return to `/donate?canceled=1` with "you have not been charged".
4. Errors must show for an empty form, a center gift with no plaque name, and amounts
   under $1 or over $10,000.
5. At 390px wide nothing should scroll sideways.

## 8. Going live

1. Replace the test values in `config.php` with `sk_live_...` and the **live** `whsec_...`.
2. Make one small real donation: receipt email arrives, webhook shows 200, payment is in
   Dashboard -> Payments. Refund it.
3. Do the same once with a $100/month plan: it must show a schedule ending after 24
   payments. Then cancel it.

## 9. If the site has a Content-Security-Policy

The current policy (`public/.htaccess`) already works: the page loads only its own
`donate.js` and `donate.css` and talks only to its own `/api/checkout.php`. Stripe
Checkout is reached by a normal browser redirect, so `connect-src` and `form-action`
need no Stripe entries. Do not add inline `<script>` blocks to these pages: the policy
blocks them.

## 10. Security rules (keep them)

- Prices come only from `includes/donate/config.php`. Never trust an amount from the
  browser except the validated "any amount" / "other amount" values.
- Never log the secret key, the webhook secret, card data or full donor details. The
  code logs only safe summaries (`storage/logs/donate.log`; it is not rotated, so delete or archive it now and then).
- `api/checkout.php` accepts JSON only, which blocks cross-site form posts.
- Keep `storage/` and `includes/` out of the web root.

## 11. Things WEF should confirm in `config.php`

- Hero figures under `impact`: "$2,400 equips one learning center", "~300 children per
  center over ten years", "$8 per child".
- `org.mailingAddress` (for mailed checks and donor-advised-fund grants).
- The one-line description of each center part (`centerParts[].about`).
- `invoiceForOneTimeGifts`: `true` creates an invoice for one-time gifts so the legal
  footer appears on the receipt (Stripe may charge a small fee per invoice); set `false`
  for plain receipts.
- Scholarships are built in but switched off.

## 12. Quick troubleshooting

| Symptom | Likely cause |
|---|---|
| "Continue to payment" shows an error | `STRIPE_SECRET_KEY` missing or wrong mode (test key with live dashboard, or the reverse) |
| Webhook shows 400 | wrong `STRIPE_WEBHOOK_SECRET` (test vs live), or the body was altered before PHP |
| Webhook shows 500 / nothing arrives | URL wrong, or `storage/logs/donate.log` has the reason; check PHP has cURL |
| Donor returns to the wrong site | `DONATE_BASE_URL` wrong |
| Page unstyled / form dead | `donate.css` / `donate.js` not copied, or `asset()` helper missing |
| 404 on `/donate` | clean-URL rule missing; use `/donate.php` |
