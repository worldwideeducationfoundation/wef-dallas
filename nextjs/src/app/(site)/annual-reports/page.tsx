/** Port of public/annual-reports.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import PageHero from '@/components/site/PageHero';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { publishedReports } from '@/lib/content';
import { phpTrim } from '@/lib/html';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = { title: 'Annual Reports — Worldwide Education Foundation', description: 'Read our annual reports and see our financial and impact metrics.' };

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

export default async function AnnualReportsPage() {
  await requirePageEnabled('annual-reports');
  const reports = await publishedReports();
  return (
    <SitePage seo={SEO}>
      <PageHero page="annual-reports" style="padding-bottom: 2rem;">
        <div className="container">
          <Html as="h1" html={await block('annual-reports', 'page_title')} />
          <Html as="p" html={await blockRich('annual-reports', 'intro')} />
        </div>
      </PageHero>

      <section className="page-content" style={css('padding-top: 1rem; padding-bottom: 3rem;')}>
        <div className="container" style={css('max-width: 1000px; margin: 0 auto;')}>
          <div className="wef-doc-list">
            {reports.length === 0 && <div className="empty-state">Annual reports will be published here soon.</div>}
            {reports.map((report, i) => (
              <div className="wef-doc-item reveal" key={i} style={i > 0 ? css(`transition-delay: ${(i * 0.1).toFixed(1)}s;`) : undefined}>
                <div className="wef-doc-info">
                  <h3 className="wef-doc-title">{report.title}</h3>
                  {phpTrim(report.description) !== '' && <p className="wef-doc-meta">{report.description}</p>}
                </div>
                {phpTrim(report.pdf_path) !== '' && (
                  <div className="wef-doc-actions">
                    <a href={report.pdf_path} target="_blank" rel="noopener" className="wef-doc-btn view-btn">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                      View
                    </a>
                    <a href={report.pdf_path} download className="wef-doc-btn download-btn">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" x2="12" y1="15" y2="3" />
                      </svg>
                      Download
                    </a>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>
    </SitePage>
  );
}
