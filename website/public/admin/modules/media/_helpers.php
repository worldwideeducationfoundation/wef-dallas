<?php
/**
 * Shared by the Media Manager pages - kept in one place deliberately.
 * delete.php uses this to decide whether a file is safe to remove, so a
 * second, slightly different copy of this list would be a real way to
 * accidentally delete something still in use.
 */

require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../../../includes/image-derivatives.php';

/**
 * Every upload path currently referenced by content, as a lookup map keyed by
 * path.
 *
 * The list of (table, file column) pairs is DERIVED from TRASH_TYPES rather
 * than written out here. It used to be a hand-maintained array of five
 * SELECTs, and it had already fallen behind: project_updates was missing, even
 * though that module uploads images and TRASH_TYPES had listed its
 * `fileColumns` correctly all along.
 *
 * The consequence was not cosmetic. Every image attached to a project update
 * would appear in the Media manager as unreferenced, with a delete button next
 * to it. An admin tidying up orphans would permanently unlink a file that a
 * published update still pointed at, and delete.php's second-chance re-check
 * consulted this same incomplete list, so it failed identically. Deriving the
 * list from the registry means adding a content type can no longer leave this
 * behind.
 *
 * DELIBERATELY has no "deleted_at IS NULL" filter. Rows in Trash still own
 * their files: if trashed items were treated as unreferenced, their images
 * would show up here as orphans, an admin would tidy them away, and restoring
 * the item afterwards would bring back a record pointing at a file that no
 * longer exists. Files are only removed by a purge, in includes/trash.php.
 */
function referencedUploadPaths(): array
{
    static $cached = null;

    // index.php calls this once and delete.php calls it once; caching
    // keeps a future third caller from silently doubling the query count.
    if ($cached !== null) {
        return $cached;
    }

    $selects = [];
    foreach (mediaReferenceColumns() as [$table, $column]) {
        // Table and column names come from the registry constant, never
        // from a request. Aliased to a common name so UNION ALL is happy
        // whichever column each table happens to use.
        $selects[] = sprintf(
            'SELECT `%s` AS p FROM `%s` WHERE `%s` IS NOT NULL AND `%s` <> \'\'',
            $column,
            $table,
            $column,
            $column
        );
    }

    if ($selects === []) {
        return $cached = [];
    }

    $rows = getDb()->query(implode(' UNION ALL ', $selects))->fetchAll(PDO::FETCH_COLUMN);

    $cached = array_fill_keys($rows, true);

    return $cached;
}

/**
 * Every (table, column) that can hold an upload path: the file columns of
 * every content type in TRASH_TYPES, plus the tables that are not trashable
 * content. Shared by referencedUploadPaths() (is a file in use?) and
 * replaceUploadReferences() (point everything at a replacement file), so
 * the two can never disagree about where files are used.
 *
 * @return list<array{0: string, 1: string}>
 */
function mediaReferenceColumns(): array
{
    $pairs = [];
    // installedTrashTypes(), not TRASH_TYPES: a table migrate.php hasn't
    // created yet holds no references, and querying it would fail the page.
    foreach (installedTrashTypes() as $config) {
        foreach ($config['fileColumns'] ?? [] as $column) {
            $pairs[] = [$config['table'], $column];
        }
    }

    // Files referenced from tables that are not trashable content types, so
    // they are not in TRASH_TYPES - without these, an in-use page header
    // image would be offered for deletion as "unreferenced".
    // content_blocks: pictures uploaded into Page Content image blocks.
    $installedTables = array_flip(array_map(
        'strtolower',
        getDb()->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')
            ->fetchAll(PDO::FETCH_COLUMN)
    ));
    foreach ([['pages', 'header_image'], ['program_photos', 'image_path'], ['content_blocks', 'value']] as [$table, $column]) {
        if (isset($installedTables[$table])) {
            $pairs[] = [$table, $column];
        }
    }

    return $pairs;
}

/**
 * Points every reference to $oldPath at $newPath (both relative to
 * admin/uploads/). Exact matches only, in one transaction: a replaced file
 * is either switched everywhere or nowhere. Returns how many were changed.
 */
function replaceUploadReferences(string $oldPath, string $newPath): int
{
    $db = getDb();
    $changed = 0;
    $db->beginTransaction();
    try {
        foreach (mediaReferenceColumns() as [$table, $column]) {
            $stmt = $db->prepare(sprintf('UPDATE `%s` SET `%s` = :new WHERE `%s` = :old', $table, $column, $column));
            $stmt->execute(['new' => $newPath, 'old' => $oldPath]);
            $changed += $stmt->rowCount();
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $changed;
}

/**
 * Where files added directly in the Media Manager go (admin/uploads/library/).
 * They belong to no content item, so they are listed as "Library" rather than
 * orphaned: kept until someone deletes them, for linking from anywhere
 * (a PDF for a project update's Read More, a photo to share).
 */
const MEDIA_LIBRARY_FOLDER = 'library';

/**
 * The absolute path of an original upload named by a path relative to
 * admin/uploads/, or null for anything else: a path outside uploads/, a
 * missing file, a dotfile (.htaccess), or a size variant this code generated
 * (those follow their original and are never handled on their own).
 */
function mediaFileFromPath(string $path): ?string
{
    $root = realpath(__DIR__ . '/../../uploads');
    if ($root === false || $path === '' || str_contains($path, "\0")) {
        return null;
    }
    $abs = realpath($root . '/' . $path);
    if ($abs === false || !is_file($abs)
        || !str_starts_with($abs, rtrim($root, '/\\') . DIRECTORY_SEPARATOR)
        || str_starts_with(basename($abs), '.')
        || isImageDerivative($abs)) {
        return null;
    }

    return $abs;
}

/** 'image', 'pdf' or 'other', from the file's extension. */
function mediaKind(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return 'image';
    }

    return $ext === 'pdf' ? 'pdf' : 'other';
}
