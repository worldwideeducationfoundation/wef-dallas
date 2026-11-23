/** Port of public/index.php (home page) */
import type { Metadata } from 'next';
import A from '@/components/A';
import { preload } from 'react-dom';
import SitePage from '@/components/site/SitePage';
import Html from '@/components/site/Html';
import Img from '@/components/site/Img';
import ProgramCard from '@/components/site/ProgramCard';
import { blockImage, blockRich, blockValue } from '@/lib/blocks';
import { imageSize, imageSrcset } from '@/lib/assets';
import {
  pageHero,
  publishedHeroSlides,
  publishedPartners,
  publishedPrograms,
  publishedSponsorGroups,
  publishedUpdates,
  publishedVideos,
  safeYoutubeUrl,
  siteSettings,
  uploadUrl,
} from '@/lib/content';
import { SUBMISSION_MAX_EMAIL, SUBMISSION_MAX_MESSAGE, SUBMISSION_MAX_NAME, submissionResultMessage, submissionSubjects } from '@/lib/forms';
import { e, phpTrim, richText } from '@/lib/html';
import { nonce } from '@/lib/request';
import { pageMetadata } from '@/lib/seo';
import { css } from '@/lib/style';

const SEO = {
  page: 'home',
  title: 'Worldwide Education Foundation — Empowering Communities Through Education in Chitral',
  description:
    'Worldwide Education Foundation (WEF) invests in the potential of children and youth across underserved communities in Chitral, Pakistan through early childhood development, teacher training, English language learning, and scholarships.',
};

export async function generateMetadata(): Promise<Metadata> {
  const m = await pageMetadata(SEO);
  return {
    ...m,
    keywords: 'WEF, Worldwide Education Foundation, ECD education, Chitral, Pakistan, early childhood development, scholarships, teacher training, NGO',
    authors: [{ name: 'Worldwide Education Foundation' }],
  };
}

const HOME_LD = {
  '@context': 'https://schema.org',
  '@type': 'NGO',
  name: 'Worldwide Education Foundation',
  alternateName: 'WEF',
  url: 'https://www.worldwideeducationfoundation.org',
  logo: 'assets/images/logo.png',
  description:
    'WEF invests in the potential of children and youth across underserved communities in Central and South Asia through early development programs, English language learning, and scholarships.',
  address: { '@type': 'PostalAddress', addressLocality: 'Chitral', addressRegion: 'Khyber Pakhtunkhwa', addressCountry: 'PK' },
  foundingDate: '2021',
  nonprofitStatus: '501(c)(3)',
  taxID: '81-2135633',
  areaServed: 'Central and South Asia',
};

const KEN_BURNS = ['anim-zoom-in', 'anim-pan-left', 'anim-pan-right', 'anim-zoom-out'];

const HB_MOBILE_TITLES: Record<string, [string, string]> = {
  'Honor Board - Circle of Friends - Chitral, Pakistan': ['Honor Board', 'Circle of Friends, Chitral Pakistan'],
  'Sponsors of Bright Beginnings: Early Childhood Learning Centers': ['Sponsor a Bright Beginning', 'Early Childhood Centers'],
};
function hbHeading(text: string): string {
  const lines = HB_MOBILE_TITLES[text];
  if (!lines) return e(text);
  return `<span class="hb-full">${e(text)}</span><span class="hb-mobile" aria-hidden="true"><span class="hb-l1">${e(lines[0])}</span><span class="hb-l2">${e(lines[1])}</span></span>`;
}

const ARROW_PREV = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M15 18l-6-6 6-6" />
  </svg>
);
const ARROW_NEXT = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M9 18l6-6-6-6" />
  </svg>
);
const UIV_ARROW = (
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path>
  </svg>
);

export default async function HomePage({ searchParams }: { searchParams: Promise<{ form?: string }> }) {
  const n = await nonce();
  const { form } = await searchParams;
  const formResult = submissionResultMessage(typeof form === 'string' ? form : null);
  const settings = await siteSettings();
  const v = (k: string) => blockValue('home', k);
  const r = (k: string) => blockRich('home', k);

  const heroSlides = await publishedHeroSlides();
  const heroImage = String(heroSlides[0]?.image_src ?? 'assets/images/programs/bright-beginnings-01.webp');
  const heroSrcset = await imageSrcset(heroImage);
  preload('/' + heroImage, { as: 'image', fetchPriority: 'high', ...(heroSrcset ? { imageSrcSet: heroSrcset, imageSizes: '100vw' } : {}) });

  const purpose = await pageHero('home');
  const programs = await publishedPrograms();
  const sponsorGroups = (await publishedSponsorGroups()).filter((g) => g.sponsors.length > 0);
  const partners = await publishedPartners();
  const partnerSizes = await Promise.all(partners.map((p) => imageSize(String(p.logo_src ?? ''))));
  const updates = await publishedUpdates();
  const videos = (await publishedVideos(2)).map((x) => ({ ...x, title: String(x.title ?? ''), safe: safeYoutubeUrl(x.youtube_url ?? null) })).filter((x) => x.safe !== null);
  const counts = {
    teachers: Number.parseInt(await v('impact_teachers_count'), 10) || 0,
    experience: Number.parseInt(await v('impact_experience_count'), 10) || 0,
    students: Number.parseInt(await v('impact_students_count'), 10) || 0,
    spaces: Number.parseInt(await v('impact_spaces_count'), 10) || 0,
  };
  const mapIntro = e(await v('map_intro')).replace('{count}', '<strong id="legend-total-count">94</strong>');

  return (
    <SitePage
      seo={SEO}
      tagline="Providing the Seeds for Growth"
      styles={['css/uiverse-cards.css', 'css/pages/index.css']}
      scriptsBefore={['js/map.js']}
      afterFooter={
        <div className="lightbox" id="lightbox">
          <button className="lightbox-close" id="lightbox-close" aria-label="Close lightbox">
            ✕
          </button>
          <button className="lightbox-nav lightbox-prev" id="lightbox-prev" aria-label="Previous image">
            ←
          </button>
          <img id="lightbox-img" alt="Gallery image enlarged view" decoding="async" />
          <button className="lightbox-nav lightbox-next" id="lightbox-next" aria-label="Next image">
            →
          </button>
        </div>
      }
    >
      <script type="application/ld+json" nonce={n} dangerouslySetInnerHTML={{ __html: JSON.stringify(HOME_LD) }} />
      <h1 className="sr-only">Worldwide Education Foundation — Providing the Seeds for Growth</h1>

      {/* HERO */}
      <section className="hero-glass" id="home">
        <div className="hero-slideshow" id="hero-slideshow">
          {heroSlides.map((slide, i) => {
            const alt = phpTrim(slide.image_alt ?? '') || phpTrim(`${slide.title_lead} ${slide.title_highlight}`);
            return (
              <div className={`hero-slide${i === 0 ? ' active' : ''} ${KEN_BURNS[i % 4]}`} key={i}>
                {i === 0 ? <Img src={String(slide.image_src)} alt={alt} priority sizes="100vw" /> : <Img src={String(slide.image_src)} alt={alt} defer sizes="100vw" />}
              </div>
            );
          })}
        </div>
        <div className="container hero-glass-container">
          <div className="hero-glass-panel">
            <div className="hero-text-slider">
              {heroSlides.map((slide, i) => {
                const lead = phpTrim(slide.title_lead) + ' ';
                return (
                  <div className={`hero-text-slide${i === 0 ? ' active' : ''}`} key={i}>
                    <p className="hero-glass-heading">
                      <span className="static-part" data-text={lead}>
                        {lead}
                      </span>
                      <span style={css('white-space: nowrap;')}>
                        <em className="highlight-part" data-text={slide.title_highlight}>
                          {slide.title_highlight}
                        </em>
                      </span>
                    </p>
                    {slide.description ? (
                      <p className="hero-glass-desc" style={css('margin-top: 1rem;')}>
                        {slide.description}
                      </p>
                    ) : null}
                  </div>
                );
              })}
            </div>
            <div className="hero-glass-actions" style={css('margin-top: -1.5rem;')}>
              <A href="donate" className="button">
                <svg viewBox="0 0 16 16" className="bi bi-coin" fill="currentColor" height="16" width="16" xmlns="http://www.w3.org/2000/svg">
                  <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9H5.5zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518l.087.02z"></path>
                  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
                  <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zm0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12z"></path>
                </svg>
                Donate Now
              </A>
              <A href="active-projects" className="btn-glass-secondary" id="hero-explore-btn">
                Explore Projects
              </A>
            </div>
          </div>
          <div className="hero-slide-dots" id="hero-slide-dots">
            <button className="slide-dot prev-dot" id="hero-prev-dot" aria-label="Previous Slide"></button>
            <button className="slide-dot active center-dot" id="hero-curr-dot" aria-label="Current Slide"></button>
            <button className="slide-dot next-dot" id="hero-next-dot" aria-label="Next Slide"></button>
          </div>
        </div>
      </section>

      {/* PURPOSE */}
      <section className={'purpose-section' + purpose.className} id="vision-banner" style={css(purpose.style)}>
        {purpose.hasImage && (
          <>
            <div className="page-hero__bg" aria-hidden="true"></div>
            <div className="page-hero__overlay" aria-hidden="true"></div>
          </>
        )}
        <div className="purpose-overlay"></div>
        <div className="container purpose-container reveal">
          <span className="purpose-badge">{await v('purpose_badge')}</span>
          <Html as="h2" className="purpose-text" html={await r('purpose_headline')} />
        </div>
      </section>

      {/* ABOUT */}
      <section className="section about" id="about">
        <div className="container">
          <div className="section-header reveal">
            <h2>{await v('section1_title')}</h2>
            <Html as="p" html={await r('section1_intro')} />
          </div>
          <div className="about-grid">
            <A href="our-mission" className="about-card reveal" id="about-vision">
              <div className="about-card-icon-container">
                <svg className="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </div>
              <h3>{await v('about_vision_title')}</h3>
              <Html as="p" html={await r('about_vision_text')} />
            </A>
            <A href="our-mission" className="about-card reveal" id="about-mission" style={css('transition-delay: 0.15s;')}>
              <div className="about-card-icon-container">
                <svg className="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="22" y1="12" x2="18" y2="12"></line>
                  <line x1="6" y1="12" x2="2" y2="12"></line>
                  <line x1="12" y1="6" x2="12" y2="2"></line>
                  <line x1="12" y1="22" x2="12" y2="18"></line>
                  <circle cx="12" cy="12" r="6"></circle>
                </svg>
              </div>
              <h3>{await v('about_mission_title')}</h3>
              <Html as="p" html={await r('about_mission_text')} />
            </A>
            <A href="our-mission" className="about-card reveal" id="about-belief" style={css('transition-delay: 0.3s;')}>
              <div className="about-card-icon-container">
                <svg className="about-card-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .6 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"></path>
                  <line x1="9" y1="18" x2="15" y2="18"></line>
                  <line x1="10" y1="22" x2="14" y2="22"></line>
                </svg>
              </div>
              <h3>{await v('about_belief_title')}</h3>
              <Html as="p" html={await r('about_belief_text')} />
            </A>
          </div>
        </div>
      </section>

      {/* PROJECTS */}
      <section className="section projects" id="projects">
        <div className="container">
          <div className="section-header reveal">
            <h2>{await v('section2_title')}</h2>
            <Html as="p" html={await r('section2_intro')} />
          </div>
          <div className="card-carousel">
            <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous project">
              {ARROW_PREV}
            </button>
            <div className="projects-grid card-carousel-track">
              {programs.map((program, index) => (
                <ProgramCard key={program.slug} program={program} index={index} />
              ))}
            </div>
            <button type="button" className="carousel-arrow carousel-arrow--next" aria-label="Next project">
              {ARROW_NEXT}
            </button>
          </div>
        </div>
      </section>

      {/* HONOR BOARD */}
      <section className="honor-board-section reveal" id="sponsors">
        <div className="container">
          <div className="honor-board-card">
            <div className="honor-board-header">
              <h2 dangerouslySetInnerHTML={{ __html: hbHeading(await v('honor_board_title')) }} />
            </div>
            {sponsorGroups.map((group) => (
              <div className="sponsor-group" key={group.id}>
                <div className="sponsor-group-header">
                  <h3 dangerouslySetInnerHTML={{ __html: hbHeading(String(group.title)) }} />
                  {phpTrim(group.description) !== '' && <p dangerouslySetInnerHTML={{ __html: richText(group.description) }} />}
                </div>
                <div className="sponsors-grid">
                  {group.sponsors.map((s: Record<string, any>, i: number) => (
                    <div className="sponsor-tile" data-sponsor={s.name} key={i}>
                      <span className="sponsor-name">{s.name}</span>
                      {phpTrim(s.badge_text) !== '' && <span className="sponsor-badge-tag">{s.badge_text}</span>}
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PARTNERS */}
      <section className="section partners-section" id="partners">
        <div className="container">
          <div className="section-header reveal" style={css('text-align: center; margin-bottom: 0;')}>
            <h2 style={css('font-size: var(--fs-xl); color: var(--clr-primary-dark); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0;')}>
              {await v('partners_title')}
            </h2>
          </div>
          <div className="partners-marquee reveal">
            <div className="partners-track">
              {[false, true].flatMap((isCopy) =>
                partners.map((p, i) => {
                  const size = partnerSizes[i];
                  const img = (
                    <img
                      {...(size ? { width: size.width, height: size.height } : {})}
                      src={p.logo_src}
                      alt={isCopy ? '' : p.name}
                      {...(isCopy ? { 'aria-hidden': true } : {})}
                      loading="lazy"
                      decoding="async"
                    />
                  );
                  return (
                    <div className="partner-logo" key={`${isCopy}-${i}`}>
                      {p.link_url ? (
                        <a href={p.link_url} target="_blank" rel="noopener" {...(isCopy ? { tabIndex: -1, 'aria-hidden': true } : {})}>
                          {img}
                        </a>
                      ) : (
                        img
                      )}
                    </div>
                  );
                }),
              )}
            </div>
          </div>
        </div>
      </section>

      {/* PROJECT UPDATES */}
      <section className="section project-updates" id="project-updates">
        <div className="container">
          <div className="section-header reveal">
            <h2>{await v('section3_title')}</h2>
            <Html as="p" html={await r('section3_intro')} />
          </div>
          <div className="updates-grid-container updates-carousel reveal">
            <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous update">
              {ARROW_PREV}
            </button>
            <div className="updates-grid">
              {updates.map((u, i) => {
                const tag = phpTrim(u.tag);
                let image = String(u.image_path ?? '');
                if (image !== '' && !image.startsWith('assets/') && !/^https?:\/\//i.test(image)) image = uploadUrl(image) ?? '';
                return (
                  <div className="updates-card" key={i}>
                    <div className="updates-card-image">
                      <img width={1024} height={1024} src={image} alt={u.title} loading="lazy" decoding="async" />
                      {tag !== '' && <span className={`updates-card-tag tag-${tag.toLowerCase()}`}>{tag}</span>}
                    </div>
                    <div className="updates-card-body">
                      <h3>{u.title}</h3>
                      <p>{u.content}</p>
                      {phpTrim(u.link_url) !== '' && (
                        <a href={u.link_url} className="updates-card-link">
                          <span>READ MORE</span>
                          <span className="arrow">&rarr;</span>
                        </a>
                      )}
                    </div>
                  </div>
                );
              })}
              <div className="mobile-updates-nav">
                <button className="uiverse-nav-btn prev" id="mobile-updates-prev" aria-label="Previous Project">
                  <div className="button-box">
                    <span className="button-elem">{UIV_ARROW}</span>
                    <span className="button-elem">{UIV_ARROW}</span>
                  </div>
                </button>
                <button className="uiverse-nav-btn next" id="mobile-updates-next" aria-label="Next Project">
                  <div className="button-box">
                    <span className="button-elem">{UIV_ARROW}</span>
                    <span className="button-elem">{UIV_ARROW}</span>
                  </div>
                </button>
              </div>
            </div>
            <button type="button" className="carousel-arrow carousel-arrow--next" aria-label="Next update">
              {ARROW_NEXT}
            </button>
          </div>
        </div>
      </section>

      {/* WEF BEGINNINGS */}
      <section className="section beginnings" id="beginnings">
        <div className="container">
          <div className="section-header reveal">
            <h2>{await v('section4_title')}</h2>
          </div>
          <div className="beginnings-timeline reveal">
            {await Promise.all(
              [1, 2, 3, 4].map(async (step) => (
                <div className="timeline-step" key={step}>
                  <div className="step-badge">{step}</div>
                  <div className="step-content">
                    <Html as="p" html={await r(`beginnings_step${step}`)} />
                  </div>
                </div>
              )),
            )}
          </div>
          <div className="video-grid reveal" style={css('margin-top: var(--space-24);')}>
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

      {/* WEF GLOBALLY */}
      <section className="section impact wef-globally" id="impact">
        <div className="container">
          <div className="globally-main-grid reveal">
            <div className="globally-text-side">
              <h2>{await v('globally_title')}</h2>
              <Html as="p" html={await r('globally_text1')} />
              <Html as="p" html={await r('globally_text2')} />
              <div className="globally-stats-grid">
                {(
                  [
                    ['impact-teachers', 'TEACHERS.png', 189, 'Teachers Icon', 'Teachers', counts.teachers, ''],
                    ['impact-experience', 'Years%20of%20Experience.png', 184, 'Years of Experience Icon', 'Years of Experience', counts.experience, '0.1s'],
                    ['impact-students', 'STUDENTS.png', 202, 'Students Icon', 'Students', counts.students, '0.2s'],
                    ['impact-spaces', 'LEARNING%20SPACES.png', 168, 'Learning Spaces Icon', 'Learning Spaces', counts.spaces, '0.3s'],
                  ] as const
                ).map(([id, icon, h, alt, label, count, delay]) => (
                  <div className="impact-card reveal" id={id} key={id} style={delay ? css(`transition-delay: ${delay};`) : undefined}>
                    <div className="impact-card-icon">
                      <img width={256} height={h} src={`assets/icons/SVG/${icon}`} alt={alt} style={css('width: 1em; height: 1em;')} decoding="async" loading="lazy" />
                    </div>
                    <div className="impact-label">{label}</div>
                    <div className="impact-number" data-count={count} data-suffix="+">
                      0
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div className="globally-map-side">
              <div className="globally-map-wrapper">
                <img
                  width={1024}
                  height={826}
                  src={await blockImage('home', 'globally_map_image')}
                  alt="WEF Globally & Global Virtual School Footprint Map"
                  className="globally-map-img"
                  id="globally-map-img"
                  loading="lazy"
                  decoding="async"
                />
                <div className="map-caption">
                  <svg className="map-caption-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35M11 8v6M8 11h6" />
                  </svg>{' '}
                  Click to enlarge map (Global Virtual School Footprint)
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* CENTERS MAP */}
      <section className="section map-section reveal" id="centers-map">
        <div className="container">
          <div className="map-card">
            <div className="map-header">
              <h2>{await v('map_title')}</h2>
              <p dangerouslySetInnerHTML={{ __html: mapIntro }} />
            </div>
            <div className="map-body">
              <div id="map" role="region" aria-label="Interactive map showing ECD center locations"></div>
              <div className="map-sidebar">
                <div id="map-no-results" className="map-no-results" hidden>
                  No mapped centers found for <strong data-sponsor-name=""></strong> yet.
                </div>
                <div className="map-controls-panel map-district-panel">
                  <label htmlFor="district-select" className="donor-search-label">
                    Filter by District:
                  </label>
                  <div className="donor-select-wrapper">
                    <svg className="donor-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                      <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" />
                      <circle cx="12" cy="9.5" r="2.5" />
                    </svg>
                    <select id="district-select" className="donor-select-input" data-district-select="">
                      <option value="All">All Districts</option>
                      <option value="Upper Chitral">Upper Chitral</option>
                      <option value="Lower Chitral">Lower Chitral</option>
                    </select>
                  </div>
                </div>
                <div className="map-controls-panel">
                  <label htmlFor="donor-select" className="donor-search-label">
                    Filter by Sponsor:
                  </label>
                  <div className="donor-select-wrapper">
                    <svg className="donor-select-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                    <select id="donor-select" className="donor-select-input">
                      <option value="All">All Sponsors</option>
                    </select>
                  </div>
                </div>
                <div className="map-legend-filters">
                  <div className="legend-filters-header">
                    <span className="legend-filters-title">Filter by Category</span>
                  </div>
                  <div className="category-select-wrapper">
                    <select id="category-select" className="category-select-input" aria-label="Filter by category">
                      <option value="All">All Centers</option>
                      <option value="Entrepreneurial Model">Entrepreneurial</option>
                      <option value="Government School">Government</option>
                      <option value="Private School">Private</option>
                      <option value="Semi Govt School">Semi Govt</option>
                    </select>
                  </div>
                  <div className="legend-filters-grid">
                    <button className="legend-filter-btn legend-filter-all legend-filter-active-all" id="legend-filter-all">
                      All Centers
                    </button>
                    {(
                      [
                        ['Entrepreneurial Model', '#0e611d', 'Entrepreneurial Models', 'legend-count-entrepreneurial-model'],
                        ['Government School', '#1a56db', 'Government Schools', 'legend-count-government-school'],
                        ['Private School', '#9333ea', 'Private Schools', 'legend-count-private-school'],
                        ['Semi Govt School', '#dc6803', 'Semi Govt Schools', 'legend-count-semi-govt-school'],
                      ] as const
                    ).map(([cat, color, label, id]) => (
                      <button className="legend-filter-btn" data-filter-category={cat} key={cat}>
                        <span className="legend-filter-dot" style={css(`background: ${color};`)}></span>
                        <span className="legend-filter-label">{label}</span>
                        <span className="legend-filter-count" id={id}>
                          0
                        </span>
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* CONTACT */}
      <section className="section contact" id="contact">
        <div className="container">
          <div className="section-header reveal">
            <h2>{await v('section5_title')}</h2>
            <Html as="p" html={await r('section5_intro')} />
          </div>
          <div className="contact-grid">
            <div className="contact-info reveal-left" id="contact-info">
              <h3>{await v('contact_connect_title')}</h3>
              <Html as="p" html={await r('contact_connect_text')} />
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
              <input type="hidden" name="form_type" value="contact" />
              <div className={'form-status' + (formResult ? ' form-status--' + formResult.type : '')} data-form-status="" role="status" aria-live="polite" hidden={!formResult}>
                {formResult ? formResult.text : ''}
              </div>
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
                <label htmlFor="contact-subject">Subject</label>
                <select id="contact-subject" name="subject" required defaultValue="">
                  <option value="" disabled>
                    Select a topic
                  </option>
                  {Object.entries(submissionSubjects('contact')).map(([value, label]) => (
                    <option value={value} key={value}>
                      {label}
                    </option>
                  ))}
                </select>
                <p className="form-error" data-error-for="subject" hidden></p>
              </div>
              <div className="form-group">
                <label htmlFor="contact-message">Message</label>
                <textarea id="contact-message" name="message" placeholder="Tell us how we can help..." rows={5} maxLength={SUBMISSION_MAX_MESSAGE} required></textarea>
                <p className="form-error" data-error-for="message" hidden></p>
              </div>
              <div className="form-hp" aria-hidden="true">
                <label htmlFor="contact-website">Leave this field empty</label>
                <input type="text" id="contact-website" name="website" tabIndex={-1} autoComplete="off" />
              </div>
              <button type="submit" className="btn btn-accent" id="contact-submit" style={css('width: 100%; justify-content: center;')}>
                Send Message →
              </button>
            </form>
          </div>
        </div>
      </section>
    </SitePage>
  );
}
