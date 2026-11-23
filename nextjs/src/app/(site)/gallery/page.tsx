/** Port of public/gallery.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { galleryPhotos, uploadUrl } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = {
  title: 'Gallery — Worldwide Education Foundation',
  description: "Photo gallery showcasing moments of learning, growth, and transformation from WEF's projects across Chitral, Pakistan and Central Asia.",
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

const ARROW = (
  <span className="uiv-nav-btn-box">
    <span className="uiv-nav-btn-icon">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
      </svg>
    </span>
    <span className="uiv-nav-btn-icon">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
      </svg>
    </span>
  </span>
);

export default async function GalleryPage() {
  await requirePageEnabled('gallery');
  const photos = await galleryPhotos();
  const projects = new Map<string, number>();
  for (const p of photos) projects.set(p.project, (projects.get(p.project) ?? 0) + 1);
  if (projects.has('General')) {
    const general = projects.get('General')!;
    projects.delete('General');
    projects.set('General', general);
  }
  const highlight = photos[0] ?? null;

  return (
    <SitePage seo={SEO} scripts={['js/gallery.js']}>
      <PageHero page="gallery">
        <div className="container">
          <Html as="h1" html={await block('gallery', 'page_title')} />
          <Html as="p" html={await blockRich('gallery', 'intro')} />
        </div>
      </PageHero>

      <section className="gallery-page-content">
        <div className="container">
          {projects.size > 1 && (
            <div className="gallery-filters" role="group" aria-label="Filter photos by project">
              <button type="button" className="gallery-filter is-active" data-gallery-filter="" aria-pressed="true">
                All Projects <span className="gallery-filter-count">{photos.length}</span>
              </button>
              {[...projects].map(([name, count]) => (
                <button type="button" className="gallery-filter" data-gallery-filter={name} aria-pressed="false" key={name}>
                  {name} <span className="gallery-filter-count">{count}</span>
                </button>
              ))}
            </div>
          )}

          <div className="gallery-grid">
            {highlight !== null && (
              <div className="gallery-highlights" id="gallery-highlights" role="region" aria-roledescription="slideshow" aria-label="Gallery highlights">
                <img className="gallery-highlights-slide is-active" src={uploadUrl(highlight.image_path)!} alt={highlight.caption} width={600} height={600} decoding="async" />
                <img className="gallery-highlights-slide" alt="" width={600} height={600} decoding="async" aria-hidden="true" />
                <span className="gallery-highlights-badge">Highlights</span>
                <div className="gallery-highlights-caption" aria-live="polite">
                  <strong data-highlight-project="">{highlight.project}</strong>
                  <span data-highlight-caption="">{highlight.caption !== highlight.project ? highlight.caption : ''}</span>
                </div>
                <div className="gallery-highlights-controls">
                  <button type="button" className="gallery-highlights-btn" data-highlight-prev="" aria-label="Previous highlight">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                      <path d="M15 18l-6-6 6-6" />
                    </svg>
                  </button>
                  <button type="button" className="gallery-highlights-btn" data-highlight-next="" aria-label="Next highlight">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                      <path d="M9 18l6-6-6-6" />
                    </svg>
                  </button>
                </div>
              </div>
            )}

            {photos.map((item, index) => {
              const delay = String(Number((0.1 + index * 0.05).toFixed(2)));
              return (
                <div className="gallery-item reveal" style={css(`transition-delay: ${delay}s;`)} id={`gallery-${index + 1}`} data-project={item.project} key={index}>
                  <img src={uploadUrl(item.image_path)!} alt={item.caption} data-caption={item.caption} width={300} height={300} loading="lazy" decoding="async" />
                  <div className="gallery-item-overlay">
                    <span className="gallery-item-project">{item.project}</span>
                    {item.caption !== item.project && <span className="gallery-item-caption">{item.caption}</span>}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <div className="lightbox" id="lightbox">
        <button className="lightbox-close" id="lightbox-close" aria-label="Close lightbox">
          ✕
        </button>
        <button className="lightbox-nav lightbox-prev uiv-nav-btn" id="lightbox-prev" aria-label="Previous image">
          {ARROW}
        </button>
        <img id="lightbox-img" alt="Gallery image enlarged view" decoding="async" />
        <button className="lightbox-nav lightbox-next uiv-nav-btn uiv-nav-btn--next" id="lightbox-next" aria-label="Next image">
          {ARROW}
        </button>
      </div>
    </SitePage>
  );
}
