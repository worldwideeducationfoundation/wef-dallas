/** date('Y-m-d H:i:s') in server-local time, the format the PHP site wrote to MySQL. */
export function mysqlDateTime(d: Date = new Date()): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

/** PHP date('Y'). */
export function currentYear(): number {
  return new Date().getFullYear();
}
