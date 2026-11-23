/** Renders trusted, already-escaped HTML (block()/blockRich() output) inside a given tag. */
import { createElement, type HTMLAttributes } from 'react';

type Props = HTMLAttributes<HTMLElement> & { as?: string; html: string };

export default function Html({ as = 'div', html, ...rest }: Props) {
  return createElement(as, { ...rest, dangerouslySetInnerHTML: { __html: html } });
}
