/* ============================================
   Worldwide Education Foundation — Map JS
   Leaflet.js Integration for ECD Centers Map
   Updated: Uses MAP-1 data with category filters
   ============================================ */

/* ============================================
   Lazy Leaflet loading

   Leaflet used to be two blocking tags in the page: a stylesheet in <head>
   from unpkg.com and a 43 KB script before </body>. Both loaded on every
   visit to the homepage, whether or not the visitor
   ever scrolled as far as the map — and the stylesheet, being in <head> and
   on a third-party origin, held up first paint behind a DNS lookup, a TCP
   connection and a TLS handshake to a host the page otherwise never touches.

   The map sits well below the fold on both pages. So the library is fetched
   when the visitor is within 400px of it and not before: no map in view, no
   request, no third-party connection, ~57 KB not downloaded.

   rootMargin rather than a zero threshold, so loading starts while the map is
   still approaching and the tiles are in place by the time it is on screen.
   ============================================ */

const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const LEAFLET_CSS_SRI = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
const LEAFLET_JS_SRI = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';

document.addEventListener('DOMContentLoaded', () => {
  const mapElement = document.getElementById('map');
  if (!mapElement) return;

  // No IntersectionObserver (very old browser): load straight away rather than
  // leaving an empty box on the page forever.
  if (!('IntersectionObserver' in window)) {
    loadLeaflet().then(initECDMap);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    if (!entries.some((entry) => entry.isIntersecting)) return;

    observer.disconnect();
    loadLeaflet().then(initECDMap).catch(() => {
      mapElement.innerHTML =
        '<p style="padding:2rem;text-align:center;color:#5a6069">' +
        'The map could not be loaded. Please check your connection and refresh.</p>';
    });
  }, { rootMargin: '400px 0px' });

  observer.observe(mapElement);
});

/**
 * Injects the Leaflet stylesheet and script once, and resolves when the
 * library is ready. The integrity hashes are the same ones the markup used to
 * carry, so a tampered CDN response is still rejected by the browser.
 */
let leafletPromise = null;

function loadLeaflet() {
  if (leafletPromise) return leafletPromise;

  leafletPromise = new Promise((resolve, reject) => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = LEAFLET_CSS;
    link.integrity = LEAFLET_CSS_SRI;
    link.crossOrigin = '';
    document.head.appendChild(link);

    const script = document.createElement('script');
    script.src = LEAFLET_JS;
    script.integrity = LEAFLET_JS_SRI;
    script.crossOrigin = '';
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Leaflet failed to load'));
    document.head.appendChild(script);
  });

  return leafletPromise;
}

function initECDMap() {
  const mapElement = document.getElementById('map');
  if (!mapElement) return;

  /* ──────────────────────────────────────────────
     1. Category Color Palette
     ────────────────────────────────────────────── */
  const categoryColors = {
    'Entrepreneurial Model': { fill: '#0e611d', accent: '#34d058', label: 'Entrepreneurial Models' },
    'Government School':     { fill: '#1a56db', accent: '#60a5fa', label: 'Government Schools' },
    'Private School':        { fill: '#9333ea', accent: '#c084fc', label: 'Private Schools' },
    'Semi Govt School':      { fill: '#dc6803', accent: '#fbbf24', label: 'Semi Govt Schools' }
  };

  /* ──────────────────────────────────────────────
     2. Create colored marker icon for each category
     ────────────────────────────────────────────── */
  function createMarkerIcon(category) {
    const colors = categoryColors[category] || { fill: '#6b7280', accent: '#d1d5db', label: 'Other Center' };
    return L.divIcon({
      className: 'custom-marker-container',
      html: `
        <div class="marker-pin" data-category="${category}">
          <svg width="28" height="34" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="14" cy="31" rx="6" ry="2" fill="rgba(0,0,0,0.2)" />
            <path d="M14 0C6.268 0 0 6.268 0 14C0 24.5 14 32 14 32C14 32 28 24.5 28 14C28 6.268 21.732 0 14 0Z" fill="${colors.fill}" />
            <path d="M14 1C6.82 1 1 6.82 1 14C1 23.75 14 30.85 14 30.85C14 30.85 27 23.75 27 14C27 6.82 21.18 1 14 1Z" fill="none" stroke="#ffffff" stroke-width="1.5" />
            <circle cx="14" cy="13" r="5.5" fill="#ffffff" />
            <circle cx="14" cy="13" r="3" fill="${colors.accent}" />
          </svg>
        </div>
      `,
      iconSize: [28, 34],
      iconAnchor: [14, 32],
      popupAnchor: [0, -32]
    });
  }

  /* ──────────────────────────────────────────────
     3. Base Tile Layers
     ────────────────────────────────────────────── */
  const streetMap = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 20,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener noreferrer">CARTO</a>'
  });

  const satelliteMap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 19,
    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
  });

  const hybridMap = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    attribution: 'Map data &copy; Google'
  });

  const terrainMap = L.tileLayer('https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
    maxZoom: 20,
    attribution: 'Map data &copy; Google'
  });

  /* ──────────────────────────────────────────────
     4. Initialize Map
     ────────────────────────────────────────────── */
  const map = L.map('map', {
    zoomSnap: 0.5,
    zoomDelta: 0.5,
    scrollWheelZoom: false,
    layers: [hybridMap]
  }).setView([35.85, 71.8], 10);

  map.on('click', () => { map.scrollWheelZoom.enable(); });
  map.on('mouseout', () => { map.scrollWheelZoom.disable(); });

  // Layer Switcher
  const baseMaps = {
    "Satellite (Google Hybrid)": hybridMap,
    "Street Map": streetMap,
    "Satellite (Esri)": satelliteMap,
    "Terrain View": terrainMap
  };
  L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

  /* ──────────────────────────────────────────────
     5. "View All Centers" Reset Control
     ────────────────────────────────────────────── */
  const SeeAllControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function (map) {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
      container.style.backgroundColor = '#ffffff';
      container.style.padding = '8px 12px';
      container.style.cursor = 'pointer';
      container.style.fontWeight = '600';
      container.style.color = '#121417';
      container.style.fontFamily = 'Inter, sans-serif';
      container.style.boxShadow = '0 2px 8px rgba(18, 20, 23, 0.1)';
      // "View" drops on the narrowest phones (styles.css) so the control row fits.
      container.innerHTML = '<span class="map-see-all-verb">View </span>All Centers';
      container.title = 'Reset Map View';

      container.onmouseover = function() { container.style.backgroundColor = '#f4f5f6'; };
      container.onmouseout = function() { container.style.backgroundColor = '#ffffff'; };

      container.onclick = function (e) {
        L.DomEvent.stopPropagation(e);
        if (window.mapBounds) {
          map.fitBounds(window.mapBounds, { padding: [30, 30], animate: true, duration: 1.0 });
        }
      }
      return container;
    }
  });
  map.addControl(new SeeAllControl());

  /* ──────────────────────────────────────────────
     6. Category Layer Groups (for filtering)
     ────────────────────────────────────────────── */
  const categoryLayers = {};
  
  /* ──────────────────────────────────────────────
     6. State Management
     ────────────────────────────────────────────── */
  let activeSponsor = 'All';
  let activeCategory = 'All';
  let activeDistrict = 'All';
  const allMarkers = []; // Array to store { marker, category, sponsor, district, latlng }

  // Upper or Lower Chitral. The data has no district field of its own, but
  // every village name ends with one ("Dawashish Jughoor Lower Chitral"); a
  // "district" property, if the data ever gains one, takes precedence.
  function districtOf(props) {
    if (props.district) return String(props.district).trim();
    const match = /\b(upper|lower)\s+chitral\b/i.exec(props.village || '');
    if (!match) return 'Other';
    return match[1].toLowerCase() === 'upper' ? 'Upper Chitral' : 'Lower Chitral';
  }
  
  // Create a single layer group for all markers
  const mapMarkersGroup = L.layerGroup().addTo(map);

  /* ──────────────────────────────────────────────
     7. Popup Builder — shows all attributes except enrollment
     ────────────────────────────────────────────── */
  function buildPopupContent(props, latlng) {
    const colors = categoryColors[props.centerModel] || { fill: '#6b7280', label: 'Other Center' };

    return `
      <div class="eclc-popup-card">
        <div class="eclc-popup-topbar" style="background-color: ${colors.fill};"></div>
        <div class="eclc-popup-content">
          <div class="eclc-badge" style="color: ${colors.fill}; background: ${colors.fill}15;">
            ${colors.label}
          </div>
          <h3 class="eclc-title">ECLC Center</h3>
          
          <div class="eclc-details-grid">
            <div class="eclc-detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span><strong>Candidate:</strong> ${props.name || 'N/A'}</span>
            </div>
            <div class="eclc-detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span><strong>Village:</strong> ${props.village || 'Unknown Location'}</span>
            </div>
            <div class="eclc-detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
              <span><strong>Sponsored by:</strong> ${props.sponsor || 'N/A'}</span>
            </div>
            <div class="eclc-detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
              <span><strong>Coordinates:</strong> ${latlng.lat.toFixed(4)}°, ${latlng.lng.toFixed(4)}°</span>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  /* ──────────────────────────────────────────────
     8. Fetch Chitral Border
     ────────────────────────────────────────────── */
  const fetchBorder = fetch('assets/data/chitral_border.geojson')
    .then(response => {
      if (!response.ok) throw new Error('Border failed to load');
      return response.json();
    })
    .then(borderData => {
      const borderLayer = L.geoJSON(borderData, {
        style: {
          color: '#c4691c',
          // A solid, heavy outline (was a 2.5px dashed line that read as
          // faint and broken against the satellite and terrain layers).
          weight: 4,
          opacity: 1,
          fillColor: '#c4691c',
          fillOpacity: 0.04,
          interactive: false
        }
      }).addTo(map);
      return borderLayer;
    })
    .catch(error => {
      console.error('Error loading Chitral border:', error);
      return null;
    });

  /* ──────────────────────────────────────────────
     9. Fetch MAP-1 Location Data & Build Markers
     ────────────────────────────────────────────── */
  // admin -> Map Centers, served as GeoJSON (falls back to the static file).
  const fetchLocations = fetch('api/map-centers.php')
    .then(response => {
      if (!response.ok) throw new Error('Locations failed to load');
      return response.json();
    })
    .then(locationsData => {
      const uniqueSponsors = new Set();

      locationsData.features.forEach(feature => {
        const props = feature.properties;
        const [lon, lat] = feature.geometry.coordinates;
        const latlng = L.latLng(lat, lon);
        const category = props.centerModel || 'Other';
        const sponsor = props.sponsor ? props.sponsor.trim() : 'Unknown Sponsor';

        // Add to our set of unique sponsors
        uniqueSponsors.add(sponsor);

        const marker = L.marker(latlng, { icon: createMarkerIcon(category) });

        marker.bindPopup(buildPopupContent(props, latlng), {
          maxWidth: 340,
          minWidth: 260,
          className: 'custom-leaflet-popup'
        });

        marker.on('click', (e) => {
          const px = map.project(e.latlng, 16);
          px.y -= 120;
          const offsetLatLng = map.unproject(px, 16);
          map.setView(offsetLatLng, 16, { animate: true, duration: 0.8 });
        });

        // Store marker in our master array
        allMarkers.push({
          marker: marker,
          category: category,
          sponsor: sponsor,
          district: districtOf(props),
          latlng: latlng
        });
      });

      // Populate Sponsor Dropdown in both files
      // Not the district select, which shares the class for its styling.
      const sponsorSelects = document.querySelectorAll('.donor-select-input:not([data-district-select])');
      const sponsorList = Array.from(uniqueSponsors).sort();
      sponsorSelects.forEach(select => {
        sponsorList.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s;
          opt.textContent = s;
          select.appendChild(opt);
        });
      });

      /* Exposed for the Honor Board -> map link (initHonorBoardMapLink() in
         main.js): a sponsor tile's name comes from the sponsors table/CMS,
         which is a different data source than this map's own GeoJSON (a
         CSV export of ECLC center locations), maintained separately, so
         nothing guarantees the two spellings agree exactly. Matching is
         case/whitespace-normalized rather than exact so the far more common
         drift (casing, stray spaces) doesn't silently fail, plus one
         explicit, verified correction for a single known typo between the
         two sources ("Jumabhaqat" in the Honor Board's data vs
         "Jumabhagat" in the CSV - confirmed the same sponsor: it was the
         only name on either side with zero match on the other, both normalized
         and not). A genuinely different sponsor - one who funded a program
         other than these ECLC centers - is expected to resolve to null, not
         to get force-matched to something else; the caller shows a "no
         centers mapped for this sponsor" message in that case rather than
         guessing. */
      function normalizeSponsorName(raw) {
        return String(raw)
          .toLowerCase()
          .trim()
          .replace(/\s+/g, ' ')
          .replace('jumabhaqat', 'jumabhagat');
      }
      window.WEF_resolveMapSponsor = function (rawName) {
        const norm = normalizeSponsorName(rawName);
        return sponsorList.find((s) => normalizeSponsorName(s) === norm) || null;
      };

      // Apply filters initially to render the map
      applyFilters();

      return locationsData;
    })
    .catch(error => {
      console.error('Error loading locations:', error);
      const mapElement = document.getElementById('map');
      if (mapElement) {
        mapElement.innerHTML = `
          <div class="map-error-message">
            <p><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>Unable to load the interactive map data. Please check back later.</p>
          </div>
        `;
      }
      return null;
    });

  /* ──────────────────────────────────────────────
     10. Unified Filter Logic
     ────────────────────────────────────────────── */
  function applyFilters() {
    mapMarkersGroup.clearLayers();
    
    const currentCounts = {};
    Object.keys(categoryColors).forEach(cat => currentCounts[cat] = 0);
    
    let visibleCount = 0;
    const bounds = L.latLngBounds([]);

    allMarkers.forEach(item => {
      // Ignore unknown categories
      if (!categoryColors[item.category]) return;

      const matchCategory = activeCategory === 'All' || item.category === activeCategory;
      const matchSponsor = activeSponsor === 'All' || item.sponsor === activeSponsor;
      const matchDistrict = activeDistrict === 'All' || item.district === activeDistrict;

      if (matchCategory && matchSponsor && matchDistrict) {
        mapMarkersGroup.addLayer(item.marker);
        bounds.extend(item.latlng);
        visibleCount++;
        currentCounts[item.category]++;
      }
    });

    // Update counts in legend
    updateLegendCounts(currentCounts, visibleCount);

    // Update Category Button UI
    document.querySelectorAll('.legend-filter-btn:not(.legend-filter-all)').forEach(btn => {
      const cat = btn.getAttribute('data-filter-category');
      if (activeCategory === 'All' || activeCategory === cat) {
        btn.classList.remove('legend-filter-inactive');
      } else {
        btn.classList.add('legend-filter-inactive');
      }
    });

    const allBtn = document.getElementById('legend-filter-all');
    if (allBtn) {
      allBtn.classList.toggle('legend-filter-active-all', activeCategory === 'All');
    }

    // Fit Map Bounds to visible markers
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15, animate: true, duration: 1.0 });
    } else if (window.mapBounds) {
      map.fitBounds(window.mapBounds, { padding: [30, 30], animate: true, duration: 1.0 });
    }
  }

  function updateLegendCounts(counts, total) {
    Object.keys(counts).forEach(cat => {
      const countEl = document.getElementById(`legend-count-${cat.replace(/\s+/g, '-').toLowerCase()}`);
      if (countEl) {
        countEl.textContent = counts[cat];
      }
    });

    const totalEl = document.getElementById('legend-total-count');
    if (totalEl) totalEl.textContent = total;
  }

  /* ──────────────────────────────────────────────
     11. Filter Interactions
     ────────────────────────────────────────────── */
  window.toggleMapCategory = function(category) {
    if (activeCategory === category) {
      activeCategory = 'All'; // toggle off if clicked again
    } else {
      activeCategory = category;
    }
    applyFilters();
  };

  window.toggleAllCategories = function() {
    activeCategory = 'All';
    applyFilters();
  };

  window.toggleSponsor = function(sponsor) {
    activeSponsor = sponsor;
    applyFilters();
  };

  window.toggleDistrict = function(district) {
    activeDistrict = district;
    applyFilters();
  };

  // The controls in index.php used to call these through onclick/onchange
  // attributes, which the Content-Security-Policy no longer runs.
  const districtSelect = document.getElementById('district-select');
  if (districtSelect) {
    districtSelect.addEventListener('change', () => window.toggleDistrict(districtSelect.value));
  }
  const donorSelect = document.getElementById('donor-select');
  if (donorSelect) {
    donorSelect.addEventListener('change', () => window.toggleSponsor(donorSelect.value));
  }
  const categorySelect = document.getElementById('category-select');
  if (categorySelect) {
    categorySelect.addEventListener('change', () => {
      window.toggleAllCategories();
      if (categorySelect.value !== 'All') window.toggleMapCategory(categorySelect.value);
    });
  }
  const allCategoriesBtn = document.getElementById('legend-filter-all');
  if (allCategoriesBtn) {
    allCategoriesBtn.addEventListener('click', () => window.toggleAllCategories());
  }
  document.querySelectorAll('.legend-filter-btn[data-filter-category]').forEach((btn) => {
    btn.addEventListener('click', () => window.toggleMapCategory(btn.getAttribute('data-filter-category')));
  });

  /* ──────────────────────────────────────────────
     12. Auto-Center on Load
     ────────────────────────────────────────────── */
  Promise.all([fetchBorder, fetchLocations]).then(([borderLayer]) => {
    if (borderLayer && borderLayer.getBounds().isValid()) {
      window.mapBounds = borderLayer.getBounds();
    }
  });
}
