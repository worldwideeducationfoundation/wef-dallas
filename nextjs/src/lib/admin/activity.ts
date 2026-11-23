/** Admin activity log + admin log file (port of logActivity()/adminLog()). */
import 'server-only';
import { dbExecute } from '../db';
import { appendLog, logTimestamp } from '../log';

export type RequestMeta = { ip: string; userAgent: string };

export function adminLog(level: string, message: string): void {
  appendLog('admin-error.log', `[${logTimestamp()}] ${level}: ${message}\n`);
}

export async function logActivity(userId: number | null, action: string, module = 'auth', details: string | null = null, meta?: RequestMeta): Promise<void> {
  try {
    await dbExecute(
      `INSERT INTO activity_logs (user_id, action, module, details, ip_address, user_agent)
       VALUES (:user_id, :action, :module, :details, :ip, :agent)`,
      {
        user_id: userId,
        action,
        module,
        details: details === null ? null : [...details].slice(0, 255).join(''),
        ip: meta?.ip ?? '0.0.0.0',
        agent: (meta?.userAgent ?? '').slice(0, 255),
      },
    );
  } catch (e) {
    adminLog('WARN', 'activity log write failed: ' + (e as Error).message);
  }
}

export function requestMeta(request: Request): RequestMeta {
  return {
    ip: (request.headers.get('x-forwarded-for') ?? '').split(',')[0].trim() || request.headers.get('x-real-ip') || '0.0.0.0',
    userAgent: request.headers.get('user-agent') ?? '',
  };
}
