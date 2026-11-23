/**
 * Map center location verifier (port of public/verify-centers.php).
 * GET  /verify-centers                         the tool page (public/js/verifier.js)
 * GET  /verify-centers?action=get_state         saved verdicts
 * POST /verify-centers?action=save              save verdicts (JSON body)
 * POST /verify-centers?action=apply_to_geojson  write corrections into the map data file
 * GET  /verify-centers?action=export            download the corrected GeoJSON
 * State lives in storage/verification_audit.json, as in the PHP site.
 */
import fs from 'node:fs';
import path from 'node:path';
import { VERIFY_CENTERS_HTML } from '@/lib/verify-centers-html';
import { publicPath, STORAGE_DIR } from '@/lib/paths';
import { mysqlDateTime } from '@/lib/time';

export const dynamic = 'force-dynamic';

const AUDIT_FILE = path.join(STORAGE_DIR, 'verification_audit.json');
const LOCATIONS = publicPath('assets/data/locations_map1.geojson');

const json = (data: unknown, status = 200, extra: Record<string, string> = {}) =>
  new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json', ...extra } });

function readJson(file: string): any {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return null;
  }
}

const stamp = () => mysqlDateTime().replace(/[-:]/g, '').replace(' ', '_');

export async function GET(request: Request) {
  const action = new URL(request.url).searchParams.get('action');
  if (action === 'get_state') {
    return new Response(fs.existsSync(AUDIT_FILE) ? fs.readFileSync(AUDIT_FILE, 'utf8') : '{}', { headers: { 'Content-Type': 'application/json' } });
  }
  if (action === 'export') {
    const audit = readJson(AUDIT_FILE) ?? {};
    const locations = readJson(LOCATIONS) ?? { features: [] };
    for (const f of locations.features ?? []) {
      const sno = f.properties?.sno ?? null;
      if (sno && audit[sno]) {
        f.properties.verification = audit[sno].verdict;
        if (audit[sno].note) f.properties.verification_note = audit[sno].note;
        if (audit[sno].corrected_lat && audit[sno].corrected_lon) {
          f.properties.original_coordinates = f.geometry.coordinates;
          f.geometry.coordinates = [Number(audit[sno].corrected_lon), Number(audit[sno].corrected_lat)];
        }
      }
    }
    return json(locations, 200, { 'Content-Disposition': 'attachment; filename="locations_map1_verified.geojson"' });
  }
  return new Response(VERIFY_CENTERS_HTML, { headers: { 'Content-Type': 'text/html; charset=UTF-8' } });
}

export async function POST(request: Request) {
  const action = new URL(request.url).searchParams.get('action');
  if (action === 'save') {
    let data: unknown = null;
    try {
      data = JSON.parse(await request.text());
    } catch {
      data = null;
    }
    if (data === null) return json({ status: 'error', message: 'Invalid JSON' }, 400);
    fs.mkdirSync(STORAGE_DIR, { recursive: true });
    fs.writeFileSync(AUDIT_FILE, JSON.stringify(data, null, 4));
    return json({ status: 'success', saved_at: mysqlDateTime() });
  }
  if (action === 'apply_to_geojson') {
    const audit = readJson(AUDIT_FILE) ?? {};
    const locations = readJson(LOCATIONS);
    if (locations === null) return json({ status: 'error', message: 'Failed to read locations_map1.geojson' }, 500);
    const backupDir = path.join(STORAGE_DIR, 'backups');
    fs.mkdirSync(backupDir, { recursive: true });
    fs.copyFileSync(LOCATIONS, LOCATIONS + '.bak');
    fs.copyFileSync(LOCATIONS, path.join(backupDir, `locations_map1_${stamp()}.geojson`));
    let applied = 0;
    for (const f of locations.features ?? []) {
      const sno = f.properties?.sno ?? null;
      const entry = sno ? audit[sno] : null;
      if (entry && entry.verdict === 'wrong' && entry.corrected_lat && entry.corrected_lon) {
        f.properties.original_coordinates = f.geometry.coordinates;
        f.geometry.coordinates = [Number(entry.corrected_lon), Number(entry.corrected_lat)];
        if (entry.note) f.properties.correction_note = entry.note;
        applied++;
      }
    }
    fs.writeFileSync(LOCATIONS, JSON.stringify(locations, null, 4));
    return json({ status: 'success', applied, message: `Successfully updated ${applied} center coordinates in locations_map1.geojson!` });
  }
  return json({ status: 'error', message: 'Unknown action' }, 400);
}
