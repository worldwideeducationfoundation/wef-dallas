<?php
/**
 * Turns one page on or off (pages.is_enabled). Posted by a switch on
 * index.php. A page with no row yet gets one; its header image, if any,
 * is untouched.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/page-visibility.php';

requireCapability('content.edit');

requirePostWithCsrf();

$user = currentUser();

$slug = (string) ($_POST['page'] ?? '');
$enabled = ($_POST['enabled'] ?? '') === '1' ? 1 : 0;

if (!isset(SWITCHABLE_PAGES[$slug])) {
    flashSet('error', 'That page cannot be switched on or off.');
    redirect('index.php');
}

try {
    $stmt = getDb()->prepare(
        'INSERT INTO pages (page_key, is_enabled, updated_by) VALUES (:page_key, :is_enabled, :updated_by)
         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_by = VALUES(updated_by)'
    );
    $stmt->execute(['page_key' => $slug, 'is_enabled' => $enabled, 'updated_by' => $user['id']]);
} catch (PDOException $e) {
    adminLog('ERROR', 'Pages On/Off save failed: ' . $e->getMessage());
    flashSet('error', 'Could not save. The database may still need the Pages On/Off update (see the note on this page).');
    redirect('index.php');
}

$label = SWITCHABLE_PAGES[$slug];
logActivity($user['id'], 'update', 'pages', $label . ' page turned ' . ($enabled ? 'on' : 'off'));
flashSet('success', $enabled
    ? "{$label} is on again: it is back in the menu and its links work."
    : "{$label} is off: it is out of the menu, links to it are disabled, and its address shows \"Page not found\".");
redirect('index.php');
