<?php
/**
 * The fixed choices for a map center, shared by the form and save.php so
 * the two cannot drift. The center types are the four the map's legend
 * colours (js/map.js categoryColors); a new type needs a colour there too.
 */
const MAP_CENTER_DISTRICTS = ['Upper Chitral', 'Lower Chitral'];
const MAP_CENTER_MODELS = ['Entrepreneurial Model', 'Government School', 'Private School', 'Semi Govt School'];

// Chitral with a generous margin: catches a latitude/longitude swap or a
// missing digit, which would otherwise put a pin in another country.
const MAP_CENTER_LAT_RANGE = [34.5, 37.5];
const MAP_CENTER_LNG_RANGE = [70.5, 74.5];
