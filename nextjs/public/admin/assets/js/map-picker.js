/* Map Centers form: place the center's pin on a map.
   Click the map to drop the pin, drag it to fine-tune; the Latitude and
   Longitude fields follow, and typing in them moves the pin. The centers
   already on the website show as grey dots, so a new one can be placed
   next to its neighbours. Loaded (with admin/assets/vendor/leaflet) on
   modules/map-centers/form.php only. */
(function () {
  var el = document.getElementById('center-map');
  if (!el || typeof L === 'undefined') return;

  var latInput = document.getElementById(el.getAttribute('data-lat-input'));
  var lngInput = document.getElementById(el.getAttribute('data-lng-input'));
  var status = document.getElementById('center-map-status');

  var street = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
  });
  var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 19,
    attribution: 'Imagery &copy; Esri'
  });

  var map = L.map(el, { layers: [satellite], scrollWheelZoom: false });
  L.control.layers({ 'Satellite': satellite, 'Street map': street }).addTo(map);
  // Scroll-zoom only once the map has been clicked, so scrolling down the
  // form does not get caught by it.
  map.on('focus click', function () { map.scrollWheelZoom.enable(); });

  var pinIcon = L.divIcon({ className: 'center-map-pin', iconSize: [26, 26], iconAnchor: [13, 26] });
  var pin = null;

  function readInputs() {
    var lat = parseFloat(latInput.value);
    var lng = parseFloat(lngInput.value);
    return (isFinite(lat) && isFinite(lng)) ? L.latLng(lat, lng) : null;
  }

  function say(text) {
    if (status) status.textContent = text;
  }

  function place(latlng, fromInputs) {
    if (!pin) {
      pin = L.marker(latlng, { icon: pinIcon, draggable: true, keyboard: true, title: 'This center' }).addTo(map);
      pin.on('dragend', function () { place(pin.getLatLng(), false); });
    } else {
      pin.setLatLng(latlng);
    }
    if (!fromInputs) {
      latInput.value = latlng.lat.toFixed(6);
      lngInput.value = latlng.lng.toFixed(6);
      say('Pin placed at ' + latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6) + '. Save to put it on the website.');
    }
  }

  var start = readInputs();
  if (start) {
    map.setView(start, 15);
    place(start, true);
  } else {
    // All of Chitral, until a pin is placed.
    map.fitBounds([[35.2, 71.2], [36.9, 73.6]]);
    say('Click the map where the center is to place its pin.');
  }

  map.on('click', function (e) { place(e.latlng, false); });

  // Leaflet measures its box when created; the form can still be settling
  // (fonts, the sidebar), which left an untiled strip. Measure again.
  window.addEventListener('load', function () { map.invalidateSize(); });
  window.addEventListener('resize', function () { map.invalidateSize(); });

  [latInput, lngInput].forEach(function (input) {
    input.addEventListener('change', function () {
      var latlng = readInputs();
      if (latlng) {
        place(latlng, true);
        map.panTo(latlng);
      }
    });
  });

  // The other centers, for reference.
  var url = el.getAttribute('data-centers-url');
  if (url) {
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.features) return;
        data.features.forEach(function (f) {
          var c = f.geometry && f.geometry.coordinates;
          if (!c) return;
          var here = start && Math.abs(start.lat - c[1]) < 1e-6 && Math.abs(start.lng - c[0]) < 1e-6;
          if (here) return;
          L.circleMarker([c[1], c[0]], { radius: 5, color: '#ffffff', weight: 1.5, fillColor: '#6b7280', fillOpacity: 0.9, interactive: true })
            .bindTooltip(String((f.properties && f.properties.village) || ''))
            .addTo(map);
        });
      })
      .catch(function () { /* the picker still works without the reference dots */ });
  }
})();
