<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/_photos.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'category' => (string) ($_POST['category'] ?? ''),
    'name' => trim((string) ($_POST['name'] ?? '')),
    'summary' => trim((string) ($_POST['summary'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'badge_text' => trim((string) ($_POST['badge_text'] ?? '')) ?: null,
    'location_text' => trim((string) ($_POST['location_text'] ?? '')) ?: null,
    'year_launched' => trim((string) ($_POST['year_launched'] ?? '')) !== '' ? (int) $_POST['year_launched'] : null,
    'stat1_value' => trim((string) ($_POST['stat1_value'] ?? '')) ?: null,
    'stat1_label' => trim((string) ($_POST['stat1_label'] ?? '')) ?: null,
    'stat2_value' => trim((string) ($_POST['stat2_value'] ?? '')) ?: null,
    'stat2_label' => trim((string) ($_POST['stat2_label'] ?? '')) ?: null,
    'stat3_value' => trim((string) ($_POST['stat3_value'] ?? '')) ?: null,
    'stat3_label' => trim((string) ($_POST['stat3_label'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['name'] === '' || mb_strlen($input['name']) > 160) {
    $errors[] = 'Program name is required (max 160 characters).';
}
if ($input['slug'] === '' || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $input['slug'])) {
    $errors[] = 'Slug is required and may only contain lowercase letters, numbers, and hyphens.';
}
if (!in_array($input['category'], ['early_childhood', 'programs', 'scholarships'], true)) {
    $errors[] = 'Please choose a valid category.';
}
if ($input['summary'] === '' || mb_strlen($input['summary']) > 500) {
    $errors[] = 'Summary is required (max 500 characters).';
}
if ($input['description'] === '') {
    $errors[] = 'Full description is required.';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
if ($input['year_launched'] !== null && ($input['year_launched'] < 1900 || $input['year_launched'] > 2100)) {
    $errors[] = 'Year launched must be between 1900 and 2100.';
}

if (!$errors) {
    $slugCheck = getDb()->prepare(
        'SELECT id FROM programs WHERE slug = :slug' . ($isEdit ? ' AND id != :id' : '') . ' LIMIT 1'
    );
    $slugParams = ['slug' => $input['slug']];
    if ($isEdit) {
        $slugParams['id'] = $id;
    }
    $slugCheck->execute($slugParams);
    if ($slugCheck->fetch()) {
        $errors[] = 'That slug is already in use by another program.';
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$existingRow = [];
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT image_path, before_image_path, after_image_path FROM programs WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch() ?: [];
}

// The card photo, then the optional before/after pair for the comparison
// slider. Each keeps its current file unless a new one is uploaded or its
// "Remove" box is ticked.
foreach (['image' => 'image_path', 'before_image' => 'before_image_path', 'after_image' => 'after_image_path'] as $field => $column) {
    $upload = handleImageUpload($field, 'programs', $existingRow[$column] ?? null, !empty($_POST['remove_' . $field]));
    if (!$upload['ok']) {
        flashInputSet(array_merge($input, ['id' => $id]));
        flashSet('error', $upload['error']);
        redirect($backTarget);
    }
    $input[$column] = $upload['path'];
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE programs SET slug = :slug, category = :category, name = :name, summary = :summary,
         description = :description, badge_text = :badge_text, location_text = :location_text,
         year_launched = :year_launched,
         stat1_value = :stat1_value, stat1_label = :stat1_label,
         stat2_value = :stat2_value, stat2_label = :stat2_label,
         stat3_value = :stat3_value, stat3_label = :stat3_label,
         image_path = :image_path, before_image_path = :before_image_path, after_image_path = :after_image_path,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'programs', $input['name']);
    flashSet('success', 'Program updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO programs (slug, category, name, summary, description, badge_text, location_text,
         year_launched,
         stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label,
         image_path, before_image_path, after_image_path, display_order, status)
         VALUES (:slug, :category, :name, :summary, :description, :badge_text, :location_text,
         :year_launched,
         :stat1_value, :stat1_label, :stat2_value, :stat2_label, :stat3_value, :stat3_label,
         :image_path, :before_image_path, :after_image_path, :display_order, :status)'
    );
    $stmt->execute($input);
    // Read before logActivity(), whose own INSERT would replace it.
    $newProgramId = (int) getDb()->lastInsertId();
    logActivity($user['id'], 'create', 'programs', $input['name']);
    flashSet('success', 'Program created.');
}

// --- Project photos (program_photos) ------------------------------------
$programId = $isEdit ? $id : $newProgramId;
$photoResult = saveProgramPhotos(getDb(), $programId, (array) ($_POST['photos'] ?? []), $_FILES);
$photosAdded = $photoResult['added'];
$photoErrors = $photoResult['errors'];

if ($photosAdded > 0) {
    logActivity($user['id'], 'update', 'programs', $input['name'] . ': ' . $photosAdded . ' photo(s) added');
}

$saved = $isEdit ? 'Program updated.' : 'Program created.';
if ($photoErrors) {
    flashSet('error', $saved . ' ' . implode(' ', $photoErrors));
} elseif ($photosAdded > 0) {
    flashSet('success', $saved . ' ' . $photosAdded . ' photo' . ($photosAdded === 1 ? '' : 's') . ' added - add captions below if you like.');
}

// Straight back to the form after adding photos, where their captions and
// order are edited.
redirect($photosAdded > 0 || $photoErrors ? 'form.php?id=' . $programId : 'index.php');
