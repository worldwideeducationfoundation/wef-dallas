# Worldwide Education Foundation website

| Folder | What it is |
|---|---|
| **`website/`** | The site. Its contents go into Hostinger's `public_html`, see [extras/DEPLOY-HOSTINGER.md](extras/DEPLOY-HOSTINGER.md). |
| `nextjs/` | The Next.js port of the site and admin, in progress. See [nextjs/PORTING.md](nextjs/PORTING.md). |
| `extras/` | Everything that is not part of the site: docs, build tools, old backups, notes. Never uploaded. |

Locally (XAMPP) the site is at <http://localhost/WEF/>. The admin panel is at
<http://localhost/WEF/admin/>.

The Donate page uses Stripe: setup, testing and go-live steps are in
[extras/DONATE-STRIPE.md](extras/DONATE-STRIPE.md).
