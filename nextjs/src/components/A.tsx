/**
 * Internal link that turns into plain text when its page is switched off in
 * admin -> Pages On/Off (what the PHP output filter did to every <a>).
 * Plain <a>, never next/link: every navigation is a full page load, like the
 * PHP site, so the site's own scripts (js/main.js etc.) run on each page.
 */
import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { disabledLinkSlugs, hrefSlug } from '@/lib/visibility';

type Props = AnchorHTMLAttributes<HTMLAnchorElement> & { href: string; children?: ReactNode };

export default async function A({ href, children, className, id, ...rest }: Props) {
  const slug = hrefSlug(href);
  if (slug && (await disabledLinkSlugs()).includes(slug)) {
    return (
      <span className={(className ? className.trim() + ' ' : '') + 'link-disabled'} id={id} aria-disabled="true">
        {children}
      </span>
    );
  }
  return (
    <a href={href} className={className} id={id} {...rest}>
      {children}
    </a>
  );
}
