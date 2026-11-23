import type { CSSProperties } from 'react';

/** "height: 220px; --bg-image: url('x')" -> React style object (keeps the PHP markup's inline styles verbatim). */
export function css(text: string | undefined | null): CSSProperties | undefined {
  if (!text || !text.trim()) return undefined;
  const out: Record<string, string> = {};
  // split on ; not inside url(...) or quotes
  let depth = 0;
  let quote = '';
  let cur = '';
  const parts: string[] = [];
  for (const ch of text) {
    if (quote) {
      if (ch === quote) quote = '';
    } else if (ch === '"' || ch === "'") quote = ch;
    else if (ch === '(') depth++;
    else if (ch === ')') depth--;
    else if (ch === ';' && depth === 0) {
      parts.push(cur);
      cur = '';
      continue;
    }
    cur += ch;
  }
  parts.push(cur);
  for (const decl of parts) {
    const i = decl.indexOf(':');
    if (i === -1) continue;
    const prop = decl.slice(0, i).trim();
    const value = decl.slice(i + 1).trim();
    if (!prop) continue;
    const key = prop.startsWith('--') ? prop : prop.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());
    out[key] = value;
  }
  return out as CSSProperties;
}
