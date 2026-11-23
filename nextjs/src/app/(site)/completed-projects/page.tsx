/** Port of public/completed-projects.php */
import type { Metadata } from 'next';
import A from '@/components/A';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { publishedCompletedProjects, uploadUrl } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = { title: 'Completed Projects — Worldwide Education Foundation', description: 'Explore the completed projects by Worldwide Education Foundation.' };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function CompletedProjectsPage() {
  await requirePageEnabled('completed-projects');
  const projects = await publishedCompletedProjects();
  let delay = 0;
  const cards = projects.map((project) => {
    const d = Math.round(delay * 10) / 10;
    delay += 0.1;
    if (delay > 0.4 + 1e-9) delay = 0;
    return (
      <A href={`completed-project-details.php?slug=${encodeURIComponent(project.slug)}`} className="editorial-card reveal" style={css(`transition-delay: ${d}s;`)} key={project.slug}>
        <div className="ec-image-wrapper">
          <div className="ec-badge">{project.category}</div>
          <div className="ec-status">Completed</div>
          <img src={uploadUrl(project.image_path) ?? ''} alt={project.title} className="ec-image" loading="lazy" decoding="async" />
        </div>
        <div className="ec-content">
          <div className="ec-meta">
            <span className="ec-location">
              <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              {project.location}
            </span>
            <span className="ec-year">{project.completion_year}</span>
          </div>
          <h3 className="ec-title">{project.title}</h3>
          <p className="ec-summary">{project.summary}</p>
          <div className="ec-stats">
            {[1, 2, 3].map((n) => (
              <div className="ec-stat" key={n}>
                <span>{project[`stat${n}_label`]}</span>
                <strong>{project[`stat${n}_value`]}</strong>
              </div>
            ))}
          </div>
        </div>
      </A>
    );
  });

  return (
    <SitePage seo={SEO} styles={['css/pages/completed-projects.css']}>
      <PageHero page="completed-projects" style="padding-bottom: 2rem;">
        <div className="container">
          <Html as="h1" html={await block('completed-projects', 'page_title')} />
          <Html as="p" html={await blockRich('completed-projects', 'intro')} />
        </div>
      </PageHero>

      <section className="programs-page-content" style={css('padding-top: 1rem; padding-bottom: 6rem;')}>
        <div className="container">
          <div className="card-carousel">
            <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous project">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div className="projects-editorial-grid card-carousel-track">{cards}</div>
            <button type="button" className="carousel-arrow carousel-arrow--next" aria-label="Next project">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </button>
          </div>
        </div>
      </section>
    </SitePage>
  );
}
