/** Port of public/our-mission.php (WEF At A Glance) */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import Html from '@/components/site/Html';
import { block, blockImage, blockRich, blockValue } from '@/lib/blocks';
import { e } from '@/lib/html';
import { missionBulletList, publishedMissionProjects } from '@/lib/content';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = {
  title: 'WEF At A Glance — Worldwide Education Foundation',
  description: 'Worldwide Education Foundation (WEF) At A Glance: Our Active Projects, Vision, Mission, and Beliefs.',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function OurMissionPage() {
  await requirePageEnabled('our-mission');
  const projects = await publishedMissionProjects();
  const b = (k: string) => block('our-mission', k);
  const r = (k: string) => blockRich('our-mission', k);

  return (
    <SitePage seo={SEO} styles={['css/pages/our-mission.css']} mainClassName="glance-page-container">
      <div className="glance-title-header">
        <Html as="h1" className="glance-main-title" html={await b('page_title')} />
        <Html as="h2" className="glance-sub-title" html={await b('subtitle')} />
      </div>

      <div className="glance-card-frame">
        <div className="glance-table-wrapper">
          <table className="glance-pdf-table">
            <thead>
              <tr>
                <th style={css('width: 24%;')}>WHAT</th>
                <th style={css('width: 22%;')}>WHERE</th>
                <th style={css('width: 18%;')}>WHO</th>
                <th style={css('width: 18%;')}>IMPACT</th>
                <th style={css('width: 18%;')}>NOTES</th>
              </tr>
            </thead>
            <tbody>
              {projects.map((p, i) => (
                <tr key={i}>
                  <td>
                    <div className="project-what-header">{p.what_header}</div>
                    {p.what_sub ? <div className="project-what-sub">{p.what_sub}</div> : null}
                    {p.launch_year ? <div className="project-launch-date">(Launched in {p.launch_year})</div> : null}
                  </td>
                  <td data-label="Where" dangerouslySetInnerHTML={{ __html: missionBulletList(p.where_text ?? null) }} />
                  <td data-label="Who" dangerouslySetInnerHTML={{ __html: missionBulletList(p.who_text ?? null) }} />
                  <td data-label="Impact" dangerouslySetInnerHTML={{ __html: missionBulletList(p.impact_text ?? null) }} />
                  <td
                    data-label="Notes"
                    dangerouslySetInnerHTML={{
                      __html: missionBulletList(p.notes_text ?? null) + (p.notes_highlight ? '<div class="notes-bold-highlight">' + e(p.notes_highlight) + '</div>' : ''),
                    }}
                  />
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="pillars-image-grid">
        <div>
          {await Promise.all([1, 2, 3].map(async (n) => (
            <div className="pillar-item-box" key={n}>
              <Html as="h4" html={await b(`pillar${n}_title`)} />
              <Html as="p" html={await r(`pillar${n}_text`)} />
            </div>
          )))}
        </div>
        <div className="glance-photo-card">
          <img
            width={1536}
            height={1152}
            src={await blockImage('our-mission', 'pillars_image')}
            alt="Bright Beginnings Early Childhood Learning Center in Chitral"
            loading="lazy"
            decoding="async"
          />
        </div>
      </div>

      <div className="glance-footer-note">
        <Html as="p" html={await r('footer_note')} />
        <Html className="glance-footer-date" html={await b('footer_date')} />
        <div>
          <a href="assets/PDF/WEF-at-a-Glance-Final-Updated.pdf" target="_blank" rel="noopener noreferrer" className="btn-download-pdf-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            {await blockValue('our-mission', 'download_button_label')}
          </a>
        </div>
      </div>
    </SitePage>
  );
}
