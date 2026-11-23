/** Port of public/our-team.php */
import type { Metadata } from 'next';
import SitePage from '@/components/site/SitePage';
import Html from '@/components/site/Html';
import { block, blockRich } from '@/lib/blocks';
import { pageHero, publishedTeamBySection, uploadUrl } from '@/lib/content';
import { phpTrim, richText } from '@/lib/html';
import { pageMetadata } from '@/lib/seo';
import { requirePageEnabled } from '@/lib/visibility';
import { css } from '@/lib/style';

const SEO = {
  title: 'Our Team — Worldwide Education Foundation',
  description: 'Meet the Advisory Board Members and leadership team of the Worldwide Education Foundation.',
};

export async function generateMetadata(): Promise<Metadata> {
  return pageMetadata(SEO);
}

function anchorFor(name: string): string {
  return String(name)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export default async function OurTeamPage() {
  await requirePageEnabled('our-team');
  const hero = await pageHero('our-team');
  const sections = await publishedTeamBySection(['Advisory Board Members', 'Chitral Team', 'Collaborating Partners']);

  return (
    <SitePage
      seo={SEO}
      tagline="Providing the Seeds for Growth"
      scripts={['js/our-team.js']}
      afterFooter={
        <div className="team-modal-overlay" id="team-modal-overlay">
          <div className="team-modal-content">
            <div className="team-modal-header-actions">
              <button className="team-modal-back" aria-label="Back to team">
                &larr;
              </button>
              <button className="team-modal-close" id="team-modal-close" aria-label="Close modal">
                &times;
              </button>
            </div>
            <div className="team-modal-layout">
              <div className="team-modal-image">
                <img id="team-modal-img" src={undefined} alt="" decoding="async" />
              </div>
              <div className="team-modal-body">
                <h3 id="team-modal-name"></h3>
                <span className="team-role" id="team-modal-role"></span>
                <div className="team-bio" id="team-modal-bio"></div>
              </div>
            </div>
          </div>
        </div>
      }
    >
      <section className={'team-page-header' + hero.className} style={css(hero.style)}>
        {hero.hasImage && (
          <>
            <div className="page-hero__bg" aria-hidden="true"></div>
            <div className="page-hero__overlay" aria-hidden="true"></div>
          </>
        )}
        <div className="container">
          <Html as="h1" html={await block('our-team', 'page_title')} />
          <Html as="p" html={await blockRich('our-team', 'intro')} />
        </div>
      </section>

      <section className="section team-section">
        <div className="container">
          {Object.entries(sections).map(([sectionTitle, members]) => {
            const sp = sectionTitle.indexOf(' ');
            const label = sp === -1 ? sectionTitle : sectionTitle.slice(0, sp);
            const rest = sp === -1 ? '' : sectionTitle.slice(sp + 1);
            return [
              <div className="team-group-header" key={sectionTitle + '-h'}>
                <div className="header-fieldset">
                  <span className="header-fieldset-label">{label}</span>
                  <h2 className="no-underline">{rest}</h2>
                </div>
              </div>,
              <div className={'team-grid' + (sectionTitle === 'Collaborating Partners' ? ' team-grid--centered' : '')} key={sectionTitle + '-g'}>
                {members.map((member, i) => {
                  const photo = uploadUrl(member.image_path);
                  return (
                    <div className="team-card reveal" id={anchorFor(member.name)} key={i}>
                      {photo !== null && (
                        <div className="team-card-image">
                          <img src={photo} alt={member.name} loading="lazy" decoding="async" />
                        </div>
                      )}
                      <div className="team-card-body">
                        <h3>{member.name}</h3>
                        <span className="team-role">{member.role}</span>
                        {phpTrim(member.bio) !== '' && (
                          <>
                            <Html className="team-bio" html={richText(member.bio)} />
                            <button className="team-read-more" aria-expanded="false">
                              Read More <span>&rarr;</span>
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>,
            ];
          })}
        </div>
      </section>
    </SitePage>
  );
}
