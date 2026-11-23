<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/content-blocks.php';
require_once __DIR__ . '/../../includes/upload.php';

requireCapability('content.edit');

requirePostWithCsrf();

$user = currentUser();
$pageKey = (string) ($_POST['page'] ?? '');

if (!isset(blockRegistry()[$pageKey])) {
    flashSet('error', 'That page could not be found.');
    redirect('index.php');
}

$config = blockRegistry()[$pageKey];
$backTarget = 'edit.php?page=' . urlencode($pageKey);

$submitted = (array) ($_POST['blocks'] ?? []);
$resets = (array) ($_POST['reset'] ?? []);

$errors = [];
$values = [];

// Only keys the registry knows about are considered — a crafted POST
// cannot invent a block, and the max lengths mirror the form's own.
foreach ($config['blocks'] as $key => $block) {
    if ($block['type'] === 'image') {
        continue; // a file field, handled below
    }
    $raw = (string) ($submitted[$key] ?? '');
    $value = trim(str_replace("\r\n", "\n", $raw));
    // From BLOCK_LIMITS, which the edit form's maxlength reads too — the two
    // were previously separate literals in the two files, so a limit raised
    // here would silently keep truncating in the browser.
    $limit = blockLimit($block['type']);

    if (mb_strlen($value) > $limit) {
        $errors[] = sprintf('“%s” is too long (max %d characters).', $block['label'], $limit);
    }

    $values[$key] = $value;
}

if ($errors) {
    flashInputSet($values);
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

// Header image (the photo behind this page's green banner), on pages that
// have one - PAGE_HEADER_IMAGE_PAGES in includes/content.php. Handled before
// the text so a rejected file (wrong type, too large) stops the whole save
// with the form still filled in, rather than saving half of it.
$headerChanged = false;
if (in_array($pageKey, PAGE_HEADER_IMAGE_PAGES, true)) {
    $current = getDb()->prepare('SELECT header_image FROM pages WHERE page_key = :page');
    $current->execute(['page' => $pageKey]);
    $oldHeader = $current->fetchColumn() ?: null;

    // handleImageUpload() sniffs the real MIME type, caps the size, stores
    // under a random name in admin/uploads/pages/ (where PHP never runs),
    // writes the size variants, and deletes the file it replaces.
    $upload = handleImageUpload('header_image', 'pages', $oldHeader, !empty($_POST['remove_header_image']));
    if (!$upload['ok']) {
        flashInputSet($values);
        flashSet('error', 'Header image: ' . $upload['error']);
        redirect($backTarget);
    }

    if ($upload['path'] !== $oldHeader) {
        getDb()->prepare(
            'INSERT INTO pages (page_key, header_image, updated_by) VALUES (:page, :image, :user)
             ON DUPLICATE KEY UPDATE header_image = VALUES(header_image), updated_by = VALUES(updated_by)'
        )->execute(['page' => $pageKey, 'image' => $upload['path'], 'user' => $user['id']]);
        $headerChanged = true;
    }
}

// Image blocks (the world map, the Our Mission photo...): a new file
// replaces the picture, "Go back to the original picture" removes the
// upload. handleImageUpload() checks the file and deletes the one it
// replaces. Done before the text, so a rejected file stops the save with
// the form still filled in. $imageValues: key => new path, or null to drop
// the override (the page then shows its original picture).
$imageValues = [];
foreach ($config['blocks'] as $key => $block) {
    if ($block['type'] !== 'image') {
        continue;
    }
    $oldImage = blockIsOverridden($pageKey, $key) ? blockValue($pageKey, $key) : null;
    $upload = handleImageUpload('block_image_' . $key, 'pages', $oldImage, !empty($resets[$key]));
    if (!$upload['ok']) {
        flashInputSet($values);
        flashSet('error', $block['label'] . ': ' . $upload['error']);
        redirect($backTarget);
    }
    if ($upload['path'] !== $oldImage) {
        $imageValues[$key] = $upload['path'];
    }
}

$db = getDb();
$saved = 0;
$reset = 0;

$db->beginTransaction();
try {
    $delete = $db->prepare('DELETE FROM content_blocks WHERE page = :page AND block_key = :key');
    $upsert = $db->prepare(
        'INSERT INTO content_blocks (page, block_key, value, updated_by)
         VALUES (:page, :key, :value, :user)
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)'
    );

    foreach ($config['blocks'] as $key => $block) {
        if ($block['type'] === 'image') {
            if (!array_key_exists($key, $imageValues)) {
                continue; // picture unchanged
            }
            if ($imageValues[$key] === null) {
                $delete->execute(['page' => $pageKey, 'key' => $key]);
                $reset++;
            } else {
                $upsert->execute(['page' => $pageKey, 'key' => $key, 'value' => $imageValues[$key], 'user' => $user['id']]);
                $saved++;
            }
            continue;
        }

        $value = $values[$key];

        // An explicit reset, or text that matches the default anyway, both
        // mean "no override" — storing a copy of the default would just be
        // a second place for it to drift.
        if (!empty($resets[$key]) || $value === '' || $value === $block['default']) {
            $delete->execute(['page' => $pageKey, 'key' => $key]);
            if ($delete->rowCount() > 0) {
                $reset++;
            }
            continue;
        }

        $upsert->execute([
            'page' => $pageKey,
            'key' => $key,
            'value' => $value,
            'user' => $user['id'],
        ]);
        $saved++;
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

logActivity(
    $user['id'],
    'update',
    'pages',
    sprintf(
        '%s — %d block(s) saved, %d reset%s',
        $config['label'],
        $saved,
        $reset,
        $headerChanged ? ', header image changed' : ''
    )
);

flashSet('success', sprintf(
    '%s updated: %d block%s saved%s.',
    $config['label'],
    $saved,
    $saved === 1 ? '' : 's',
    ($reset > 0 ? ", {$reset} reset to default" : '') . ($headerChanged ? ', header image updated' : '')
));

redirect($backTarget);
