/** Port of public/active-projects.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import ProgramCard from '@/components/site/ProgramCard';
import { block, blockRich } from '@/lib/blocks';
import { publishedPrograms } from '@/lib/content';
import { programHubParam } from '@/lib/program-nav';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const SEO = {
  page: 'programs',
  title: 'Our Projects — Worldwide Education Foundation',
  description:
    'Explore all 9 active projects by Worldwide Education Foundation — from Early Childhood Learning Centers to Teacher Training and Scholarships across Chitral, Pakistan.',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function ActiveProjectsPage() {
  await requirePageEnabled('active-projects');
  const programs = await publishedPrograms();
  return (
    <SitePage seo={SEO} styles={['css/uiverse-cards.css']}>
      <PageHero page="programs">
        <div className="container">
          <Html as="h1" html={await block('programs', 'page_title')} />
          <Html as="p" html={await blockRich('programs', 'intro')} />
        </div>
      </PageHero>

      <section className="programs-page-content">
        <div className="container">
          <div className="card-carousel">
            <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous project">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div className="projects-grid card-carousel-track">
              {programs.map((program, index) => (
                <ProgramCard key={program.slug} program={program} index={index} hubParam={programHubParam('active-projects')} />
              ))}
            </div>
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
