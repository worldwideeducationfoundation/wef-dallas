<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('settings.manage');

requirePostWithCsrf();

$user = currentUser();

$fields = [
    'contact_email', 'contact_phone', 'contact_address',
    'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url', 'youtube_url',
    'donate_url',
];

$input = [];
foreach ($fields as $field) {
    $input[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
}

$errors = [];

if ($input['contact_email'] !== null && !filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Contact email must be a valid email address.';
}

$urlFields = ['facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url', 'youtube_url'];
foreach ($urlFields as $field) {
    if ($input[$field] !== null && !filter_var($input[$field], FILTER_VALIDATE_URL)) {
        $label = ucfirst(str_replace(['_url', 'twitter'], ['', 'X (Twitter)'], $field));
        $errors[] = "{$label} must be a valid URL.";
    }
}

// The donate link lands in an href and a window.open() call on every page,
// so only a full http(s) address is accepted - never javascript: or a path.
if ($input['donate_url'] !== null
    && (!filter_var($input['donate_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $input['donate_url']))) {
    $errors[] = 'Donate link must be a full web address starting with https://.';
}

if ($errors) {
    flashInputSet($input);
    flashSet('error', implode(' ', $errors));
    redirect('index.php');
}

// Row id=1 is guaranteed to already exist (index.php creates it on first
// load) — this is always an UPDATE, never an insert.
$stmt = getDb()->prepare(
    'UPDATE site_settings SET contact_email = :contact_email, contact_phone = :contact_phone,
     contact_address = :contact_address, facebook_url = :facebook_url, twitter_url = :twitter_url,
     instagram_url = :instagram_url, linkedin_url = :linkedin_url, youtube_url = :youtube_url,
     donate_url = :donate_url
     WHERE id = 1'
);
$stmt->execute($input);

logActivity($user['id'], 'update', 'settings', 'Site settings updated');
flashSet('success', 'Settings saved.');
redirect('index.php');
