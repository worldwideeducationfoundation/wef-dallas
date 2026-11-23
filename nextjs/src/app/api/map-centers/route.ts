/** Centers for the home-page map (port of public/api/map-centers.php). Also at /api/map-centers.php. */
import fs from 'node:fs';
import { tryFetchAll } from '@/lib/db';
import { publicPath } from '@/lib/paths';

export const dynamic = 'force-dynamic';

export async function GET() {
  const rows = await tryFetchAll(
    `SELECT lead_name, sponsor, village, district, center_model, latitude, longitude, display_order
     FROM map_centers WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, id`,
  );
  const headers = { 'Content-Type': 'application/geo+json; charset=utf-8' };
  if (!rows || rows.length === 0) {
    return new Response(fs.readFileSync(publicPath('assets/data/locations_map1.geojson')), { headers });
  }
  const features = rows.map((r) => ({
    type: 'Feature',
    geometry: { type: 'Point', coordinates: [Number(r.longitude), Number(r.latitude)] },
    properties: {
      name: String(r.lead_name ?? ''),
      sponsor: String(r.sponsor),
      village: String(r.village),
      district: String(r.district),
      centerModel: String(r.center_model),
      sno: Math.trunc(Number(r.display_order)),
    },
  }));
  return new Response(JSON.stringify({ type: 'FeatureCollection', features }), { headers });
}
