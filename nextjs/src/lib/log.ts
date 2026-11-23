/** storage/logs/site-error.log with 5MB rotation (port of publicLog() in includes/bootstrap.php). */
import 'server-only';
import fs from 'node:fs';
import path from 'node:path';
import { STORAGE_DIR } from './paths';

const LOG_DIR = path.join(STORAGE_DIR, 'logs');

function stamp(): string {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

export function appendLog(file: string, line: string, keep = 5, maxBytes = 5 * 1024 * 1024): void {
  try {
    fs.mkdirSync(LOG_DIR, { recursive: true });
    const logPath = path.join(LOG_DIR, file);
    if (fs.existsSync(logPath) && fs.statSync(logPath).size >= maxBytes) {
      if (fs.existsSync(`${logPath}.${keep}`)) fs.rmSync(`${logPath}.${keep}`, { force: true });
      for (let i = keep - 1; i >= 1; i--) if (fs.existsSync(`${logPath}.${i}`)) fs.renameSync(`${logPath}.${i}`, `${logPath}.${i + 1}`);
      fs.renameSync(logPath, `${logPath}.1`);
    }
    fs.appendFileSync(logPath, line);
  } catch {
    console.error('WEF site: ' + line.trim());
  }
}

export function publicLog(level: string, message: string): void {
  appendLog('site-error.log', `[${stamp()}] ${level}: ${message}\n`);
}

export { stamp as logTimestamp };
