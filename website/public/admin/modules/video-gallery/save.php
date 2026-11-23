<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'youtube_url' => trim((string) ($_POST['youtube_url'] ?? '')),
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title'] === '' || mb_strlen($input['title']) > 200) {
    $errors[] = 'Title is required (max 200 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

// The public page builds an <iframe src> straight from this value, so the
// host is checked here too (not just "is it a URL") — the same allow-list
// safeYoutubeUrl() in includes/content.php checks again at render time.
$videoHost = $input['youtube_url'] !== '' ? parse_url($input['youtube_url'], PHP_URL_HOST) : null;
$allowedVideoHosts = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];
if ($input['youtube_url'] === '' || !filter_var($input['youtube_url'], FILTER_VALIDATE_URL) || !in_array($videoHost, $allowedVideoHosts, true)) {
    $errors[] = 'YouTube URL is required and must be a youtube.com or youtube-nocookie.com link.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE video_gallery SET title = :title, youtube_url = :youtube_url,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'video-gallery', $input['title']);
    flashSet('success', 'Video updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO video_gallery (title, youtube_url, display_order, status)
         VALUES (:title, :youtube_url, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'video-gallery', $input['title']);
    flashSet('success', 'Video created.');
}

redirect('index.php');
