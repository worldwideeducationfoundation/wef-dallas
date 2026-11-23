/**
 * Worldwide Education Foundation — Center Location Verifier JS
 * External script compliant with strict Content-Security-Policy (no inline scripts)
 */

(function () {
  'use strict';

  const categoryColors = {
    'Entrepreneurial Model': { fill: '#0e611d', accent: '#34d058', bg: '#dcfce7', text: '#14532d' },
    'Government School':     { fill: '#1a56db', accent: '#60a5fa', bg: '#dbeafe', text: '#1e40af' },
    'Private School':        { fill: '#9333ea', accent: '#c084fc', bg: '#f3e8ff', text: '#6b21a8' },
    'Semi Govt School':      { fill: '#dc6803', accent: '#fbbf24', bg: '#fef3c7', text: '#92400e' }
  };

  const knownAnomalies = {
    6: "⚠️ Duplicate Coordinates: Exact same coordinates as Center #8 (Nahid Wasim in Kari thing)!",
    8: "⚠️ Duplicate Coordinates: Exact same coordinates as Center #6 (Samina Naz in Singoor Shahmiran dah)!",
    26: "⚠️ Duplicate Coordinates: Exact same coordinates as Center #31 (Shahina in Bohtogol Gol)!",
    28: "🚨 Critical: Coordinates (35.57553, 71.32486) fall OUTSIDE Chitral border into Afghanistan! Likely typo for 71.82486.",
    31: "⚠️ Duplicate Coordinates: Exact same coordinates as Center #26 (Shaheena in Pitagram KA)!",
    75: "⚠️ Outlier Check: Longitude 72.541809 is far east for Warijune (compare with #74 at 72.218428).",
    78: "⚠️ Outlier Check: Coordinates (36.493032, 72.919322) are in high-altitude glaciers, ~60km east of Ovir valley!",
    82: "⚠️ Placeholder Name: Candidate name is literally 'Name' at GPS Jughoor."
  };

  let centers = [];
  let auditState = {};
  let currentIndex = 0;
  let map = null;
  let markersLayer = null;
  let markersMap = {};
  let pickerMarker = null;
  let distanceLine = null;
  let isPickingOnMap = false;

  function createMarkerIcon(category, isActive) {
    const col = categoryColors[category] || { fill: '#64748b', accent: '#94a3b8' };
    const size = isActive ? [36, 44] : [26, 32];
    const anchor = isActive ? [18, 42] : [13, 30];
    const borderCol = isActive ? '#fbbf24' : '#ffffff';
    const borderWidth = isActive ? '3' : '1.5';

    return L.divIcon({
      className: 'custom-pin',
      html: `
        <div style="${isActive ? 'filter: drop-shadow(0 0 8px #fbbf24); transform: scale(1.15);' : ''}">
          <svg width="${size[0]}" height="${size[1]}" viewBox="0 0 28 34" fill="none">
            <ellipse cx="14" cy="31" rx="6" ry="2" fill="rgba(0,0,0,0.3)" />
            <path d="M14 0C6.268 0 0 6.268 0 14C0 24.5 14 32 14 32C14 32 28 24.5 28 14C28 6.268 21.732 0 14 0Z" fill="${col.fill}" />
            <path d="M14 1C6.82 1 1 6.82 1 14C1 23.75 14 30.85 14 30.85C14 30.85 27 23.75 27 14C27 6.82 21.18 1 14 1Z" fill="none" stroke="${borderCol}" stroke-width="${borderWidth}" />
            <circle cx="14" cy="13" r="5" fill="#ffffff" />
            <circle cx="14" cy="13" r="2.8" fill="${col.accent}" />
          </svg>
        </div>
      `,
      iconSize: size,
      iconAnchor: anchor,
      popupAnchor: [0, -anchor[1]]
    });
  }

  function initMap() {
    if (typeof L === 'undefined') {
      setTimeout(initMap, 150);
      return;
    }
    if (map) return;

    const hybridMap = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 20, attribution: 'Google' });
    const esriMap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' });
    const cartoMap = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { maxZoom: 20, attribution: 'CARTO' });

    map = L.map('map', {
      layers: [hybridMap],
      zoomControl: true
    }).setView([35.85, 71.8], 10);

    L.control.layers({
      "Google Satellite": hybridMap,
      "Esri Satellite": esriMap,
      "Street Map": cartoMap
    }, null, { position: 'topleft' }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    // Render center markers
    centers.forEach((f, idx) => {
      const [lon, lat] = f.geometry.coordinates;
      const marker = L.marker([lat, lon], {
        icon: createMarkerIcon(f.properties.centerModel, false)
      }).addTo(markersLayer);

      marker.on('click', () => jumpToCenter(idx));
      markersMap[idx] = marker;
    });

    // Handle map click for picking new coordinates
    map.on('click', (e) => {
      if (!isPickingOnMap) return;
      setPickedCoordinates(e.latlng.lat, e.latlng.lng);
    });

    setTimeout(() => { map.invalidateSize(); }, 200);

    // Fetch Chitral border in background
    fetch('assets/data/chitral_border.geojson')
      .then(r => r.json())
      .then(borderData => {
        if (map) {
          L.geoJSON(borderData, {
            style: {
              color: '#fbbf24',
              weight: 2,
              opacity: 0.85,
              fillColor: '#fbbf24',
              fillOpacity: 0.03,
              dashArray: '6, 6',
              interactive: false
            }
          }).addTo(map);
        }
      })
      .catch(() => {});

    updateMapPosition();
  }

  function activateLocationPicker() {
    isPickingOnMap = true;
    const center = centers[currentIndex];
    const [origLon, origLat] = center.geometry.coordinates;

    const inputLat = document.getElementById('input-new-lat');
    const inputLon = document.getElementById('input-new-lon');

    // Use current input or center coords
    const initialLat = inputLat && inputLat.value ? parseFloat(inputLat.value) : origLat;
    const initialLon = inputLon && inputLon.value ? parseFloat(inputLon.value) : origLon;

    setPickedCoordinates(initialLat, initialLon);

    // Show HUD
    const hud = document.getElementById('map-picker-hud');
    if (hud) hud.style.display = 'block';

    const pickBtn = document.getElementById('btn-pick-map');
    if (pickBtn) {
      pickBtn.classList.add('picking-active');
      pickBtn.textContent = '🎯 Click anywhere on map to reposition';
    }

    if (map) {
      map.getContainer().style.cursor = 'crosshair';
      map.panTo([initialLat, initialLon]);
    }
  }

  function deactivateLocationPicker() {
    isPickingOnMap = false;
    const hud = document.getElementById('map-picker-hud');
    if (hud) hud.style.display = 'none';

    const pickBtn = document.getElementById('btn-pick-map');
    if (pickBtn) {
      pickBtn.classList.remove('picking-active');
      pickBtn.textContent = '📍 Activate Location Picker on Map';
    }

    if (map) map.getContainer().style.cursor = '';
  }

  function setPickedCoordinates(lat, lon) {
    const center = centers[currentIndex];
    const [origLon, origLat] = center.geometry.coordinates;

    const inputLat = document.getElementById('input-new-lat');
    const inputLon = document.getElementById('input-new-lon');
    if (inputLat) inputLat.value = lat.toFixed(6);
    if (inputLon) inputLon.value = lon.toFixed(6);

    const hudLat = document.getElementById('hud-lat');
    const hudLon = document.getElementById('hud-lon');
    const hudDist = document.getElementById('hud-dist');
    if (hudLat) hudLat.textContent = lat.toFixed(6);
    if (hudLon) hudLon.textContent = lon.toFixed(6);

    // Compute distance from original
    const pOrig = L.latLng(origLat, origLon);
    const pNew = L.latLng(lat, lon);
    const distMeters = pOrig.distanceTo(pNew);
    const distText = distMeters < 1000
      ? `(Moved ${Math.round(distMeters)} m)`
      : `(Moved ${(distMeters / 1000).toFixed(2)} km)`;
    if (hudDist) hudDist.textContent = distText;

    // Update or create draggable marker
    if (pickerMarker) {
      pickerMarker.setLatLng([lat, lon]);
    } else if (map) {
      pickerMarker = L.marker([lat, lon], {
        draggable: true,
        icon: L.divIcon({
          className: 'picker-pin',
          html: '<div style="background:#2563eb;color:#fff;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:20px;border:3px solid #fff;box-shadow:0 0 14px rgba(37,99,235,0.95);cursor:move;">📍</div>',
          iconSize: [38, 38],
          iconAnchor: [19, 19]
        })
      }).addTo(map);

      pickerMarker.bindTooltip("Drag me or click map", { permanent: true, direction: "top", offset: [0, -18] }).openTooltip();

      pickerMarker.on('drag', (evt) => {
        const pos = evt.target.getLatLng();
        setPickedCoordinates(pos.lat, pos.lng);
      });
    }

    // Draw connecting line from original pin to new pin
    if (distanceLine) {
      distanceLine.setLatLngs([pOrig, pNew]);
    } else if (map) {
      distanceLine = L.polyline([pOrig, pNew], {
        color: '#38bdf8',
        weight: 3,
        dashArray: '6, 8',
        opacity: 0.9
      }).addTo(map);
    }
  }

  function updateMapPosition() {
    if (!map || !centers[currentIndex]) return;
    const center = centers[currentIndex];
    const sno = center.properties.sno || (currentIndex + 1);
    const props = center.properties;
    const [lon, lat] = center.geometry.coordinates;

    // Remove existing picker marker and distance line when switching center
    if (pickerMarker) {
      map.removeLayer(pickerMarker);
      pickerMarker = null;
    }
    if (distanceLine) {
      map.removeLayer(distanceLine);
      distanceLine = null;
    }
    deactivateLocationPicker();

    Object.keys(markersMap).forEach(i => {
      const cModel = centers[i].properties.centerModel;
      markersMap[i].setIcon(createMarkerIcon(cModel, parseInt(i, 10) === currentIndex));
    });

    map.setView([lat, lon], 17, { animate: true, duration: 0.6 });

    if (markersMap[currentIndex]) {
      markersMap[currentIndex].bindPopup(`
        <div style="font-family: Inter, sans-serif; font-size: 13px; line-height: 1.4;">
          <strong style="font-size: 14px; color: #0f172a;">#${sno}: ${props.name}</strong><br>
          <span style="color: #475569;">${props.village}</span><br>
          <strong style="color: #0284c7;">${props.centerModel}</strong><br>
          <small style="color: #64748b;">${props.sponsor}</small>
        </div>
      `).openPopup();
    }

    // If center already has saved corrected coords, show them
    if (auditState[sno] && auditState[sno].corrected_lat && auditState[sno].corrected_lon) {
      const cLat = auditState[sno].corrected_lat;
      const cLon = auditState[sno].corrected_lon;
      setPickedCoordinates(cLat, cLon);
    }
  }

  function buildChips() {
    const container = document.getElementById('chips-grid');
    if (!container) return;
    container.innerHTML = '';
    centers.forEach((c, idx) => {
      const sno = c.properties.sno || (idx + 1);
      const btn = document.createElement('button');
      btn.className = 'chip';
      btn.id = 'chip-' + idx;
      btn.textContent = sno;
      btn.onclick = () => jumpToCenter(idx);
      if (auditState[sno]) {
        if (auditState[sno].verdict === 'right') {
          btn.classList.add('status-right');
        } else if (auditState[sno].corrected_lat && auditState[sno].corrected_lon) {
          btn.classList.add('status-corrected');
        } else {
          btn.classList.add('status-wrong');
        }
      }
      container.appendChild(btn);
    });
  }

  function showCenter(idx) {
    if (idx < 0 || idx >= centers.length) return;
    currentIndex = idx;
    const center = centers[idx];
    const props = center.properties;
    const sno = props.sno || (idx + 1);
    const [lon, lat] = center.geometry.coordinates;

    document.getElementById('stepper-label').textContent = `Center ${sno} of ${centers.length}`;
    document.getElementById('btn-prev').disabled = idx === 0;
    document.getElementById('btn-next').disabled = idx === centers.length - 1;

    document.getElementById('disp-name').textContent = props.name || 'N/A';
    document.getElementById('disp-village').textContent = props.village || 'N/A';
    document.getElementById('disp-sponsor').textContent = props.sponsor || 'N/A';
    document.getElementById('disp-coords').textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
    document.getElementById('disp-gmaps').href = `https://www.google.com/maps?q=${lat},${lon}&t=k`;

    const modelEl = document.getElementById('disp-model');
    modelEl.textContent = props.centerModel;
    const col = categoryColors[props.centerModel] || { bg: '#e2e8f0', text: '#334155' };
    modelEl.style.backgroundColor = col.bg;
    modelEl.style.color = col.text;

    const anomalyBox = document.getElementById('anomaly-container');
    if (knownAnomalies[sno]) {
      anomalyBox.innerHTML = `<div class="anomaly-alert">${knownAnomalies[sno]}</div>`;
    } else {
      anomalyBox.innerHTML = '';
    }

    const wrongPanel = document.getElementById('wrong-correction-panel');
    const inputLat = document.getElementById('input-new-lat');
    const inputLon = document.getElementById('input-new-lon');
    const inputNote = document.getElementById('input-note');

    const statusBanner = document.getElementById('status-banner');
    if (auditState[sno]) {
      if (auditState[sno].verdict === 'right') {
        statusBanner.className = 'center-status-banner status-right';
        statusBanner.innerHTML = '<span>✅ Verified: <strong>Marked RIGHT (Correct)</strong></span>';
        wrongPanel.style.display = 'none';
      } else {
        statusBanner.className = 'center-status-banner status-wrong';
        let statusText = '<span>❌ Verified: <strong>Marked WRONG</strong>';
        if (auditState[sno].corrected_lat && auditState[sno].corrected_lon) {
          statusText += ` — 📍 New: <strong>${auditState[sno].corrected_lat}, ${auditState[sno].corrected_lon}</strong>`;
        }
        if (auditState[sno].note) {
          statusText += ` (${auditState[sno].note})`;
        }
        statusText += '</span>';
        statusBanner.innerHTML = statusText;

        inputLat.value = auditState[sno].corrected_lat || '';
        inputLon.value = auditState[sno].corrected_lon || '';
        inputNote.value = auditState[sno].note || '';
        wrongPanel.style.display = 'flex';
      }
    } else {
      statusBanner.className = 'center-status-banner status-pending';
      statusBanner.innerHTML = '<span>⏳ Status: <strong>Not yet verified</strong></span>';
      wrongPanel.style.display = 'none';
      inputLat.value = '';
      inputLon.value = '';
      inputNote.value = '';
    }

    document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById('chip-' + idx);
    if (activeChip) {
      activeChip.classList.add('active');
      activeChip.scrollIntoView({ block: 'nearest' });
    }

    updateMapPosition();
  }

  function jumpToCenter(idx) {
    showCenter(idx);
  }

  function hideWrongPanel() {
    document.getElementById('wrong-correction-panel').style.display = 'none';
    deactivateLocationPicker();
    if (pickerMarker && map) {
      map.removeLayer(pickerMarker);
      pickerMarker = null;
    }
    if (distanceLine && map) {
      map.removeLayer(distanceLine);
      distanceLine = null;
    }
  }

  function recordRight() {
    const center = centers[currentIndex];
    const sno = center.properties.sno || (currentIndex + 1);

    auditState[sno] = {
      sno: sno,
      name: center.properties.name,
      village: center.properties.village,
      model: center.properties.centerModel,
      sponsor: center.properties.sponsor,
      lat: center.geometry.coordinates[1],
      lon: center.geometry.coordinates[0],
      verdict: 'right',
      corrected_lat: null,
      corrected_lon: null,
      note: '',
      timestamp: new Date().toISOString()
    };

    saveAndAdvance(sno, 'status-right');
  }

  function openWrongPanel() {
    const wrongPanel = document.getElementById('wrong-correction-panel');
    wrongPanel.style.display = 'flex';

    // Immediately activate the interactive on-map location picker!
    activateLocationPicker();
  }

  function recordWrong(includeCoordinates) {
    const center = centers[currentIndex];
    const sno = center.properties.sno || (currentIndex + 1);
    const inputLat = document.getElementById('input-new-lat');
    const inputLon = document.getElementById('input-new-lon');
    const inputNote = document.getElementById('input-note');

    const correctedLat = includeCoordinates && inputLat.value.trim() !== '' ? parseFloat(inputLat.value.trim()) : null;
    const correctedLon = includeCoordinates && inputLon.value.trim() !== '' ? parseFloat(inputLon.value.trim()) : null;

    auditState[sno] = {
      sno: sno,
      name: center.properties.name,
      village: center.properties.village,
      model: center.properties.centerModel,
      sponsor: center.properties.sponsor,
      lat: center.geometry.coordinates[1],
      lon: center.geometry.coordinates[0],
      verdict: 'wrong',
      corrected_lat: correctedLat,
      corrected_lon: correctedLon,
      note: inputNote.value.trim(),
      timestamp: new Date().toISOString()
    };

    const chipClass = (correctedLat && correctedLon) ? 'status-corrected' : 'status-wrong';
    saveAndAdvance(sno, chipClass);
  }

  function saveAndAdvance(sno, chipClass) {
    const chip = document.getElementById('chip-' + currentIndex);
    if (chip) {
      chip.classList.remove('status-right', 'status-wrong', 'status-corrected');
      chip.classList.add(chipClass);
    }

    fetch('verify-centers?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(auditState)
    }).catch(e => console.error('Save failed', e));

    updateScoreboard();
    showCenter(currentIndex);

    if (currentIndex < centers.length - 1) {
      setTimeout(() => {
        showCenter(currentIndex + 1);
      }, 350);
    }
  }

  function updateScoreboard() {
    let right = 0, wrong = 0, corrected = 0;
    Object.values(auditState).forEach(item => {
      if (item.verdict === 'right') right++;
      if (item.verdict === 'wrong') {
        wrong++;
        if (item.corrected_lat && item.corrected_lon) corrected++;
      }
    });
    const total = centers.length;
    const pending = total - (right + wrong);

    document.getElementById('score-total').textContent = total;
    document.getElementById('score-right').textContent = right;
    document.getElementById('score-wrong').textContent = wrong;
    document.getElementById('score-corrected').textContent = corrected;
    document.getElementById('score-pending').textContent = pending;
  }

  function showToast(msg) {
    const toast = document.getElementById('toast-msg');
    if (!toast) return;
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
  }

  function applyUpdatesToLiveGeoJSON() {
    if (!confirm('This will update the website’s live map data (locations_map1.geojson) with all corrected coordinates. A backup will be created automatically. Proceed?')) {
      return;
    }

    fetch('verify-centers?action=apply_to_geojson', {
      method: 'POST'
    })
      .then(r => r.json())
      .then(res => {
        if (res.status === 'success') {
          showToast(`⚡ ${res.message}`);
        } else {
          alert('Failed to update: ' + res.message);
        }
      })
      .catch(err => {
        alert('Request failed: ' + err.message);
      });
  }

  function start() {
    Promise.all([
      fetch('assets/data/locations_map1.geojson').then(r => r.json()),
      fetch('verify-centers?action=get_state').then(r => r.json()).catch(() => ({}))
    ]).then(([geojson, savedState]) => {
      centers = geojson.features || [];
      auditState = savedState || {};

      document.getElementById('score-total').textContent = centers.length;
      buildChips();
      updateScoreboard();
      showCenter(0);
      initMap();
    }).catch(err => {
      console.error('Failed to load centers data:', err);
      document.getElementById('disp-name').textContent = 'Error loading data';
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btn-prev').addEventListener('click', () => showCenter(currentIndex - 1));
    document.getElementById('btn-next').addEventListener('click', () => showCenter(currentIndex + 1));
    document.getElementById('action-right').addEventListener('click', () => recordRight());
    document.getElementById('action-wrong').addEventListener('click', () => openWrongPanel());
    document.getElementById('btn-save-correction').addEventListener('click', () => recordWrong(true));
    document.getElementById('btn-skip-correction').addEventListener('click', () => recordWrong(false));
    document.getElementById('close-wrong-panel').addEventListener('click', () => hideWrongPanel());

    document.getElementById('btn-pick-map').addEventListener('click', () => {
      if (isPickingOnMap) {
        deactivateLocationPicker();
      } else {
        activateLocationPicker();
      }
    });

    // Map HUD action buttons
    document.getElementById('hud-save-btn').addEventListener('click', () => recordWrong(true));
    document.getElementById('hud-cancel-btn').addEventListener('click', () => deactivateLocationPicker());

    // Apply to live website GeoJSON
    const btnApply = document.getElementById('btn-apply-geojson');
    if (btnApply) {
      btnApply.addEventListener('click', () => applyUpdatesToLiveGeoJSON());
    }

    // Sync input coordinates when user manually types into inputs
    const inputLat = document.getElementById('input-new-lat');
    const inputLon = document.getElementById('input-new-lon');
    function onManualCoordChange() {
      const lat = parseFloat(inputLat.value);
      const lon = parseFloat(inputLon.value);
      if (!isNaN(lat) && !isNaN(lon) && map) {
        setPickedCoordinates(lat, lon);
        map.panTo([lat, lon]);
      }
    }
    inputLat.addEventListener('input', onManualCoordChange);
    inputLon.addEventListener('input', onManualCoordChange);

    window.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'INPUT') {
        if (e.key === 'Enter') recordWrong(true);
        return;
      }
      if (e.key === 'ArrowRight') showCenter(currentIndex + 1);
      if (e.key === 'ArrowLeft') showCenter(currentIndex - 1);
      if (e.key === '1' || e.key.toLowerCase() === 'r') recordRight();
      if (e.key === '2' || e.key.toLowerCase() === 'w') openWrongPanel();
    });

    start();
  });

})();
