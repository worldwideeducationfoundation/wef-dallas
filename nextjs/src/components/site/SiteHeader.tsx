/** Port of includes/layout/site-header.php */
import { pageEnabled } from '@/lib/visibility';

type Menu = { label: string; href: string; keep: boolean; items: [string, string][] };

const NAV_MENUS: Menu[] = [
  { label: 'About', href: './#about', keep: true, items: [['our-team', 'Our Team'], ['our-mission', 'Our Mission']] },
  {
    label: 'Impact',
    href: './#impact',
    keep: true,
    items: [['success-stories', 'Success Stories'], ['impact-goals', 'Impact Goals'], ['annual-reports', 'Annual Reports']],
  },
  {
    label: 'What We Do',
    href: 'active-projects',
    keep: false,
    items: [['active-projects', 'Active Projects'], ['completed-projects', 'Completed Projects']],
  },
  { label: 'Gallery', href: 'gallery', keep: false, items: [['gallery', 'Photo Gallery'], ['video-gallery', 'Video Gallery']] },
  { label: 'Contact', href: 'contact', keep: false, items: [['contact', 'Get in Touch'], ['volunteer', 'Volunteer']] },
];

export default async function SiteHeader({ tagline = 'Empowering Through Education' }: { tagline?: string }) {
  const menus = [];
  for (const menu of NAV_MENUS) {
    const items: [string, string][] = [];
    for (const item of menu.items) if (await pageEnabled(item[0])) items.push(item);
    if (items.length === 0 && !menu.keep) continue;
    let href = menu.href;
    if (!menu.keep && !(await pageEnabled(href))) href = items[0][0];
    menus.push({ ...menu, href, items });
  }
  const donateOn = await pageEnabled('donate');

  return (
    <>
      <a href="#main-content" className="skip-link">
        Skip to main content
      </a>
      <div className="mobile-overlay" id="mobile-overlay"></div>

      <header className="header" id="main-header">
        <div className="container">
          <a href="./" className="header-logo" id="header-logo" aria-label="Worldwide Education Foundation Home">
            <img src="assets/images/logo.png" alt="Worldwide Education Foundation Logo" width={52} height={52} loading="eager" decoding="async" />
            <div className="header-logo-text">
              <span>Worldwide Education Foundation</span>
              <span>{tagline}</span>
            </div>
          </a>

          <nav className="nav-menu" id="nav-menu" aria-label="Main Navigation">
            <a href="./">Home</a>
            {menus.map((menu) =>
              menu.items.length === 0 ? (
                <a key={menu.label} href={menu.href}>
                  {menu.label}
                </a>
              ) : (
                <div className="nav-dropdown" key={menu.label}>
                  <a href={menu.href} className="dropdown-toggle" aria-expanded="false">
                    {menu.label}
                  </a>
                  <div className="dropdown-menu">
                    {menu.items.map(([slug, label]) => (
                      <a key={slug} href={slug}>
                        {label}
                      </a>
                    ))}
                  </div>
                </div>
              ),
            )}
            {donateOn && (
              <a href="donate" className="nav-donate" id="nav-donate-btn">
                Donate Now
              </a>
            )}
          </nav>

          <button type="button" className="hamburger" id="hamburger" aria-label="Toggle Navigation" aria-expanded="false" aria-controls="nav-menu">
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </header>
    </>
  );
}
