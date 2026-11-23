<?php
/**
 * Saving a project's photo set (program_photos) from the Programs form.
 * Include-only; refused over HTTP by the admin .htaccess (^_.*\.php).
 */

/**
 * Applies the "Project photos" part of the form: captions, order, replace and
 * remove for existing photos, then the new uploads.
 *
 * Every photo id posted is checked against $programId, so a crafted form can
 * never edit or delete another project's photos. Uploads go through
 * handleImageUpload(), which keeps doing all the checking (real MIME type,
 * size cap, generated filename, WebP twin and size variants).
 *
 * @param array $post  $_POST['photos']: [photoId => [caption, order, remove]]
 * @param array $files $_FILES, for new_photos[] and photo_replace_<id>
 * @return array{added: int, errors: list<string>}
 */
function saveProgramPhotos(PDO $db, int $programId, array $post, array $files): array
{
    $errors = [];
    $added = 0;

    $ownedStmt = $db->prepare('SELECT id, image_path FROM program_photos WHERE program_id = :pid');
    $ownedStmt->execute(['pid' => $programId]);
    $owned = array_column($ownedStmt->fetchAll(), 'image_path', 'id');

    foreach ($post as $photoId => $fields) {
        $photoId = (int) $photoId;
        if (!isset($owned[$photoId]) || !is_array($fields)) {
            continue;
        }

        if (!empty($fields['remove'])) {
            $db->prepare('DELETE FROM program_photos WHERE id = :id AND program_id = :pid')
                ->execute(['id' => $photoId, 'pid' => $programId]);
            deleteUploadedFile($owned[$photoId]);
            continue;
        }

        $path = $owned[$photoId];
        if (isset($files['photo_replace_' . $photoId])) {
            $_FILES['photo_replace_' . $photoId] = $files['photo_replace_' . $photoId];
            $replace = handleImageUpload('photo_replace_' . $photoId, 'programs', $path);
            if ($replace['ok'] && $replace['path'] !== null) {
                $path = $replace['path'];
            } elseif (!$replace['ok']) {
                $errors[] = 'Photo not replaced: ' . $replace['error'];
            }
        }

        $caption = mb_substr(trim((string) ($fields['caption'] ?? '')), 0, 160);
        $db->prepare('UPDATE program_photos SET caption = :caption, display_order = :ord, image_path = :path WHERE id = :id AND program_id = :pid')
            ->execute([
                'caption' => $caption === '' ? null : $caption,
                'ord' => (int) ($fields['order'] ?? 0),
                'path' => $path,
                'id' => $photoId,
                'pid' => $programId,
            ]);
    }

    // The multi-file field arrives as parallel arrays; each file is handed to
    // handleImageUpload() as a single upload of its own.
    $new = $files['new_photos'] ?? null;
    if (is_array($new) && is_array($new['name'] ?? null)) {
        $orderStmt = $db->prepare('SELECT COALESCE(MAX(display_order), 0) FROM program_photos WHERE program_id = :pid');
        $orderStmt->execute(['pid' => $programId]);
        $nextOrder = (int) $orderStmt->fetchColumn();

        foreach (array_keys($new['name']) as $n) {
            if (($new['error'][$n] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($added >= 12) {
                $errors[] = 'Only 12 photos can be added per save; save again for the rest.';
                break;
            }
            $_FILES['__project_photo'] = [
                'name' => $new['name'][$n],
                'type' => $new['type'][$n],
                'tmp_name' => $new['tmp_name'][$n],
                'error' => $new['error'][$n],
                'size' => $new['size'][$n],
            ];
            $upload = handleImageUpload('__project_photo', 'programs');
            if (!$upload['ok'] || $upload['path'] === null) {
                $errors[] = (string) $new['name'][$n] . ': ' . ($upload['error'] ?? 'not saved');
                continue;
            }
            $nextOrder += 10;
            $db->prepare('INSERT INTO program_photos (program_id, image_path, display_order) VALUES (:pid, :path, :ord)')
                ->execute(['pid' => $programId, 'path' => $upload['path'], 'ord' => $nextOrder]);
            $added++;
        }
        unset($_FILES['__project_photo']);
    }

    return ['added' => $added, 'errors' => $errors];
}
