<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_options.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

// "35.83, 71.79" pasted whole into the latitude box is split for the admin.
$latRaw = trim((string) ($_POST['latitude'] ?? ''));
$lngRaw = trim((string) ($_POST['longitude'] ?? ''));
if ($lngRaw === '' && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $latRaw, $m)) {
    [$latRaw, $lngRaw] = [$m[1], $m[2]];
}

$input = [
    'lead_name' => trim((string) ($_POST['lead_name'] ?? '')),
    'sponsor' => trim((string) ($_POST['sponsor'] ?? '')),
    'village' => trim((string) ($_POST['village'] ?? '')),
    'district' => (string) ($_POST['district'] ?? ''),
    'center_model' => (string) ($_POST['center_model'] ?? ''),
    'latitude' => $latRaw,
    'longitude' => $lngRaw,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'active'),
];

$errors = [];
if ($input['village'] === '' || mb_strlen($input['village']) > 255) {
    $errors[] = 'Village is required (max 255 characters).';
}
if ($input['sponsor'] === '' || mb_strlen($input['sponsor']) > 255) {
    $errors[] = 'Sponsor is required (max 255 characters).';
}
if (mb_strlen($input['lead_name']) > 160) {
    $errors[] = 'Center lead must be 160 characters or fewer.';
}
if (!in_array($input['district'], MAP_CENTER_DISTRICTS, true)) {
    $errors[] = 'Please choose a district.';
}
if (!in_array($input['center_model'], MAP_CENTER_MODELS, true)) {
    $errors[] = 'Please choose a type.';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if (!is_numeric($input['latitude']) || !is_numeric($input['longitude'])) {
    $errors[] = 'Latitude and longitude must be numbers, e.g. 35.838713 and 71.791280.';
} else {
    $lat = (float) $input['latitude'];
    $lng = (float) $input['longitude'];
    if ($lat < MAP_CENTER_LAT_RANGE[0] || $lat > MAP_CENTER_LAT_RANGE[1] || $lng < MAP_CENTER_LNG_RANGE[0] || $lng > MAP_CENTER_LNG_RANGE[1]) {
        $errors[] = 'Those coordinates are not in Chitral. Check that latitude (about 35-36) and longitude (about 71-73) are not swapped.';
    }
    $input['latitude'] = round($lat, 6);
    $input['longitude'] = round($lng, 6);
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$input['lead_name'] = $input['lead_name'] === '' ? null : $input['lead_name'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE map_centers SET lead_name = :lead_name, sponsor = :sponsor, village = :village, district = :district,
         center_model = :center_model, latitude = :latitude, longitude = :longitude,
         display_order = :display_order, status = :status WHERE id = :id AND deleted_at IS NULL'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'map-centers', $input['village']);
    flashSet('success', 'Center updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO map_centers (lead_name, sponsor, village, district, center_model, latitude, longitude, display_order, status)
         VALUES (:lead_name, :sponsor, :village, :district, :center_model, :latitude, :longitude, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'map-centers', $input['village']);
    flashSet('success', 'Center added.');
}

redirect('index.php');
