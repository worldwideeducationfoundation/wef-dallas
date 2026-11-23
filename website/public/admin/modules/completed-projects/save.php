<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? '')) ?: null,
    'title' => trim((string) ($_POST['title'] ?? '')),
    'summary' => trim((string) ($_POST['summary'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'location' => trim((string) ($_POST['location'] ?? '')) ?: null,
    'completion_year' => trim((string) ($_POST['completion_year'] ?? '')) ?: null,
    'video_url' => trim((string) ($_POST['video_url'] ?? '')) ?: null,
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

if ($input['title'] === '' || mb_strlen($input['title']) > 200) {
    $errors[] = 'Project title is required (max 200 characters).';
}
if ($input['slug'] === '' || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $input['slug'])) {
    $errors[] = 'Slug is required and may only contain lowercase letters, numbers, and hyphens.';
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
// Only youtube.com/youtube-nocookie.com is accepted — this URL is later
// rendered as an <iframe src> on the public page with no further checks
// beyond e(), so validating the host here (not just "is it a URL") is
// what keeps that field from becoming an open embed of anything.
if ($input['video_url'] !== null) {
    $videoHost = parse_url($input['video_url'], PHP_URL_HOST);
    $allowedVideoHosts = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];
    if (!filter_var($input['video_url'], FILTER_VALIDATE_URL) || !in_array($videoHost, $allowedVideoHosts, true)) {
        $errors[] = 'Video URL must be a youtube.com or youtube-nocookie.com link.';
    }
}

if (!$errors && !$isEdit) {
    // New rows always start with no image — this only exists to give a
    // clean error before touching the upload if the required file is
    // missing, matching the "required" attribute on the form field.
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'A main photo is required.';
    }
}

if (!$errors) {
    $slugCheck = getDb()->prepare(
        'SELECT id FROM completed_projects WHERE slug = :slug' . ($isEdit ? ' AND id != :id' : '') . ' LIMIT 1'
    );
    $slugParams = ['slug' => $input['slug']];
    if ($isEdit) {
        $slugParams['id'] = $id;
    }
    $slugCheck->execute($slugParams);
    if ($slugCheck->fetch()) {
        $errors[] = 'That slug is already in use by another completed project.';
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$existingRow = ['image_path' => null, 'secondary_image_path' => null, 'tertiary_image_path' => null, 'map_image_path' => null];
if ($isEdit) {
    $existingStmt = getDb()->prepare(
        'SELECT image_path, secondary_image_path, tertiary_image_path, map_image_path FROM completed_projects WHERE id = :id LIMIT 1'
    );
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch() ?: $existingRow;
}

$uploads = [
    'image_path' => ['field' => 'image', 'old' => $existingRow['image_path'], 'remove' => false],
    'secondary_image_path' => ['field' => 'secondary_image', 'old' => $existingRow['secondary_image_path'], 'remove' => !empty($_POST['remove_secondary_image'])],
    'tertiary_image_path' => ['field' => 'tertiary_image', 'old' => $existingRow['tertiary_image_path'], 'remove' => !empty($_POST['remove_tertiary_image'])],
    'map_image_path' => ['field' => 'map_image', 'old' => $existingRow['map_image_path'], 'remove' => !empty($_POST['remove_map_image'])],
];

foreach ($uploads as $column => $spec) {
    $result = handleImageUpload($spec['field'], 'completed-projects', $spec['old'], $spec['remove']);
    if (!$result['ok']) {
        flashInputSet(array_merge($input, ['id' => $id]));
        flashSet('error', $result['error']);
        redirect($backTarget);
    }
    $input[$column] = $result['path'];
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE completed_projects SET slug = :slug, category = :category, title = :title, summary = :summary,
         description = :description, location = :location, completion_year = :completion_year,
         video_url = :video_url,
         stat1_value = :stat1_value, stat1_label = :stat1_label,
         stat2_value = :stat2_value, stat2_label = :stat2_label,
         stat3_value = :stat3_value, stat3_label = :stat3_label,
         image_path = :image_path, secondary_image_path = :secondary_image_path,
         tertiary_image_path = :tertiary_image_path, map_image_path = :map_image_path,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'completed-projects', $input['title']);
    flashSet('success', 'Completed project updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO completed_projects (slug, category, title, summary, description, location, completion_year,
         video_url,
         stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label,
         image_path, secondary_image_path, tertiary_image_path, map_image_path,
         display_order, status)
         VALUES (:slug, :category, :title, :summary, :description, :location, :completion_year,
         :video_url,
         :stat1_value, :stat1_label, :stat2_value, :stat2_label, :stat3_value, :stat3_label,
         :image_path, :secondary_image_path, :tertiary_image_path, :map_image_path,
         :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'completed-projects', $input['title']);
    flashSet('success', 'Completed project created.');
}

redirect('index.php');
