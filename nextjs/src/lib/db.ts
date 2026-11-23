/**
 * MySQL access (port of includes/database.php + includes/db.php).
 *
 * Same database and schema as the PHP site. Every query takes real SQL and
 * bound parameters; only identifiers from allow-lists are ever interpolated.
 * When DB_HOST is not set, or MySQL is down, getDb() returns null and the
 * public site falls back to the built-in content (src/data/static-data.json),
 * exactly like the PHP site does.
 */
import 'server-only';
import mysql, { type Pool, type PoolConnection, type RowDataPacket, type ResultSetHeader } from 'mysql2/promise';
import { AsyncLocalStorage } from 'node:async_hooks';

type Row = Record<string, any>;
type Params = unknown[] | Record<string, unknown>;

const globalForDb = globalThis as unknown as { __wefPool?: Pool | null; __wefPoolDownUntil?: number };

export function dbConfigured(): boolean {
  return Boolean(process.env.DB_HOST && process.env.DB_NAME && process.env.DB_USER) && process.env.DISABLE_DATABASE !== '1';
}

function pool(): Pool | null {
  if (!dbConfigured()) return null;
  if (globalForDb.__wefPool === undefined || globalForDb.__wefPool === null) {
    globalForDb.__wefPool = mysql.createPool({
      host: process.env.DB_HOST,
      port: Number(process.env.DB_PORT || 3306),
      database: process.env.DB_NAME,
      user: process.env.DB_USER,
      password: process.env.DB_PASS ?? '',
      charset: 'utf8mb4',
      connectionLimit: Number(process.env.DB_POOL_SIZE || 5),
      connectTimeout: 2000,
      namedPlaceholders: true,
      dateStrings: true,
      decimalNumbers: true,
      supportBigNumbers: true,
      timezone: 'local',
    });
  }
  return globalForDb.__wefPool;
}

/** Transaction connection for the current async context (see dbTransaction). */
const txStore = new AsyncLocalStorage<PoolConnection>();

/** True when a database is configured and reachable. Cached down-state for 10s. */
export async function getDb(): Promise<Pool | null> {
  const p = pool();
  if (!p) return null;
  if ((globalForDb.__wefPoolDownUntil ?? 0) > Date.now()) return null;
  return p;
}

function markDown(): void {
  globalForDb.__wefPoolDownUntil = Date.now() + 10_000;
}

function isConnectionError(e: unknown): boolean {
  const code = (e as { code?: string })?.code ?? '';
  return ['ECONNREFUSED', 'ETIMEDOUT', 'ENOTFOUND', 'PROTOCOL_CONNECTION_LOST', 'ER_ACCESS_DENIED_ERROR', 'ER_BAD_DB_ERROR', 'EHOSTUNREACH'].includes(code);
}

/** Converts PHP-style ":name" or "?" params into mysql2 calls. */
async function run(sql: string, params: Params = []): Promise<[any, any]> {
  const conn = txStore.getStore();
  const target = conn ?? (await getDb());
  if (!target) throw new DbUnavailableError();
  try {
    // query (not execute) so LIMIT ? and IN (?,?) bind the same way PDO did.
    return await target.query(sql, params as any);
  } catch (e) {
    if (isConnectionError(e)) markDown();
    throw e;
  }
}

export class DbUnavailableError extends Error {
  constructor() {
    super('Database is not configured or not reachable');
  }
}

export async function dbFetchAll<T = Row>(sql: string, params: Params = []): Promise<T[]> {
  const [rows] = await run(sql, params);
  return rows as T[];
}

export async function dbFetchOne<T = Row>(sql: string, params: Params = []): Promise<T | null> {
  const rows = await dbFetchAll<T>(sql, params);
  return rows[0] ?? null;
}

export async function dbFetchColumn<T = unknown>(sql: string, params: Params = [], fallback: T | null = null): Promise<T | null> {
  const [rows] = await run(sql, params);
  const first = (rows as RowDataPacket[])[0];
  if (!first) return fallback;
  const value = Object.values(first)[0];
  return (value === undefined ? fallback : value) as T;
}

export async function dbFetchColumnAll<T = unknown>(sql: string, params: Params = []): Promise<T[]> {
  const [rows] = await run(sql, params);
  return (rows as RowDataPacket[]).map((r) => Object.values(r)[0] as T);
}

export async function dbFetchPairs(sql: string, params: Params = []): Promise<Record<string, any>> {
  const [rows] = await run(sql, params);
  const out: Record<string, any> = {};
  for (const r of rows as RowDataPacket[]) {
    const [k, v] = Object.values(r);
    out[String(k)] = v;
  }
  return out;
}

export async function dbExecute(sql: string, params: Params = []): Promise<number> {
  const [res] = await run(sql, params);
  return (res as ResultSetHeader).affectedRows;
}

export async function dbInsert(sql: string, params: Params = []): Promise<number> {
  const [res] = await run(sql, params);
  return Number((res as ResultSetHeader).insertId);
}

/** Runs work in a transaction; nested calls join the open one. */
export async function dbTransaction<T>(work: () => Promise<T>): Promise<T> {
  if (txStore.getStore()) return work();
  const p = await getDb();
  if (!p) throw new DbUnavailableError();
  const conn = await p.getConnection();
  try {
    await conn.beginTransaction();
    const result = await txStore.run(conn, work);
    await conn.commit();
    return result;
  } catch (e) {
    await conn.rollback().catch(() => {});
    throw e;
  } finally {
    conn.release();
  }
}

/** Positional IN (...) placeholders for a list of ids (ints > 0, unique). */
export function dbIdPlaceholders(ids: unknown[]): { sql: string; ids: number[] } {
  const clean = [...new Set(ids.map((v) => Number.parseInt(String(v), 10)).filter((n) => Number.isInteger(n) && n > 0))];
  return { sql: clean.map(() => '?').join(','), ids: clean };
}

/**
 * Public-site read: runs the query, returns null on any failure (so the page
 * falls back to built-in content), mirroring the PHP try/catch pattern.
 */
export async function tryFetchAll<T = Row>(sql: string, params: Params = []): Promise<T[] | null> {
  try {
    if (!(await getDb())) return null;
    return await dbFetchAll<T>(sql, params);
  } catch {
    return null;
  }
}

export async function tryFetchOne<T = Row>(sql: string, params: Params = []): Promise<T | null | undefined> {
  try {
    if (!(await getDb())) return undefined;
    return await dbFetchOne<T>(sql, params);
  } catch {
    return undefined;
  }
}
