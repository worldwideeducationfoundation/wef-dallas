/**
 * The nine programme pages (/bright-beginnings, /learning-centers, ...).
 * Port of includes/layout/project-page.php; each page's settings come from
 * src/data/project-pages.json (the $projectPage arrays of the PHP files).
 * Any other unknown top-level slug is a 404.
 */
import type { Metadata } from 'next';
import A from '@/components/A';
import { notFound } from 'next/navigation';
import projectPagesJson from '@/data/project-pages.json';
import SitePage from '@/components/site/SitePage';
import Img from '@/components/site/Img';
import { programCompareSlider, publishedProgramBySlug, uploadUrl } from '@/lib/content';
import { phpTrim } from '@/lib/html';
import { pageMetadata } from '@/lib/seo';
import { PROGRAM_JOURNEY_FALLBACK_NAMES, programBackLink, programPagerLinks } from '@/lib/program-nav';
import {
  highlightHtml,
  projectCarouselPhotos,
  projectPhotos,
  projectSections,
  projectTitleParts,
  projectVideo,
  splitHighlight,
} from '@/lib/project-pages';

type Stat = { value: string; label: string; count?: boolean };
type ProjectPageConfig = {
  slug: string;
  highlight: string;
  before_after?: boolean;
  icon: string;
  image_alt: string;
  seo_title: string;
  seo_description: string;
  fallback: { location: string; badge: string; image: string; stats: Stat[] };
};
const PROJECT_PAGES = projectPagesJson as unknown as Record<string, ProjectPageConfig>;

type Params = { params: Promise<{ slug: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> };

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const pp = PROJECT_PAGES[(await params).slug];
  if (!pp) return {};
  return pageMetadata({ page: pp.slug, title: pp.seo_title, description: pp.seo_description });
}

export default async function ProjectPage({ params, searchParams }: Params) {
  const pp = PROJECT_PAGES[(await params).slug];
  if (!pp) notFound();
  const from = (await searchParams).from;
  const slug = pp.slug;
  const fallback = pp.fallback;
  const program = await publishedProgramBySlug(slug);
  const backLink = programBackLink('active-projects', typeof from === 'string' ? from : undefined);
  const pager = await programPagerLinks(slug);

  const projectName = phpTrim(program?.name ?? '') || PROGRAM_JOURNEY_FALLBACK_NAMES[slug] || pp.highlight;
  const shortName = phpTrim(projectName.split(':')[0]);
  const location = phpTrim(program?.location_text ?? '') || fallback.location;
  const badge = phpTrim(program?.badge_text ?? '') || fallback.badge;
  const coverImage = (program ? uploadUrl(program.image_path) : null) ?? fallback.image;

  let stats: Stat[] = program
    ? [1, 2, 3]
        .map((i) => ({ value: program[`stat${i}_value`] ?? null, label: program[`stat${i}_label`] ?? null }))
        .filter((s) => s.value !== null && s.value !== '')
    : [...fallback.stats];
  const hasLocation = stats.some((s) => phpTrim(s.label).toLowerCase() === 'location');
  if (!hasLocation && location !== '') stats.unshift({ value: location, label: 'Location' });
  if (badge !== '') {
    const m = /^since\s+(.+)$/i.exec(badge);
    const since: Stat = m ? { value: m[1], label: 'Since', count: false } : { value: badge, label: 'Status', count: false };
    let at = 0;
    stats.forEach((s, n) => {
      if (at === 0 && phpTrim(s.label).toLowerCase() === 'location') at = n + 1;
    });
    stats = [...stats.slice(0, at), since, ...stats.slice(at)];
  }

  const title = projectTitleParts(projectName, pp.highlight);
  const sections = await projectSections(slug, shortName);
  const photos = await projectPhotos(program);
  const slides = projectCarouselPhotos(coverImage, photos, projectName);
  const compare = pp.before_after ? programCompareSlider(program, true) : '';
  const video = await projectVideo(projectName);
  let videoSection: number | null = null;
  if (video !== null && sections.length) {
    videoSection = 0;
    const idx = sections.findIndex((s) => /impact/i.test(s.heading));
    if (idx !== -1) videoSection = idx;
  }

  let photoIndex = 0;
  const sectionEls = sections.map((section, i) => {
    const hasVideo = i === videoSection;
    const photo = hasVideo ? null : (photos[photoIndex] ?? null);
    if (photo !== null) photoIndex++;
    const heading = i === 0 ? highlightHtml(splitHighlight(section.heading, pp.highlight)) : highlightHtml(splitHighlight(section.heading));
    return (
      <section key={i} className={'pp-feature' + (i % 2 ? ' pp-feature--flip' : '')} id={i === 0 ? 'pp-about' : undefined}>
        <div className="container pp-feature-grid">
          <div className="pp-feature-media reveal">
            {hasVideo && video ? (
              <div className="pp-feature-video">
                <div className="video-wrapper">
                  <iframe
                    src={`https://www.youtube.com/embed/${video.id}`}
                    title={video.title}
                    frameBorder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerPolicy="strict-origin-when-cross-origin"
                    allowFullScreen
                    loading="lazy"
                  ></iframe>
                </div>
              </div>
            ) : photo !== null ? (
              <figure className="pp-feature-photo">
                <img src={photo.src} alt={photo.caption !== '' ? photo.caption : projectName} loading="lazy" decoding="async" />
                {photo.caption !== '' && <figcaption>{photo.caption}</figcaption>}
              </figure>
            ) : (
              <div className={`pp-feature-panel pp-feature-panel--${i % 3}`} aria-hidden="true">
                <span className="pp-feature-panel-num">{String(i + 1).padStart(2, '0')}</span>
                <img src={pp.icon} alt="" width={96} height={96} decoding="async" loading="lazy" />
                <span className="pp-feature-panel-label">{shortName}</span>
              </div>
            )}
          </div>
          <div className="pp-feature-text reveal">
            <span className="pp-eyebrow">{section.eyebrow}</span>
            <h2 className="pp-heading" dangerouslySetInnerHTML={{ __html: heading }} />
            <div className="pp-prose" dangerouslySetInnerHTML={{ __html: section.html }} />
          </div>
        </div>
      </section>
    );
  });

  return (
    <SitePage seo={{ page: slug, title: pp.seo_title, description: pp.seo_description }} mainClassName="project-page" scripts={['js/project-page.js']}>
      {/* COVER */}
      <section className="pp-cover">
        <div className="pp-cover-media">
          <Img src={coverImage} alt={pp.image_alt} priority />
        </div>
        <span className="pp-cover-arc" aria-hidden="true"></span>
        <div className="container pp-cover-inner">
          <A href={backLink.href} className="pp-cover-back">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
            {backLink.label}
          </A>
          <h1 className="pp-cover-title" data-no-text-animate="">
            {title.kicker !== '' && <span className="pp-cover-kicker">{title.kicker}</span>}
            <span className="pp-cover-name" dangerouslySetInnerHTML={{ __html: highlightHtml(title.main, true) }} />
            {title.sub !== '' && <span className="pp-cover-sub">{title.sub}</span>}
          </h1>
        </div>
      </section>

      {/* IMPACT LINE */}
      {stats.length > 0 && (
        <section className="pp-stats" aria-label={`${shortName} in numbers`}>
          <div className="container pp-stats-row">
            {stats.map((stat, i) => {
              const isNumber = /^\D{0,2}[\d,.]+/.test(String(stat.value));
              const countUp = isNumber && (stat.count ?? true);
              return (
                <div key={i} className={'pp-stat' + (isNumber ? '' : ' pp-stat--text')}>
                  <span className="pp-stat-label">{String(stat.label)}</span>
                  <strong className={'pp-stat-value' + (isNumber ? '' : ' is-text')} {...(countUp ? { 'data-count-up': '' } : {})}>
                    {String(stat.value)}
                  </strong>
                </div>
              );
            })}
          </div>
        </section>
      )}

      {/* BEFORE & AFTER */}
      {compare !== '' && (
        <section className="pp-compare">
          <div className="container">
            <div className="pp-center-head reveal">
              <span className="pp-eyebrow">See the Change</span>
              <h2 className="pp-heading">
                Before &amp; <span className="pp-hl">After</span>
              </h2>
            </div>
            <div className="pp-compare-frame reveal" dangerouslySetInnerHTML={{ __html: compare }} />
            <p className="pp-compare-hint">Drag the slider to compare.</p>
          </div>
        </section>
      )}

      {sectionEls}

      {/* PHOTO CAROUSEL */}
      {slides.length > 0 && (
        <section className="pp-gallery" id="pp-photos">
          <div className="container">
            <div className="pp-center-head reveal">
              <span className="pp-eyebrow">Moments from the Field</span>
              <h2 className="pp-heading">
                Life at <span className="pp-hl">{shortName}</span>
              </h2>
            </div>
          </div>
          <div className="pp-coverflow" data-coverflow="" role="region" aria-roledescription="carousel" aria-label={`Photos of ${shortName}`}>
            <div className="pp-coverflow-stage">
              {slides.map((slide, n) => (
                <figure
                  key={n}
                  className={'pp-coverflow-slide' + (slide.src === null ? ' is-placeholder' : '')}
                  data-index={n}
                  aria-roledescription="slide"
                  aria-label={`${n + 1} of ${slides.length}`}
                >
                  {slide.src !== null ? (
                    <>
                      <img src={slide.src} alt={slide.caption} loading="lazy" decoding="async" />
                      <figcaption>{slide.caption}</figcaption>
                    </>
                  ) : (
                    <div className="pp-coverflow-soon">
                      <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <circle cx="8.5" cy="10" r="1.5" />
                        <path d="M21 16l-5-5-7 7" />
                      </svg>
                      <span>{slide.caption}</span>
                    </div>
                  )}
                </figure>
              ))}
            </div>
            {slides.length > 1 && (
              <>
                <button type="button" className="pp-coverflow-arrow pp-coverflow-arrow--prev" data-coverflow-prev="" aria-label="Previous photo">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6" />
                  </svg>
                </button>
                <button type="button" className="pp-coverflow-arrow pp-coverflow-arrow--next" data-coverflow-next="" aria-label="Next photo">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M9 18l6-6-6-6" />
                  </svg>
                </button>
                <div className="pp-coverflow-dots" role="group" aria-label="Choose a photo">
                  {slides.map((_, n) => (
                    <button key={n} type="button" className="pp-coverflow-dot" data-coverflow-dot={n} aria-label={`Photo ${n + 1}`}></button>
                  ))}
                </div>
              </>
            )}
          </div>
        </section>
      )}

      {/* SUPPORT BAND */}
      <section className="pp-support" aria-labelledby="pp-support-heading">
        <div className="container pp-support-row">
          <div>
            <h2 id="pp-support-heading">Support {shortName}</h2>
            <p>Choose an amount and continue to PayPal.</p>
          </div>
          <A href={`donate?program=${encodeURIComponent(slug)}`} className="button pp-support-button">
            <svg viewBox="0 0 16 16" fill="currentColor" height="16" width="16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9H5.5zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518l.087.02z"></path>
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
              <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zm0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"></path>
            </svg>
            Donate Now
          </A>
        </div>
      </section>

      {/* PREVIOUS / NEXT PROJECT */}
      <nav className="pp-pager" aria-label="More projects">
        <div className="container pp-pager-row">
          <a href={pager.prev.slug} className="pp-pager-link">
            <span className="pp-pager-label">
              &larr; Previous<span className="pp-pager-word"> project</span>
            </span>
            <span className="pp-pager-name">{pager.prev.name}</span>
          </a>
          <a href={pager.next.slug} className="pp-pager-link pp-pager-link--next">
            <span className="pp-pager-label">
              Next<span className="pp-pager-word"> project</span> &rarr;
            </span>
            <span className="pp-pager-name">{pager.next.name}</span>
          </a>
        </div>
      </nav>
    </SitePage>
  );
}

