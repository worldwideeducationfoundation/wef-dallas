<?php
/**
 * Worldwide Education Foundation — Interactive Center Pinpoint Verifier
 * Compliant with strict Content-Security-Policy (all JS in js/verifier.js)
 */

$auditFile = __DIR__ . '/../storage/verification_audit.json';
$locationsPath = __DIR__ . '/assets/data/locations_map1.geojson';

// Return saved verification state
if (isset($_GET['action']) && $_GET['action'] === 'get_state') {
    header('Content-Type: application/json');
    if (file_exists($auditFile)) {
        echo file_get_contents($auditFile);
    } else {
        echo '{}';
    }
    exit;
}

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if ($data !== null) {
        file_put_contents($auditFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        echo json_encode(['status' => 'success', 'saved_at' => date('Y-m-d H:i:s')]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    }
    exit;
}

// Apply corrections directly to the live locations_map1.geojson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'apply_to_geojson') {
    header('Content-Type: application/json');
    $auditData = file_exists($auditFile) ? json_decode(file_get_contents($auditFile), true) : [];
    $locations = file_exists($locationsPath) ? json_decode(file_get_contents($locationsPath), true) : null;

    if ($locations === null) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to read locations_map1.geojson']);
        exit;
    }

    // Create backups
    $backupDir = __DIR__ . '/../storage/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
    copy($locationsPath, $locationsPath . '.bak');
    copy($locationsPath, $backupDir . '/locations_map1_' . date('Ymd_His') . '.geojson');

    $appliedCount = 0;
    foreach ($locations['features'] as &$f) {
        $sno = $f['properties']['sno'] ?? null;
        if ($sno && isset($auditData[$sno])) {
            $entry = $auditData[$sno];
            if ($entry['verdict'] === 'wrong' && !empty($entry['corrected_lat']) && !empty($entry['corrected_lon'])) {
                $f['properties']['original_coordinates'] = $f['geometry']['coordinates'];
                $f['geometry']['coordinates'] = [
                    (float) $entry['corrected_lon'],
                    (float) $entry['corrected_lat']
                ];
                if (!empty($entry['note'])) {
                    $f['properties']['correction_note'] = $entry['note'];
                }
                $appliedCount++;
            }
        }
    }

    file_put_contents($locationsPath, json_encode($locations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => 'success', 'applied' => $appliedCount, 'message' => "Successfully updated {$appliedCount} center coordinates in locations_map1.geojson!"]);
    exit;
}

// Handle Export of Verified GeoJSON
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $auditData = file_exists($auditFile) ? json_decode(file_get_contents($auditFile), true) : [];
    $locations = file_exists($locationsPath) ? json_decode(file_get_contents($locationsPath), true) : ['features' => []];

    foreach ($locations['features'] as &$f) {
        $sno = $f['properties']['sno'] ?? null;
        if ($sno && isset($auditData[$sno])) {
            $f['properties']['verification'] = $auditData[$sno]['verdict'];
            if (!empty($auditData[$sno]['note'])) {
                $f['properties']['verification_note'] = $auditData[$sno]['note'];
            }
            if (!empty($auditData[$sno]['corrected_lat']) && !empty($auditData[$sno]['corrected_lon'])) {
                $f['properties']['original_coordinates'] = $f['geometry']['coordinates'];
                $f['geometry']['coordinates'] = [
                    (float) $auditData[$sno]['corrected_lon'],
                    (float) $auditData[$sno]['corrected_lat']
                ];
            }
        }
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="locations_map1_verified.geojson"');
    echo json_encode($locations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Map Center Location Verifier — WEF</title>
  <link rel="stylesheet" href="assets/leaflet/leaflet.css">
  <style>
    :root {
      --primary: #0e611d;
      --primary-light: #e8f5e9;
      --danger: #dc2626;
      --danger-light: #fef2f2;
      --warning: #d97706;
      --warning-light: #fffbeb;
      --info: #2563eb;
      --info-light: #eff6ff;
      --bg: #f8fafc;
      --card-bg: #ffffff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --radius: 12px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: var(--bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow: hidden;
    }
    /* Top Bar */
    header {
      background: #0f172a;
      color: #fff;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .header-title {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .header-badge {
      background: #1e293b;
      color: #38bdf8;
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    /* Scoreboard */
    .scoreboard {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.85rem;
      flex-wrap: wrap;
    }
    .score-item {
      display: flex;
      align-items: center;
      gap: 5px;
      background: rgba(255,255,255,0.08);
      padding: 5px 10px;
      border-radius: 8px;
      font-weight: 500;
    }
    .score-item strong { font-weight: 700; }
    .score-total { color: #f8fafc; }
    .score-right { color: #4ade80; }
    .score-wrong { color: #f87171; }
    .score-corrected { color: #60a5fa; }
    .score-pending { color: #cbd5e1; }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-action-header {
      color: #fff;
      text-decoration: none;
      font-size: 0.78rem;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      border: none;
      transition: background 0.2s;
    }
    .btn-apply-geojson { background: #16a34a; }
    .btn-apply-geojson:hover { background: #15803d; }
    .btn-export { background: #0284c7; }
    .btn-export:hover { background: #0369a1; }

    /* Layout */
    .main-container {
      display: flex;
      flex: 1;
      height: calc(100vh - 58px);
      overflow: hidden;
      position: relative;
    }
    .map-wrapper {
      flex: 1;
      height: 100%;
      position: relative;
      background: #0f172a;
    }
    #map {
      width: 100%;
      height: 100%;
      min-height: 350px;
    }

    /* On-map Location Picker HUD */
    .map-picker-hud {
      position: absolute;
      top: 16px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 1000;
      pointer-events: none;
    }
    .hud-pill {
      pointer-events: auto;
      background: rgba(15, 23, 42, 0.94);
      backdrop-filter: blur(10px);
      color: #fff;
      border: 1.5px solid #38bdf8;
      border-radius: 30px;
      padding: 8px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.35);
      font-size: 0.85rem;
    }
    .hud-status {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: #38bdf8;
    }
    .hud-indicator {
      width: 10px;
      height: 10px;
      background: #38bdf8;
      border-radius: 50%;
      display: inline-block;
      animation: pulseIndicator 1s infinite alternate;
    }
    @keyframes pulseIndicator {
      from { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); }
      to { box-shadow: 0 0 0 8px rgba(56, 189, 248, 0); }
    }
    .hud-coords {
      font-family: monospace;
      font-size: 0.88rem;
      color: #f1f5f9;
    }
    .hud-dist {
      color: #fde047;
      font-size: 0.8rem;
      font-weight: 600;
      margin-left: 6px;
    }
    .hud-actions {
      display: flex;
      gap: 6px;
    }
    .hud-btn {
      border: none;
      border-radius: 20px;
      padding: 5px 12px;
      font-size: 0.8rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.15s;
    }
    .hud-btn-save { background: #16a34a; color: #fff; }
    .hud-btn-save:hover { background: #15803d; }
    .hud-btn-cancel { background: #475569; color: #fff; }
    .hud-btn-cancel:hover { background: #64748b; }

    .sidebar {
      width: 500px;
      background: var(--card-bg);
      border-left: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      overflow-y: auto;
      box-shadow: -4px 0 20px rgba(0,0,0,0.05);
      z-index: 900;
    }

    /* Verification Card Header */
    .card-nav {
      padding: 14px 18px;
      background: #f1f5f9;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .nav-buttons {
      display: flex;
      gap: 8px;
    }
    .nav-btn {
      background: #fff;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 6px 14px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      color: var(--text-main);
      transition: all 0.2s;
    }
    .nav-btn:hover:not(:disabled) {
      background: #0f172a;
      color: #fff;
      border-color: #0f172a;
    }
    .nav-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }
    .stepper-label {
      font-weight: 700;
      font-size: 1.05rem;
      color: #0f172a;
    }

    /* Center Content */
    .card-body {
      padding: 18px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .center-status-banner {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .status-pending { background: #f1f5f9; color: #475569; }
    .status-right { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .status-wrong { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

    .anomaly-alert {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-left: 4px solid #d97706;
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 0.84rem;
      color: #92400e;
      display: flex;
      gap: 8px;
      line-height: 1.4;
      font-weight: 500;
    }

    .detail-card {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 14px;
      display: grid;
      gap: 10px;
    }
    .detail-row {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .detail-row label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
    }
    .detail-row .value {
      font-size: 0.98rem;
      font-weight: 600;
      color: var(--text-main);
    }
    .model-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 600;
      width: fit-content;
    }
    .coords-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px dashed #cbd5e1;
    }
    .coords-text {
      font-family: monospace;
      font-size: 0.92rem;
      color: #0284c7;
      font-weight: 600;
    }
    .gmaps-link {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
      background: #eff6ff;
      padding: 4px 8px;
      border-radius: 6px;
      border: 1px solid #bfdbfe;
    }
    .gmaps-link:hover { text-decoration: underline; background: #dbeafe; }

    /* Verification Actions */
    .verification-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .action-btn {
      padding: 13px;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      border: 2px solid transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn-right {
      background: #16a34a;
      color: #fff;
    }
    .btn-right:hover {
      background: #15803d;
      box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }
    .btn-wrong {
      background: #dc2626;
      color: #fff;
    }
    .btn-wrong:hover {
      background: #b91c1c;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    /* Wrong Correction Form Box */
    .wrong-correction-card {
      background: #fff5f5;
      border: 1.5px solid #fecaca;
      border-radius: 10px;
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .correction-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: #991b1b;
      font-size: 0.88rem;
      font-weight: 700;
    }
    .coords-input-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .input-group {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .input-group label {
      font-size: 0.72rem;
      font-weight: 700;
      color: #991b1b;
      text-transform: uppercase;
    }
    .coord-input {
      padding: 8px 10px;
      border: 1px solid #fca5a5;
      border-radius: 6px;
      font-family: monospace;
      font-size: 0.88rem;
      font-weight: 600;
      color: #0f172a;
      background: #fff;
    }
    .coord-input:focus {
      outline: none;
      border-color: #dc2626;
      box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15);
    }
    .btn-pick-map {
      background: #2563eb;
      border: none;
      color: #fff;
      border-radius: 6px;
      padding: 9px 12px;
      font-size: 0.88rem;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
      box-shadow: 0 2px 6px rgba(37,99,235,0.3);
    }
    .btn-pick-map:hover { background: #1d4ed8; }
    .btn-pick-map.picking-active {
      background: #dc2626;
      box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.3);
    }

    .note-input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #fca5a5;
      border-radius: 6px;
      font-size: 0.85rem;
      font-family: inherit;
      background: #fff;
    }
    .note-input:focus {
      outline: none;
      border-color: #dc2626;
    }
    .correction-buttons {
      display: flex;
      gap: 8px;
    }
    .btn-save-correction {
      flex: 2;
      background: #dc2626;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 10px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-save-correction:hover { background: #b91c1c; }
    .btn-skip-correction {
      flex: 1;
      background: #fff;
      border: 1px solid #cbd5e1;
      color: #475569;
      border-radius: 6px;
      padding: 10px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-skip-correction:hover { background: #f1f5f9; }

    /* Bottom quick jump bar */
    .quick-jump-container {
      padding: 14px 18px;
      border-top: 1px solid var(--border);
      background: #f8fafc;
    }
    .quick-jump-header {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-muted);
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
    }
    .chips-grid {
      display: grid;
      grid-template-columns: repeat(10, 1fr);
      gap: 4px;
      max-height: 110px;
      overflow-y: auto;
      padding-right: 4px;
    }
    .chip {
      background: #e2e8f0;
      color: #475569;
      font-size: 0.75rem;
      font-weight: 700;
      border: none;
      border-radius: 4px;
      padding: 5px 0;
      cursor: pointer;
      text-align: center;
      transition: all 0.15s;
    }
    .chip:hover { filter: brightness(0.9); }
    .chip.active { outline: 2px solid #0f172a; outline-offset: 1px; }
    .chip.status-right { background: #86efac; color: #14532d; }
    .chip.status-wrong { background: #fca5a5; color: #7f1d1d; }
    .chip.status-corrected { background: #93c5fd; color: #1e3a8a; }

    /* Toast Notification */
    .toast-msg {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #0f172a;
      color: #fff;
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      z-index: 2000;
      display: none;
      font-size: 0.9rem;
      font-weight: 600;
      border-left: 4px solid #10b981;
    }

    @media (max-width: 900px) {
      .main-container { flex-direction: column; }
      .map-wrapper { height: 45vh; }
      .sidebar { width: 100%; height: 55vh; border-left: none; border-top: 1px solid var(--border); }
    }
  </style>
</head>
<body>

  <header>
    <div class="header-title">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      <span>ECD Centers Map Audit</span>
      <span class="header-badge">Live Verifier</span>
    </div>

    <div class="scoreboard">
      <div class="score-item score-total">Total: <strong id="score-total">0</strong></div>
      <div class="score-item score-right">✅ Right: <strong id="score-right">0</strong></div>
      <div class="score-item score-wrong">❌ Wrong: <strong id="score-wrong">0</strong></div>
      <div class="score-item score-corrected">📍 Corrected: <strong id="score-corrected">0</strong></div>
      <div class="score-item score-pending">⏳ Remaining: <strong id="score-pending">0</strong></div>
    </div>

    <div class="header-actions">
      <button type="button" class="btn-action-header btn-apply-geojson" id="btn-apply-geojson" title="Update the live website map file directly with all verified coordinates">
        ⚡ Apply to Website Map
      </button>
      <a href="verify-centers?action=export" class="btn-action-header btn-export" title="Download verified GeoJSON file">
        📥 Export GeoJSON
      </a>
    </div>
  </header>

  <div class="main-container">
    <div class="map-wrapper">
      <div id="map"></div>

      <!-- Interactive Map Picker HUD -->
      <div id="map-picker-hud" class="map-picker-hud" style="display: none;">
        <div class="hud-pill">
          <div class="hud-status">
            <span class="hud-indicator"></span>
            <span>📍 Location Picker Active</span>
          </div>
          <div class="hud-coords">
            <span id="hud-lat">0.000000</span>, <span id="hud-lon">0.000000</span>
            <span id="hud-dist" class="hud-dist"></span>
          </div>
          <div class="hud-actions">
            <button type="button" class="hud-btn hud-btn-save" id="hud-save-btn">💾 Save Location</button>
            <button type="button" class="hud-btn hud-btn-cancel" id="hud-cancel-btn">✕ Done</button>
          </div>
        </div>
      </div>
    </div>

    <div class="sidebar">
      <div class="card-nav">
        <button class="nav-btn" id="btn-prev" title="Previous center (Left Arrow)">⬅️ Prev</button>
        <span class="stepper-label" id="stepper-label">Center 1 of 94</span>
        <button class="nav-btn" id="btn-next" title="Next center (Right Arrow)">Next ➡️</button>
      </div>

      <div class="card-body">
        <div id="status-banner" class="center-status-banner status-pending">
          <span>⏳ Status: <strong>Not yet verified</strong></span>
        </div>

        <div id="anomaly-container"></div>

        <div class="detail-card">
          <div class="detail-row">
            <label>Candidate / Teacher</label>
            <div class="value" id="disp-name">Loading...</div>
          </div>

          <div class="detail-row">
            <label>Village / Location</label>
            <div class="value" id="disp-village">Loading...</div>
          </div>

          <div class="detail-row">
            <label>Center Model</label>
            <div><span class="model-badge" id="disp-model">—</span></div>
          </div>

          <div class="detail-row">
            <label>Sponsored By</label>
            <div class="value" id="disp-sponsor" style="font-size: 0.88rem;">—</div>
          </div>

          <div class="detail-row">
            <label>Current Pin Coordinates</label>
            <div class="coords-row">
              <span class="coords-text" id="disp-coords">0.000000, 0.000000</span>
              <a href="#" target="_blank" rel="noopener noreferrer" class="gmaps-link" id="disp-gmaps">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Google Satellite
              </a>
            </div>
          </div>
        </div>

        <div class="verification-actions">
          <button class="action-btn btn-right" id="action-right" title="Mark Correct (Key 1 or R)">
            ✅ RIGHT
          </button>
          <button class="action-btn btn-wrong" id="action-wrong" title="Mark Incorrect / Pick New Location (Key 2 or W)">
            ❌ WRONG (Pick New Pin)
          </button>
        </div>

        <!-- Wrong Correction Panel -->
        <div class="wrong-correction-card" id="wrong-correction-panel" style="display: none;">
          <div class="correction-header">
            <span>📍 Specify New Right Coordinates</span>
            <span style="font-size: 0.75rem; cursor: pointer; color: #64748b;" id="close-wrong-panel">✕ Close</span>
          </div>

          <button type="button" class="btn-pick-map" id="btn-pick-map">
            📍 Activate Location Picker on Map
          </button>

          <div class="coords-input-grid">
            <div class="input-group">
              <label for="input-new-lat">New Latitude</label>
              <input type="text" class="coord-input" id="input-new-lat" placeholder="e.g. 35.838845">
            </div>
            <div class="input-group">
              <label for="input-new-lon">New Longitude</label>
              <input type="text" class="coord-input" id="input-new-lon" placeholder="e.g. 71.791154">
            </div>
          </div>

          <div class="input-group">
            <label for="input-note">Reason / Notes for Correction</label>
            <input type="text" class="note-input" id="input-note" placeholder="e.g. Village is 4km north; wrong coordinates in CSV">
          </div>

          <div class="correction-buttons">
            <button type="button" class="btn-save-correction" id="btn-save-correction">
              💾 Save New Coordinates & Next
            </button>
            <button type="button" class="btn-skip-correction" id="btn-skip-correction">
              Mark Wrong (No Coords) & Next
            </button>
          </div>
        </div>
      </div>

      <div class="quick-jump-container">
        <div class="quick-jump-header">
          <span>Quick Jump (1 to 94)</span>
          <span style="font-weight: 400;">1: Right | 2: Wrong</span>
        </div>
        <div class="chips-grid" id="chips-grid"></div>
      </div>
    </div>
  </div>

  <div id="toast-msg" class="toast-msg"></div>

  <!-- Scripts loaded as external files to comply with Content-Security-Policy -->
  <script src="assets/leaflet/leaflet.js"></script>
  <script src="js/verifier.js"></script>
</body>
</html>
