/** Port of public/completed-project-details.php (?slug=...) */
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import SitePage from '@/components/site/SitePage';
import { publishedCompletedProjectBySlug, safeYoutubeUrl, uploadUrl } from '@/lib/content';
import { e } from '@/lib/html';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

type Props = { searchParams: Promise<{ slug?: string | string[] }> };

async function load(props: Props) {
  const slug = (await props.searchParams).slug;
  return publishedCompletedProjectBySlug(typeof slug === 'string' ? slug : '');
}

export async function generateMetadata(props: Props): Promise<Metadata> {
  const project = await load(props);
  if (!project) return {};
  return pageMetadata({ title: `${project.title} — Worldwide Education Foundation`, description: project.summary });
}

/** The PHP mini-markup: "### heading", "![alt](src)", "- item", line breaks. */
function formatDescription(text: string): string {
  let d = e(text);
  d = d.replace(/### (.*)/g, '<h3>$1</h3>');
  d = d.replace(/!\[(.*?)\]\((.*?)\)/g, (_m, alt: string, srcRaw: string) => {
    const src = srcRaw.trim();
    if (!/^(?!.*\.\.)(?:(?:admin\/uploads|assets)\/[A-Za-z0-9/_.%, -]+|https:\/\/[^\s"'<>]+)$/.test(src)) return alt;
    return `<img src="${src}" alt="${alt}" loading="lazy" style="max-width: 100%; height: auto; border-radius: var(--radius-md); margin: var(--space-10) auto; display: block; box-shadow: var(--shadow-md);">`;
  });
  d = d.replace(/^- (.*)$/gm, '<li>$1</li>');
  d = d.replace(/(<li>.*<\/li>(\r?\n)*)+/g, '<ul>$&</ul>');
  d = d.replace(/(\r\n|\n\r|\r|\n)/g, '<br />$1');
  d = d.replace(/<br \/>\s*<h3>/g, '<h3>');
  d = d.replace(/<\/h3>\s*<br \/>/g, '</h3>');
  d = d.replace(/<br \/>\s*<ul>/g, '<ul>');
  d = d.replace(/<\/ul>\s*<br \/>/g, '</ul>');
  return d;
}

export default async function CompletedProjectDetailsPage(props: Props) {
  await requirePageEnabled('completed-project-details');
  const project = await load(props);
  if (!project) notFound();

  const mainImage = uploadUrl(project.image_path);
  const secondaryImage = uploadUrl(project.secondary_image_path ? project.secondary_image_path : project.image_path);
  const tertiaryImage = uploadUrl(project.tertiary_image_path ?? null);
  const hasTertiary = tertiaryImage !== null;
  const mapImage = uploadUrl(project.map_image_path ?? null);
  const video = safeYoutubeUrl(project.video_url ?? null);
  const seo = { title: `${project.title} — Worldwide Education Foundation`, description: project.summary };

  return (
    <SitePage seo={seo} styles={['css/pages/completed-project-details.css']}>
      <section className="project-hero">
        <div className="project-hero-bg" style={mainImage ? { backgroundImage: `url('${mainImage}')` } : undefined}></div>
        <div className="project-hero-gradient"></div>
        <div className="container project-hero-content reveal">
          <span className="ph-badge">Completed Project</span>
          <h1 className="ph-title">{project.title}</h1>
          <div className="ph-meta">
            <div className="ph-meta-item">
              <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              {project.location}
            </div>
            <div className="ph-meta-item">
              <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" strokeWidth="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              Completed in {project.completion_year}
            </div>
            <div className="ph-meta-item">
              <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" strokeWidth="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
              </svg>
              {project.category}
            </div>
          </div>
        </div>
      </section>

      <section className="project-body">
        <div className="container">
          <div className="pb-grid">
            <div
              className="pb-main reveal"
              dangerouslySetInnerHTML={{
                __html:
                  '<p class="lead-text" style="font-size: 1.2em; color: var(--clr-primary-dark); font-weight: 500; margin-bottom: var(--space-14);">' +
                  e(project.summary) +
                  '</p>' +
                  formatDescription(String(project.description ?? '')),
              }}
            />
            <aside className="pb-sidebar reveal-right">
              <div className="info-panel">
                <h4 className="ip-title">Measurable Impact</h4>
                {[1, 2, 3].map((n) => (
                  <div className="ip-stat" key={n} style={n === 3 ? css('margin-bottom: 0;') : undefined}>
                    <div className="ip-stat-label">{project[`stat${n}_label`]}</div>
                    <div className="ip-stat-value">{project[`stat${n}_value`]}</div>
                  </div>
                ))}
              </div>
            </aside>
          </div>

          {mapImage !== null ? (
            <div className="map-gallery-layout reveal" style={css('display: grid; gap: var(--space-10); margin-top: var(--space-32);')}>
              <div className="project-map">
                <img src={mapImage} alt={`Map of ${project.title}`} style={css('width: 100%; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);')} />
              </div>
              <div className="gallery-side">
                <img src={mainImage ?? ''} className="gallery-img" alt="Project image 1" decoding="async" />
                <img src={secondaryImage ?? ''} className="gallery-img" style={css('transition-delay: 0.1s;')} alt="Project image 2" decoding="async" />
                {hasTertiary && <img src={tertiaryImage!} className="gallery-img" style={css('transition-delay: 0.2s;')} alt="Project image 3" decoding="async" />}
              </div>
            </div>
          ) : (
            <div
              className={`gallery-grid-${hasTertiary ? '3' : '2'}`}
              style={css(`margin-top: var(--space-32); display: grid; gap: var(--space-10); grid-template-columns: repeat(${hasTertiary ? '3' : 'auto-fit'}, minmax(300px, 1fr));`)}
            >
              <img src={mainImage ?? ''} className="gallery-img reveal" alt="Project image 1" decoding="async" />
              <img src={secondaryImage ?? ''} className="gallery-img reveal" style={css('transition-delay: 0.1s;')} alt="Project image 2" decoding="async" />
              {hasTertiary && <img src={tertiaryImage!} className="gallery-img reveal" style={css('transition-delay: 0.2s;')} alt="Project image 3" decoding="async" />}
            </div>
          )}

          {video !== null && (
            <div className="project-video-layout reveal" style={css('margin-top: var(--space-24); width: 100%;')}>
              <iframe
                width="100%"
                height="450"
                src={video}
                title={`${project.title} video`}
                frameBorder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerPolicy="strict-origin-when-cross-origin"
                allowFullScreen
                style={css('border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-top: var(--space-24);')}
              ></iframe>
            </div>
          )}
        </div>
      </section>
    </SitePage>
  );
}
