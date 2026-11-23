/** String helpers shared by the public site and admin (port of e(), richText()). */

const ESC: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

/** htmlspecialchars($v, ENT_QUOTES, 'UTF-8') */
export function e(value: unknown): string {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ESC[c]);
}

/** nl2br(e($v), false) */
export function richText(value: unknown): string {
  return e(value).replace(/(\r\n|\n\r|\r|\n)/g, '<br>$1');
}

/** PHP trim() default character set. */
export function phpTrim(value: unknown): string {
  return String(value ?? '').replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
}

export function nonEmpty(value: unknown): boolean {
  return phpTrim(value) !== '';
}
