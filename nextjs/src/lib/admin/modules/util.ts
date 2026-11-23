import 'server-only';
import type { PublicCtx } from '../context';

/** A page returns HTML, or a full Response (JSON, files, redirects). */
export type Handler = (ctx: PublicCtx) => Promise<string | Response>;
export type Routes = Record<string, Handler>;

/** PHP (int) cast. */
export function int(v: unknown): number {
  if (typeof v === 'number') return Math.trunc(v);
  const n = Number.parseInt(String(v ?? '').trim(), 10);
  return Number.isNaN(n) ? 0 : n;
}

/** PHP mb_substr */
export function mb_substr(s: unknown, start: number, length?: number): string {
  return [...String(s ?? '')].slice(start, length === undefined ? undefined : start + length).join('');
}

/** PHP mb_strimwidth (width counted in characters, which is what it does for Latin text) */
export function mb_strimwidth(s: unknown, start: number, width: number, marker = ''): string {
  const chars = [...String(s ?? '')].slice(start);
  if (chars.length <= width) return chars.join('');
  return chars.slice(0, Math.max(0, width - [...marker].length)).join('') + marker;
}

/** PHP number_format with default separators */
export function number_format(n: unknown, decimals = 0): string {
  return Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** PHP basename */
export function basename(p: unknown): string {
  return String(p ?? '').split('/').pop() ?? '';
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** PHP date($format, strtotime($value)) for the format letters the admin uses. */
export function phpDate(format: string, value: unknown): string {
  const d = value instanceof Date ? value : new Date(String(value ?? '').replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return '';
  const p = (n: number) => String(n).padStart(2, '0');
  let out = '';
  for (let i = 0; i < format.length; i++) {
    const c = format[i];
    if (c === '\\') {
      out += format[++i] ?? '';
      continue;
    }
    const h = d.getHours();
    switch (c) {
      case 'j': out += d.getDate(); break;
      case 'd': out += p(d.getDate()); break;
      case 'F': out += MONTHS[d.getMonth()]; break;
      case 'M': out += MONTHS[d.getMonth()].slice(0, 3); break;
      case 'm': out += p(d.getMonth() + 1); break;
      case 'n': out += d.getMonth() + 1; break;
      case 'Y': out += d.getFullYear(); break;
      case 'g': out += h % 12 === 0 ? 12 : h % 12; break;
      case 'G': out += h; break;
      case 'H': out += p(h); break;
      case 'i': out += p(d.getMinutes()); break;
      case 's': out += p(d.getSeconds()); break;
      case 'a': out += h < 12 ? 'am' : 'pm'; break;
      case 'A': out += h < 12 ? 'AM' : 'PM'; break;
      default: out += c;
    }
  }
  return out;
}
