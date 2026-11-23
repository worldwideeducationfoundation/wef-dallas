# Donate page (Stripe)

The page at `/donate` takes gifts through **Stripe Checkout** (Stripe's hosted
page, so card data never touches our server). It replaced the old Donorbox page.

## What it does

- **Three ways to give** (Step 1 of a 3-step form):
  1. **Sponsor a Bright Beginnings center**: $2,400 one-time, **$200/month × 12**
     or **$100/month × 24**, for one or more centers. The sponsor must give a
     plaque name and can suggest a center name.
  2. **Fund part of a center**, per center: Montessori materials $800, furniture
     and carpet $800, technology $500, teacher training $200, transportation $100,
     plus an optional "other amount".
  3. **Give any amount**: one-time or monthly, $1 to $10,000.
- On every option: in honor / in memory of, recognition name, updates opt-in,
  donor name and email.
- **Prices are always recalculated on the server** from
  `includes/donate/config.php`. The browser sends only ids and counts, plus the
  amount itself for "any amount" and "other amount".
- **The 12- and 24-month plans stop by themselves.** After the first payment the
  webhook turns the subscription into a Subscription Schedule that cancels after
  the last payment. **Without the webhook they never stop.**
- **Thank-you page** `/donate-thank-you?session_id=...` reads the payment back from
  Stripe and shows the first name, amount and dedication (never the email).
  **Cancel** returns to `/donate?canceled=1` with a "you have not been charged"
  notice.
- Receipts (Checkout page, invoice footer, payment description) carry the legal
  name, EIN 81-2135633 and "No goods or services were provided in exchange for
  this contribution."

## Files

| File | What it is |
|---|---|
| `website/includes/donate/config.php` | Prices, plans, center parts, org details, impact figures. **Edit amounts here only.** |
| `website/includes/donate/pricing.php` | Validation and the quote (the money rules). |
| `website/includes/donate/stripe.php` | Stripe API calls (cURL, no SDK), webhook signature check, fixed-term schedule. |
| `website/public/api/checkout.php` | `POST` JSON → creates the Checkout Session, returns its URL. |
| `website/public/api/stripe-webhook.php` | Stripe webhook (`checkout.session.completed`). |
| `website/public/donate.php`, `donate-thank-you.php` | The pages. |
| `website/public/js/donate.js`, `css/donate.css` | Form behaviour and styles. |

The old admin **Donate Options** module and **Settings → Donate link** are no
longer used by this page.

## Settings (secrets)

Add these to the server's **existing** `config/config.php` (never commit it,
never upload the local one over it, it holds the database password):

```php
define('STRIPE_SECRET_KEY', 'sk_live_...');      // sk_test_... for testing
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');    // from the webhook endpoint
define('DONATE_BASE_URL', 'https://www.worldwideeducationfoundation.org');
```

If a setting is empty or missing in `config.php`, the code falls back to an
environment variable of the same name (e.g. `SetEnv STRIPE_SECRET_KEY ...` in
`.htaccess`), but `config.php` is the normal place.

## Testing (before going live)

1. Put an `sk_test_...` key in the local `config/config.php`.
2. For the monthly plans, forward the webhook locally:
   ```
   stripe login
   stripe listen --forward-to http://localhost/WEF/api/stripe-webhook.php
   ```
   Put the printed `whsec_...` into `STRIPE_WEBHOOK_SECRET`.
3. In test mode pay with `4242 4242 4242 4242` (any future date, any CVC) for:
   - a one-time center ($2,400)
   - a **$200 × 12** plan: `stripe listen` must show `checkout.session.completed`
     → 200, and the subscription in the Dashboard must show a schedule ending
     after 12 payments
   - parts with an "other amount"
   - "any amount", monthly, "in memory of"
4. Check the thank-you page amount, that cancel shows the notice, and that errors
   appear for an empty form, a center gift with no plaque name, and amounts under
   $1 or over $10,000.
5. At 390px wide nothing should scroll sideways.

## Going live

1. Add the live settings above to the server's `config/config.php`.
2. Stripe Dashboard, live mode → **Developers → Webhooks → Add endpoint**:
   `https://www.worldwideeducationfoundation.org/api/stripe-webhook.php`, event
   **`checkout.session.completed`**. Copy its signing secret into
   `STRIPE_WEBHOOK_SECRET`.
3. Stripe Dashboard settings, live mode:
   - **Customer emails**: turn on emails for successful payments (the receipt).
   - **Public details**: legal name "Worldwide Education Foundation", support
     email `info@wefdallas.org`, website.
   - **Invoices**: default footer with the EIN and the "no goods or services" line
     (the code also sets it per invoice).
   - **Payment methods**: turn off ones WEF does not want (Klarna, Cash App, …).
4. Upload the files (see [DEPLOY-HOSTINGER.md](DEPLOY-HOSTINGER.md)).
5. Make one small real donation: receipt email arrives, webhook shows 200, payment
   is in Dashboard → Payments. Refund it. Do the same once with a $100/month plan:
   it must show a schedule ending after 24 payments; then cancel it.

## Open items for WEF to confirm

- Hero figures in `config.php` → `impact` ("$2,400 equips one learning center",
  "~300 children per center over ten years", "$8 per child").
- `org.mailingAddress` (W159N4940 Graysland Drive, Menomonee Falls, WI 53051) for
  mail and donor-advised fund grants.
- The one-line description of each center part (`centerParts[].about`).
- `invoiceForOneTimeGifts: true` creates an invoice for one-time gifts so the
  legal footer appears on the receipt; Stripe may charge a small fee per invoice.
  Set it to false for plain receipts.
- **Scholarships** are built in but switched off in `config.php`.

## Security rules (keep them)

- Prices only come from `includes/donate/config.php`. Never trust an amount from
  the browser except the validated custom and other amounts.
- The webhook verifies the signature against the **raw** body (`php://input`).
- Never log the secret key, the webhook secret, card data or full donor details.
  The webhook logs only a safe summary to `storage/logs/donate.log`.
- `api/checkout.php` accepts JSON only, which blocks cross-site form posts.
