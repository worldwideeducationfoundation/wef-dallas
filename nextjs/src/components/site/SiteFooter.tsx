/** Port of includes/layout/footer-full.php */
import A from '@/components/A';
import { block, blockRich } from '@/lib/blocks';
import { siteSettings } from '@/lib/content';
import { SUBMISSION_MAX_EMAIL } from '@/lib/forms';

export default async function SiteFooter() {
  const s = await siteSettings();
  const social = {
    facebook: s.facebook_url ?? 'https://www.facebook.com/WorldwideEducationFoundationWEF',
    twitter: s.twitter_url ?? 'https://x.com/WEFglobaledu',
    instagram: s.instagram_url ?? 'https://www.instagram.com/worldwideeducationfoundation/',
    linkedin: s.linkedin_url ?? 'https://www.linkedin.com/company/worldwide-education-foundation',
    youtube: s.youtube_url ?? 'https://www.youtube.com/@wefdallasorg',
  };
  const [tagline, aboutText, newsletterTitle, newsletterText, legal1, legal2] = await Promise.all([
    block('footer', 'tagline'),
    blockRich('footer', 'about_text'),
    block('footer', 'newsletter_title'),
    blockRich('footer', 'newsletter_text'),
    blockRich('footer', 'legal_text1'),
    blockRich('footer', 'legal_text2'),
  ]);

  return (
    <footer className="footer" id="footer">
      <div className="container">
        <div className="footer-grid">
          <div className="footer-about">
            <div className="footer-logo footer-logo--lead">
              <img src="assets/images/logo.png" alt="Worldwide Education Foundation logo" width={84} height={84} loading="lazy" decoding="async" />
              <div className="footer-logo-text">
                <span>Worldwide Education Foundation</span>
                <p className="footer-tagline" dangerouslySetInnerHTML={{ __html: tagline }} />
              </div>
            </div>
            <p dangerouslySetInnerHTML={{ __html: aboutText }} />
          </div>

          <div className="footer-links">
            <h4>Quick Links</h4>
            <a href="./#home">Home</a>
            <a href="./#about">About Us</a>
            <a href="./#impact">Our Impact</a>
            <A href="active-projects">What We Do</A>
            <A href="gallery">Gallery</A>
            <a href="./#contact">Contact</a>
          </div>

          <div className="footer-links">
            <h4>Pages</h4>
            <A href="our-team">Our Team</A>
            <A href="our-mission">Our Mission</A>
            <A href="active-projects">Active Projects</A>
            <A href="completed-projects">Completed Projects</A>
            <A href="gallery">Photo Gallery</A>
          </div>

          <div className="footer-newsletter">
            <h4 dangerouslySetInnerHTML={{ __html: newsletterTitle }} />
            <p dangerouslySetInnerHTML={{ __html: newsletterText }} />
            <form className="footer-newsletter-form" method="post" action="api/contact.php" noValidate>
              <input type="hidden" name="form_type" value="newsletter" />
              <label className="sr-only" htmlFor="newsletter-email">
                Email address for newsletter
              </label>
              <input
                type="email"
                id="newsletter-email"
                name="email"
                placeholder="Your email address"
                maxLength={SUBMISSION_MAX_EMAIL}
                autoComplete="email"
                required
              />
              <div className="form-hp" aria-hidden="true">
                <label htmlFor="newsletter-website">Leave this field empty</label>
                <input type="text" id="newsletter-website" name="website" tabIndex={-1} autoComplete="off" />
              </div>
              <button type="submit" aria-label="Subscribe">
                &rarr;
              </button>
            </form>
            <span className="newsletter-feedback" id="newsletter-feedback" aria-live="polite"></span>
          </div>
        </div>

        <div className="footer-bottom">
          <div className="footer-bottom-info">
            <p className="footer-legal" dangerouslySetInnerHTML={{ __html: legal1 }} />
            <p className="footer-legal" dangerouslySetInnerHTML={{ __html: legal2 }} />
            <p className="footer-copyright">&copy; {new Date().getFullYear()} Worldwide Education Foundation. All rights reserved.</p>
          </div>
          <div className="footer-social">
            <a href={social.facebook} aria-label="Facebook" target="_blank" rel="noopener noreferrer">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" />
              </svg>
            </a>
            <a href={social.twitter} aria-label="X (Twitter)" target="_blank" rel="noopener noreferrer">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
              </svg>
            </a>
            <a href={social.instagram} aria-label="Instagram" target="_blank" rel="noopener noreferrer">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
              </svg>
            </a>
            <a href={social.linkedin} aria-label="LinkedIn" target="_blank" rel="noopener noreferrer">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
              </svg>
            </a>
            <a href={social.youtube} aria-label="YouTube" target="_blank" rel="noopener noreferrer">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21.582 6.186a2.636 2.636 0 0 0-1.85-1.875C17.95 3.86 12 3.86 12 3.86s-5.95 0-7.732.451a2.636 2.636 0 0 0-1.85 1.875C2 7.994 2 12 2 12s0 4.006.418 5.814a2.636 2.636 0 0 0 1.85 1.875C6.05 20.14 12 20.14 12 20.14s5.95 0 7.732-.451a2.636 2.636 0 0 0 1.85-1.875C22 16.006 22 12 22 12s0-4.006-.418-5.814zM9.99 15.464V8.536L15.936 12z" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
}
