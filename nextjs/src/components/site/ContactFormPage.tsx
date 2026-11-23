/** Shared body of public/contact.php and public/volunteer.php (they differ only in wording). */
import PageHero from './PageHero';
import Html from './Html';
import SitePage from './SitePage';
import { block, blockRich } from '@/lib/blocks';
import { siteSettings } from '@/lib/content';
import { SUBMISSION_MAX_EMAIL, SUBMISSION_MAX_MESSAGE, SUBMISSION_MAX_NAME, submissionResultMessage, submissionSubjects } from '@/lib/forms';
import { css } from '@/lib/style';

type Props = {
  formType: 'contact' | 'volunteer';
  seo: { title: string; description: string };
  formCode?: string;
  subjectLabel: string;
  subjectPlaceholder: boolean;
  messageLabel: string;
  messagePlaceholder: string;
  submitLabel: string;
};

export default async function ContactFormPage(p: Props) {
  const page = p.formType;
  const settings = await siteSettings();
  const result = submissionResultMessage(p.formCode ?? null);
  const subjects = submissionSubjects(p.formType);

  return (
    <SitePage seo={p.seo}>
      <PageHero page={page}>
        <div className="container">
          <Html as="h1" html={await block(page, 'page_title')} />
          <Html as="p" html={await blockRich(page, 'intro')} />
        </div>
      </PageHero>

      <section className="section contact" id="contact" style={css('background: var(--clr-off-white);')}>
        <div className="container">
          <div className="contact-grid">
            <div className="contact-info reveal-left" id="contact-info">
              <Html as="h3" html={await block(page, 'connect_title')} />
              <Html as="p" html={await blockRich(page, 'connect_text')} />

              <div className="contact-detail">
                <div className="contact-detail-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M12 22s7-7.58 7-12A7 7 0 0 0 5 10c0 4.42 7 12 7 12z" />
                    <circle cx="12" cy="10" r="2.5" />
                  </svg>
                </div>
                <div className="contact-detail-text">
                  <h4>Address</h4>
                  <p>{settings.contact_address ?? 'W159N4940 Graysland Drive, Menomonee Falls, WI 53051 United States'}</p>
                </div>
              </div>
              <div className="contact-detail">
                <div className="contact-detail-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a2 2 0 0 1-2.18 2A16 16 0 0 1 2 6.18 2 2 0 0 1 4 4z" />
                  </svg>
                </div>
                <div className="contact-detail-text">
                  <h4>Phone</h4>
                  <p>{settings.contact_phone ?? '+1 (214) 502-8388'}</p>
                </div>
              </div>
              <div className="contact-detail">
                <div className="contact-detail-icon" aria-hidden="true">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <path d="M2 6l10 7 10-7" />
                  </svg>
                </div>
                <div className="contact-detail-text">
                  <h4>Email</h4>
                  <p>{settings.contact_email ?? 'info@wefdallas.org'}</p>
                </div>
              </div>
            </div>

            <form className="contact-form reveal-right" id="contact-form" method="post" action="api/contact.php" noValidate>
              <input type="hidden" name="form_type" value={p.formType} />
              <div
                className={'form-status' + (result ? ' form-status--' + result.type : '')}
                data-form-status=""
                role="status"
                aria-live="polite"
                hidden={!result}
              >
                {result ? result.text : ''}
              </div>
              <p className="form-required-note">All fields are required.</p>
              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="contact-name">Full Name</label>
                  <input type="text" id="contact-name" name="name" placeholder="Your full name" maxLength={SUBMISSION_MAX_NAME} autoComplete="name" required />
                  <p className="form-error" data-error-for="name" hidden></p>
                </div>
                <div className="form-group">
                  <label htmlFor="contact-email">Email Address</label>
                  <input type="email" id="contact-email" name="email" placeholder="your@email.com" maxLength={SUBMISSION_MAX_EMAIL} autoComplete="email" required />
                  <p className="form-error" data-error-for="email" hidden></p>
                </div>
              </div>
              <div className="form-group">
                <label htmlFor="contact-subject">{p.subjectLabel}</label>
                <select id="contact-subject" name="subject" required defaultValue={p.subjectPlaceholder ? '' : undefined}>
                  {p.subjectPlaceholder && (
                    <option value="" disabled>
                      Select a topic
                    </option>
                  )}
                  {Object.entries(subjects).map(([value, label]) => (
                    <option value={value} key={value}>
                      {label}
                    </option>
                  ))}
                </select>
                <p className="form-error" data-error-for="subject" hidden></p>
              </div>
              <div className="form-group">
                <label htmlFor="contact-message">{p.messageLabel}</label>
                <textarea id="contact-message" name="message" placeholder={p.messagePlaceholder} rows={5} maxLength={SUBMISSION_MAX_MESSAGE} required></textarea>
                <p className="form-error" data-error-for="message" hidden></p>
              </div>
              <div className="form-hp" aria-hidden="true">
                <label htmlFor="contact-website">Leave this field empty</label>
                <input type="text" id="contact-website" name="website" tabIndex={-1} autoComplete="off" />
              </div>
              <button type="submit" className="btn btn-accent btn-block" id="contact-submit">
                {p.submitLabel}
              </button>
            </form>
          </div>
        </div>
      </section>
    </SitePage>
  );
}
