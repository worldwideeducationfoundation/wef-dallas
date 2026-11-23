/** Port of public/video-gallery.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { publishedVideos, safeYoutubeUrl } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const SEO = { title: 'Video Gallery — Worldwide Education Foundation', description: 'Watch videos of our projects and impact in action.' };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function VideoGalleryPage() {
  await requirePageEnabled('video-gallery');
  const videos = (await publishedVideos())
    .map((v) => ({ ...v, title: String(v.title ?? ""), safe: safeYoutubeUrl(v.youtube_url ?? null) }))
    .filter((v) => v.safe !== null);
  return (
    <SitePage seo={SEO}>
      <PageHero page="video-gallery">
        <div className="container">
          <Html as="h1" html={await block('video-gallery', 'page_title')} />
          <Html as="p" html={await blockRich('video-gallery', 'intro')} />
        </div>
      </PageHero>

      <section className="page-content">
        <div className="container">
          <div className="video-grid reveal">
            {videos.map((video, i) => (
              <div className="video-card" key={i}>
                <div className="video-wrapper">
                  <iframe
                    src={video.safe!}
                    title={video.title}
                    frameBorder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerPolicy="strict-origin-when-cross-origin"
                    allowFullScreen
                  ></iframe>
                </div>
                <div className="video-info">
                  <h3>{video.title}</h3>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </SitePage>
  );
}
