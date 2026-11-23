/** Port of public/success-stories.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { publishedSuccessStories, uploadUrl } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = {
  title: 'Success Stories — Worldwide Education Foundation',
  description: 'Read our success stories and see the real impact of our education initiatives.',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function SuccessStoriesPage() {
  await requirePageEnabled('success-stories');
  const stories = await publishedSuccessStories();
  return (
    <SitePage seo={SEO}>
      <PageHero page="success-stories">
        <div className="container">
          <Html as="h1" html={await block('success-stories', 'page_title')} />
          <Html as="p" html={await blockRich('success-stories', 'intro')} />
        </div>
      </PageHero>

      <section className="page-content">
        <div className="container">
          <div className="card-carousel">
            <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous story">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div className="stories-grid card-carousel-track">
              {stories.map((story, i) => {
                const storyImage = uploadUrl(story.image_path ?? null);
                return (
                  <article className="story-card reveal" key={i}>
                    {storyImage !== null ? (
                      <img src={storyImage} alt={story.title} className="story-image" style={css('height: 220px; width: 100%; object-fit: cover;')} loading="lazy" decoding="async" />
                    ) : (
                      <div
                        className="story-image-placeholder"
                        style={css(
                          'height: 220px; background: linear-gradient(135deg, var(--clr-gray-100), var(--clr-gray-200)); display: flex; align-items: center; justify-content: center; color: var(--clr-gray-400); font-family: var(--ff-heading); letter-spacing: 1px; font-size: 14px; text-transform: uppercase;',
                        )}
                      >
                        Image Space
                      </div>
                    )}
                    <div className="story-content">
                      <div className="story-meta">
                        {story.tag ? <span style={css('color: var(--clr-accent);')}>{story.tag}</span> : null}
                        {story.tag && story.story_date ? <> &bull; </> : null}
                        {story.story_date ?? ''}
                      </div>
                      <h2 className="story-title">{story.title}</h2>
                      {story.link_url ? (
                        <a href={story.link_url} className="story-read-more">
                          READ MORE &raquo;
                        </a>
                      ) : null}
                    </div>
                  </article>
                );
              })}
            </div>
            <button type="button" className="carousel-arrow carousel-arrow--next" aria-label="Next story">
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
