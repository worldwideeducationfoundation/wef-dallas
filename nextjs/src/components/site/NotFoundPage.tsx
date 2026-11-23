/** Port of includes/layout/not-found.php */
import SitePage from './SitePage';
import A from '@/components/A';

type Props = { title?: string; body?: string; backLink?: string; backLabel?: string };

export default function NotFoundPage({
  title = 'Page not found',
  body = 'The page you were looking for is not here. It may have been moved, or the link that brought you here may be out of date.',
  backLink,
  backLabel = 'Go back',
}: Props) {
  return (
    <SitePage seo={{ title: 'Page not found — Worldwide Education Foundation', description: 'The page you were looking for is not here.', noIndex: true }}>
      <section className="page-hero">
        <div className="container">
          <h1>{title}</h1>
          <p>{body}</p>
        </div>
      </section>

      <section className="section">
        <div className="container">
          {backLink && (
            <p>
              <A href={backLink} className="link-back">
                &larr; {backLabel}
              </A>
            </p>
          )}
          <p>
            Or start again from the <a href="./">home page</a>, browse <A href="active-projects">active projects</A>, or{' '}
            <A href="contact">get in touch</A> if you think something is missing.
          </p>
        </div>
      </section>
    </SitePage>
  );
}
