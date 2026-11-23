/** Port of public/impact-goals.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import Html from '@/components/site/Html';
import { block, blockRich, blockValue } from '@/lib/blocks';
import { publishedImpactMilestones } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';

const SEO = {
  title: 'Impact Goals — Worldwide Education Foundation',
  description:
    'Our ten-year roadmap: growing from 10 to 1,200 learning centers, reaching 36,000 students and training 2,400 teachers and entrepreneurs by 2035.',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

const CHART_SLOTS = [
  { x: 110, y: 250 },
  { x: 340, y: 180 },
  { x: 600, y: 110 },
  { x: 850, y: 50 },
  { x: 1090, y: -20 },
];

const int = (v: unknown) => Math.trunc(Number(v) || 0);
const year = (s: unknown) => /(\d{4})/.exec(String(s ?? ''))?.[1] ?? '';

const PREV = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M15 18l-6-6 6-6" />
  </svg>
);
const NEXT = (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M9 18l6-6-6-6" />
  </svg>
);

export default async function ImpactGoalsPage() {
  await requirePageEnabled('impact-goals');
  const milestones = (await publishedImpactMilestones()).slice(0, CHART_SLOTS.length);
  const points = milestones.map((m, i) => ({ ...CHART_SLOTS[i], m }));
  const last = points.length ? points[points.length - 1].m : null;
  const firstYear = points.length ? year(points[0].m.milestone_date) : '';
  const lastYear = last ? year(last.milestone_date) : '';
  const v = (k: string) => blockValue('impact-goals', k);
  const studentsLabel = await v('chart_students_label');
  const teachersLabel = await v('chart_teachers_label');

  const summary = [
    { img: 'LEARNING SPACES.png', w: 256, h: 168, alt: 'Learning Spaces', target: last?.centers_target, key: 'centers' },
    { img: 'STUDENTS.png', w: 256, h: 202, alt: 'Students', target: last?.students_target, key: 'students' },
    { img: 'TEACHERS.png', w: 256, h: 189, alt: 'Teachers', target: last?.teachers_target, key: 'teachers' },
  ];

  return (
    <SitePage seo={SEO} styles={['css/impact-goals-new.css']} scripts={['js/impact-goals-new.js']}>
      <div className="impact-goals-wrapper">
        <section className="hero">
          <h1>
            <strong dangerouslySetInnerHTML={{ __html: await block('impact-goals', 'page_title') }} />
          </h1>
          <Html as="p" className="subtitle" html={await blockRich('impact-goals', 'intro')} />
        </section>

        {points.length > 0 && (
          <>
            <div className="new-svg-chart-wrapper">
              <div className="new-svg-chart-container">
                <svg viewBox="0 -150 1200 900">
                  <path className="line" d={'M' + points.map((p) => `${p.x} ${p.y}`).join(' L')} />
                  {points.map((p, i) => (
                    <line key={i} className="vertical" x1={p.x} y1={p.y} x2={p.x} y2="650" />
                  ))}
                  <rect className="bar students" x="40" y="300" width="1100" height="35" rx="18" />
                  <text className="label" x="600" y="324" textAnchor="middle">
                    {studentsLabel}
                  </text>
                  <rect className="bar teachers" x="40" y="500" width="1100" height="35" rx="18" />
                  <text className="label" x="600" y="524" textAnchor="middle">
                    {teachersLabel}
                  </text>
                  <line className="timeline" x1="80" y1="650" x2="1090" y2="650" />
                  <polygon className="arrow" points="1090,650 1055,625 1055,675" />
                  <circle cx="80" cy="650" r="10" fill="var(--clr-primary)" />
                  <circle cx="1090" cy="650" r="10" fill="var(--clr-primary)" />
                  {points.map((p, i) => (
                    <g className="group" key={i}>
                      <circle className="point" cx={p.x} cy={p.y} r="11" />
                      <text x={p.x} y={p.y - 105} textAnchor="middle" className="small">
                        <tspan className="counter" data-target={int(p.m.centers_target)}>
                          0
                        </tspan>
                        <tspan x={p.x} dy="24">
                          CENTERS
                        </tspan>
                        <tspan x={p.x} dy="24">
                          {p.m.milestone_date}
                        </tspan>
                      </text>
                      <text x={p.x} y="380" textAnchor="middle">
                        <tspan className="counter" data-target={int(p.m.students_target)}>
                          0
                        </tspan>
                      </text>
                      <text x={p.x} y="580" textAnchor="middle">
                        <tspan className="counter" data-target={int(p.m.teachers_target)}>
                          0
                        </tspan>
                      </text>
                    </g>
                  ))}
                  <text x="80" y="700" className="year">
                    {firstYear}
                  </text>
                  <text x="1090" y="700" className="year" textAnchor="end">
                    {lastYear}
                  </text>
                </svg>
              </div>
            </div>

            <div className="chart-mobile-list">
              {points.map((p, i) => (
                <div className="chart-mobile-item" key={i}>
                  <div className="chart-mobile-date">{p.m.milestone_date}</div>
                  <div className="chart-mobile-stats">
                    <div className="chart-mobile-stat">
                      <span className="chart-mobile-stat-label">Centers</span>
                      <span className="counter" data-target={int(p.m.centers_target)}>
                        0
                      </span>
                    </div>
                    <div className="chart-mobile-stat">
                      <span className="chart-mobile-stat-label">{studentsLabel}</span>
                      <span className="counter" data-target={int(p.m.students_target)}>
                        0
                      </span>
                    </div>
                    <div className="chart-mobile-stat">
                      <span className="chart-mobile-stat-label">{teachersLabel}</span>
                      <span className="counter" data-target={int(p.m.teachers_target)}>
                        0
                      </span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}

        <div className="card-carousel">
          <button type="button" className="carousel-arrow carousel-arrow--prev" aria-label="Previous summary">
            {PREV}
          </button>
          <section className="summary card-carousel-track">
            {await Promise.all(
              summary.map(async (s) => (
                <div className="summary-card" key={s.key}>
                  <div className="icon">
                    <img width={s.w} height={s.h} src={`assets/icons/SVG/${s.img}`} alt={s.alt} decoding="async" loading="lazy" />
                  </div>
                  <h2 className="counter" data-target={int(s.target ?? 0)}>
                    0
                  </h2>
                  <Html as="h4" html={await block('impact-goals', `summary_${s.key}_label`)} />
                  <Html as="p" html={await blockRich('impact-goals', `summary_${s.key}_text`)} />
                </div>
              )),
            )}
          </section>
          <button type="button" className="carousel-arrow carousel-arrow--next" aria-label="Next summary">
            {NEXT}
          </button>
        </div>

        <section className="cta">
          <Html as="h2" html={await block('impact-goals', 'cta_title')} />
          <Html as="p" html={await blockRich('impact-goals', 'cta_text')} />
          <button type="button" data-scroll-to=".new-svg-chart-wrapper">
            Explore Our Journey
          </button>
        </section>
      </div>
    </SitePage>
  );
}
