/** The standard .page-hero block with the admin-set header photo (pageHero() in content.php). */
import type { ReactNode } from 'react';
import { pageHero } from '@/lib/content';
import { css } from '@/lib/style';

type Props = { page: string; style?: string; className?: string; children: ReactNode };

export default async function PageHero({ page, style = '', className = '', children }: Props) {
  const hero = await pageHero(page, style);
  return (
    <section className={'page-hero' + (className ? ' ' + className : '') + hero.className} style={css(hero.style)}>
      {hero.hasImage && (
        <>
          <div className="page-hero__bg" aria-hidden="true"></div>
          <div className="page-hero__overlay" aria-hidden="true"></div>
        </>
      )}
      {children}
    </section>
  );
}
