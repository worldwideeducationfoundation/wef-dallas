<?php
/**
 * Soft delete + Trash.
 *
 * Delete used to mean DELETE: the row went, the uploaded image was
 * unlinked, and there was no way back. Now "Delete" stamps deleted_at and
 * the item moves to Trash, where it can be restored intact or purged on
 * purpose. Uploaded files are left alone until a purge, which is what
 * makes a restore actually restore something.
 *
 * TRASH_TYPES is the single registry every part of this feature reads —
 * the Trash page, the restore/purge handlers, and the media manager's
 * "still referenced?" check all iterate it, so adding a new content type
 * in one place cannot leave another part behind.
 *
 * Each type also declares the three capabilities that govern it. Trash used to
 * assume every type was site content and check content.view / content.edit /
 * content.delete for all of them — which was true right up until form
 * submissions were registered here. Those are governed by submissions.*, and
 * under the old blanket check an editor (content.view, no submissions.view)
 * would have been able to read every trashed enquirer's name on this page and
 * restore it, straight past the capability that exists to stop exactly that.
 * Per-type capabilities are what keep Trash from becoming a way around the
 * permission on the module an item came from.
 */

require_once __DIR__ . '/upload.php';

const TRASH_TYPES = [
    'programs' => [
        'table' => 'programs',
        'label' => 'Program',
        'labelColumn' => 'name',
        'fileColumns' => ['image_path', 'before_image_path', 'after_image_path'],
        // The project-page photo set: rows go with the project (ON DELETE
        // CASCADE), and their files are unlinked by purgeItems().
        'childFiles' => [['table' => 'program_photos', 'foreignKey' => 'program_id', 'column' => 'image_path']],
        'module' => 'programs',
        'editPath' => 'modules/programs/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'team' => [
        'table' => 'team_members',
        'label' => 'Team member',
        'labelColumn' => 'name',
        'fileColumns' => ['image_path'],
        'module' => 'team',
        'editPath' => 'modules/team/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'gallery' => [
        'table' => 'gallery_items',
        'label' => 'Gallery photo',
        'shortLabel' => 'Photo',
        'labelColumn' => 'caption',
        'fileColumns' => ['image_path'],
        'module' => 'gallery',
        'editPath' => 'modules/gallery/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'reports' => [
        'table' => 'annual_reports',
        'label' => 'Annual report',
        'shortLabel' => 'Report',
        'labelColumn' => 'title',
        'fileColumns' => ['cover_image_path', 'pdf_path'],
        'module' => 'reports',
        'editPath' => 'modules/reports/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'updates' => [
        'table' => 'project_updates',
        'label' => 'Project update',
        'shortLabel' => 'Update',
        'labelColumn' => 'title',
        'fileColumns' => ['image_path'],
        'module' => 'updates',
        'editPath' => 'modules/updates/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'completed-projects' => [
        'table' => 'completed_projects',
        'label' => 'Completed project',
        'shortLabel' => 'Project',
        'labelColumn' => 'title',
        'fileColumns' => ['image_path', 'secondary_image_path', 'tertiary_image_path', 'map_image_path'],
        'module' => 'completed-projects',
        'editPath' => 'modules/completed-projects/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'success-stories' => [
        'table' => 'success_stories',
        'label' => 'Success story',
        'shortLabel' => 'Story',
        'labelColumn' => 'title',
        'fileColumns' => ['image_path'],
        'module' => 'success-stories',
        'editPath' => 'modules/success-stories/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'map-centers' => [
        'table' => 'map_centers',
        'label' => 'Map center',
        'labelColumn' => 'village',
        'fileColumns' => [],
        'module' => 'map-centers',
        'editPath' => 'modules/map-centers/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'video-gallery' => [
        'table' => 'video_gallery',
        'label' => 'Video',
        'labelColumn' => 'title',
        'fileColumns' => [],
        'module' => 'video-gallery',
        'editPath' => 'modules/video-gallery/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'mission-projects' => [
        'table' => 'mission_projects',
        'label' => 'Mission project',
        'shortLabel' => 'Project',
        'labelColumn' => 'what_header',
        'fileColumns' => [],
        'module' => 'mission-projects',
        'editPath' => 'modules/mission-projects/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'impact-milestones' => [
        'table' => 'impact_milestones',
        'label' => 'Roadmap milestone',
        'shortLabel' => 'Milestone',
        'labelColumn' => 'milestone_date',
        'fileColumns' => [],
        'module' => 'impact-milestones',
        'editPath' => 'modules/impact-milestones/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'hero-slides' => [
        'table' => 'hero_slides',
        'label' => 'Hero slide',
        'shortLabel' => 'Slide',
        'labelColumn' => 'title_highlight',
        'fileColumns' => ['image_path'],
        'module' => 'hero-slides',
        'editPath' => 'modules/hero-slides/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'partners' => [
        'table' => 'partners',
        'label' => 'Partner logo',
        'shortLabel' => 'Partner',
        'labelColumn' => 'name',
        'fileColumns' => ['logo_path'],
        'module' => 'partners',
        'editPath' => 'modules/partners/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'donate-options' => [
        'table' => 'donate_options',
        'label' => 'Donate option',
        'shortLabel' => 'Option',
        'labelColumn' => 'slug',
        'fileColumns' => ['image_path'],
        'module' => 'donate-options',
        'editPath' => 'modules/donate-options/form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'sponsors' => [
        'table' => 'sponsors',
        'label' => 'Sponsor',
        'labelColumn' => 'name',
        'fileColumns' => [],
        'module' => 'sponsors',
        'editPath' => 'modules/sponsors/sponsor-form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    'sponsor_groups' => [
        'table' => 'sponsor_groups',
        'label' => 'Sponsor group',
        'labelColumn' => 'title',
        'fileColumns' => [],
        'module' => 'sponsors',
        'editPath' => 'modules/sponsors/group-form.php',
        'viewCap' => 'content.view',
        'restoreCap' => 'content.edit',
        'purgeCap' => 'content.delete',
    ],
    // The one type here that is not site content. Registered anyway so a
    // deleted enquiry behaves like everything else — recoverable rather than
    // gone — which matters more here than for content: a message from the
    // public cannot be rewritten from memory if it turns out someone deleted
    // the wrong one. editPath opens the read-only detail page; there is no
    // form to edit a submission on.
    //
    // restore and purge share one capability because, unlike content, there is
    // no middle ground to grant: submissions.manage is already the line
    // between reading enquiries and acting on them.
    'submissions' => [
        'table' => 'form_submissions',
        'label' => 'Form submission',
        'shortLabel' => 'Submission',
        'labelColumn' => 'name',
        'fileColumns' => [],
        'module' => 'submissions',
        'editPath' => 'modules/submissions/view.php',
        'viewCap' => 'submissions.view',
        'restoreCap' => 'submissions.manage',
        'purgeCap' => 'submissions.manage',
    ],
];

/**
 * The types the signed-in user is allowed to see in Trash.
 *
 * Everything that reads the registry for display or for a count goes through
 * this, so a type can never appear in the list, in the sidebar badge, or in a
 * pager total for someone who cannot open the module it came from.
 *
 * @return array<string, array<string, mixed>>
 */
function visibleTrashTypes(): array
{
    return array_filter(
        installedTrashTypes(),
        static fn(array $config): bool => can($config['viewCap'])
    );
}

/**
 * TRASH_TYPES minus any type whose table does not exist in this database yet.
 *
 * Code ships before the database catches up: a new module's table only exists
 * once admin/database/migrate.php has been run on that server. The Trash badge
 * is in the sidebar of every admin page, so a single missing table used to
 * turn the whole admin panel into a "Base table or view not found" error until
 * someone ran the migration. A missing table can hold no trashed rows and no
 * file references, so leaving it out is exact, not an approximation - and the
 * gap is logged so it is still noticed.
 *
 * @return array<string, array>
 */
function installedTrashTypes(): array
{
    static $installed = null;

    if ($installed === null) {
        try {
            $tables = getDb()
                ->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')
                ->fetchAll(PDO::FETCH_COLUMN);
            $tables = array_flip(array_map('strtolower', $tables));
        } catch (Throwable $e) {
            // Can't tell - assume everything is there, as before.
            return $installed = TRASH_TYPES;
        }

        $installed = [];
        foreach (TRASH_TYPES as $type => $config) {
            if (isset($tables[strtolower($config['table'])])) {
                $installed[$type] = $config;
            } else {
                adminLog('WARN', "table '{$config['table']}' does not exist - run admin/database/migrate.php");
            }
        }
    }

    return $installed;
}

/**
 * SQL fragment excluding trashed rows. Every list query needs this — a
 * soft-deleted row that still shows up in the list is worse than no soft
 * delete at all.
 */
function notTrashed(string $alias = ''): string
{
    $prefix = $alias === '' ? '' : $alias . '.';

    return $prefix . 'deleted_at IS NULL';
}

/**
 * Looks up a type, complaining loudly if it isn't registered.
 *
 * An unregistered type used to just return 0 quietly, which meant a
 * module wired to a missing type reported "moved to Trash" while the row
 * stayed live — the worst kind of failure. This doesn't throw (a
 * half-wired module shouldn't take a page down) but it is now visible in
 * the error log instead of silent.
 */
function trashConfig(string $type, string $operation): ?array
{
    if (isset(TRASH_TYPES[$type])) {
        return TRASH_TYPES[$type];
    }

    adminLog('ERROR', sprintf(
        '%s(): unknown trash type "%s" — nothing was changed. Add it to TRASH_TYPES in admin/includes/trash.php.',
        $operation,
        $type
    ));

    return null;
}

/**
 * The noun used when talking to the user about a single item of this type.
 *
 * The Trash listing wants the fuller form ("Gallery photo") because the list
 * mixes types and needs them distinguishable; a flash message on the gallery
 * page is already in context and reads better short ("Photo moved to Trash").
 * Both live in TRASH_TYPES so neither is a literal buried in a handler.
 */
function trashLabel(array $config, bool $short = false): string
{
    return $short
        ? ($config['shortLabel'] ?? $config['label'])
        : $config['label'];
}

/**
 * The whole "delete one item" handler, for the modules whose delete.php did
 * nothing else.
 *
 * Those files were 37 lines each and four of them were byte-identical once
 * identifiers were normalised away — same POST guard, same CSRF block, same
 * lookup, same trashItems() call, same log line, same two flash messages. The
 * codebase had already solved exactly this shape twice, in processBulkAction()
 * and processReorder(); single delete simply never got the same treatment.
 * Everything it needs (table, label column, module) is already in TRASH_TYPES.
 *
 * Modules with genuinely different delete logic — users (self-lockout and
 * last-super-admin guards), media (orphan re-check), sponsors (cascade
 * confirmation) — deliberately keep their own handlers. This is for the ones
 * where the only difference was a noun.
 *
 * Never returns: always ends in a redirect.
 */
function processDelete(string $type, string $redirectTo = 'index.php'): void
{
    // Capability first, then method, then CSRF — the same order the handwritten
    // handlers used. It matters for the unauthenticated case: checking the
    // capability first sends a signed-out visitor straight to the login page,
    // whereas failing the method check first would bounce them to index.php and
    // only redirect to login on the next hop.
    requireCapability('content.delete', $redirectTo);

    requirePostWithCsrf($redirectTo);

    $config = trashConfig($type, 'processDelete');
    if ($config === null) {
        flashSet('error', 'Unknown content type.');
        redirect($redirectTo);
    }

    $id = postId();
    if ($id === null) {
        redirect($redirectTo);
    }

    $noun = trashLabel($config, true);

    // Table and label column are literals from TRASH_TYPES, never request input.
    $row = dbFetchOne(
        "SELECT {$config['labelColumn']} AS item_label
         FROM {$config['table']}
         WHERE id = :id AND deleted_at IS NULL
         LIMIT 1",
        ['id' => $id]
    );

    if ($row === null) {
        flashSet('error', "That {$noun} could not be found.");
        redirect($redirectTo);
    }

    trashItems($type, [$id]);

    logActivity(currentUser()['id'], 'delete', $config['module'], $row['item_label']);
    flashSet('success', "{$noun} moved to Trash. You can restore it from Trash.");

    redirect($redirectTo);
}

/**
 * Moves rows to Trash. Returns how many were affected.
 *
 * Sponsor groups cascade: the group's sponsors go to Trash with it, and
 * restoring the group brings back exactly those sponsors (see
 * restoreItems). The database FK is ON DELETE CASCADE, which only fires
 * on a real DELETE — so with soft delete the cascade has to be explicit
 * here, or a "deleted" group would leave its sponsors live.
 */
function trashItems(string $type, array $ids): int
{
    $config = trashConfig($type, 'trashItems');
    if ($config === null || $ids === []) {
        return 0;
    }

    $db = getDb();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $now = date('Y-m-d H:i:s');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "UPDATE {$config['table']} SET deleted_at = ?
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $stmt->execute(array_merge([$now], $ids));
        $count = $stmt->rowCount();

        if ($type === 'sponsor_groups') {
            // Stamped with the same timestamp so restore can identify
            // exactly which sponsors went down with this group, rather
            // than resurrecting ones trashed separately beforehand.
            $cascade = $db->prepare(
                "UPDATE sponsors SET deleted_at = ?
                 WHERE group_id IN ({$placeholders}) AND deleted_at IS NULL"
            );
            $cascade->execute(array_merge([$now], $ids));
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $count;
}

/**
 * Restores rows from Trash.
 */
function restoreItems(string $type, array $ids): int
{
    $config = trashConfig($type, 'restoreItems');
    if ($config === null || $ids === []) {
        return 0;
    }

    $db = getDb();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $db->beginTransaction();
    try {
        if ($type === 'sponsor_groups') {
            // Restore the sponsors that were trashed *with* this group —
            // matched on the group's own deleted_at — before clearing the
            // group's stamp, since clearing it first loses the key.
            $groups = $db->prepare(
                "SELECT id, deleted_at FROM sponsor_groups
                 WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
            );
            $groups->execute($ids);

            $restoreSponsors = $db->prepare(
                'UPDATE sponsors SET deleted_at = NULL WHERE group_id = ? AND deleted_at = ?'
            );
            foreach ($groups->fetchAll() as $group) {
                $restoreSponsors->execute([$group['id'], $group['deleted_at']]);
            }
        }

        $stmt = $db->prepare(
            "UPDATE {$config['table']} SET deleted_at = NULL
             WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
        );
        $stmt->execute($ids);
        $count = $stmt->rowCount();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return $count;
}

/**
 * Permanently removes trashed rows and their uploaded files. Only ever
 * touches rows already in Trash, so this can never bypass the soft-delete
 * step even if called with a live id.
 */
function purgeItems(string $type, array $ids): int
{
    $config = trashConfig($type, 'purgeItems');
    if ($config === null || $ids === []) {
        return 0;
    }

    $db = getDb();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $columns = array_merge([$config['labelColumn']], $config['fileColumns']);
    $select = $db->prepare(
        'SELECT ' . implode(', ', $columns) . " FROM {$config['table']}
         WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
    );
    $select->execute($ids);
    $rows = $select->fetchAll();

    if ($rows === []) {
        return 0;
    }

    // Files of child rows (a project's photo set), read before the delete
    // cascades them away. Only rows of items that really are in Trash.
    $childFiles = [];
    foreach ($config['childFiles'] ?? [] as $child) {
        try {
            $childStmt = $db->prepare(
                "SELECT c.{$child['column']} FROM {$child['table']} c
                 JOIN {$config['table']} p ON p.id = c.{$child['foreignKey']}
                 WHERE c.{$child['foreignKey']} IN ({$placeholders}) AND p.deleted_at IS NOT NULL"
            );
            $childStmt->execute($ids);
            array_push($childFiles, ...$childStmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            // Child table not created yet (migrate.php not run): nothing to remove.
        }
    }

    $db->beginTransaction();
    try {
        $delete = $db->prepare(
            "DELETE FROM {$config['table']} WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL"
        );
        $delete->execute($ids);
        $count = $delete->rowCount();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    // Files are unlinked only after the rows are definitely gone — the
    // reverse order would destroy the image of a row that then failed to
    // delete, leaving live content pointing at nothing.
    foreach ($rows as $row) {
        foreach ($config['fileColumns'] as $column) {
            deleteUploadedFile($row[$column] ?? null);
        }
    }
    foreach ($childFiles as $path) {
        deleteUploadedFile($path);
    }

    return $count;
}

/**
 * One page of Trash, newest first, as a flat list across all content types.
 *
 * Trash is the one listing in the admin that had no pagination: it fetched
 * every soft-deleted row from all seven tables, sorted the lot in PHP, and
 * rendered all of it. Fine at four items; a page that has to be scrolled past
 * to reach the pager it does not have once someone bulk-deletes a few hundred.
 *
 * The per-table LIMIT is what keeps the fetch bounded as well as the render:
 * the globally newest N rows can only come from each table's own newest N, so
 * asking each table for at most (offset + perPage) is enough to build a
 * correct page — without that, "page 1" still means reading every trashed row
 * in the database.
 *
 * @param int $offset  rows to skip (from listParams)
 * @param int $perPage rows per page; 0 means "everything", for callers that
 *                     genuinely need the full set
 * @return list<array>
 */
function trashedItems(int $offset = 0, int $perPage = 0): array
{
    $db = getDb();
    $items = [];

    // Cast locally so the value interpolated into SQL can only ever be digits.
    $offset = max(0, $offset);
    $perPage = max(0, $perPage);
    $perTableLimit = $perPage > 0 ? $offset + $perPage : 0;

    foreach (visibleTrashTypes() as $type => $config) {
        // Table and column names come from the registry, never from a request.
        $sql = "SELECT id, {$config['labelColumn']} AS label, deleted_at
                FROM {$config['table']}
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC";

        if ($perTableLimit > 0) {
            $sql .= ' LIMIT ' . (int) $perTableLimit;
        }

        foreach ($db->query($sql)->fetchAll() as $row) {
            $items[] = [
                'type' => $type,
                'typeLabel' => $config['label'],
                'editPath' => $config['editPath'],
                'id' => (int) $row['id'],
                'label' => $row['label'],
                'deleted_at' => $row['deleted_at'],
            ];
        }
    }

    usort($items, fn(array $a, array $b): int => strcmp($b['deleted_at'], $a['deleted_at']));

    return $perPage > 0 ? array_slice($items, $offset, $perPage) : $items;
}

/**
 * How many items are in Trash *for this user* — the sidebar badge and the
 * pager total both read it, and a badge counting rows the page will not list
 * is a badge that never reaches zero.
 */
function trashCount(): int
{
    $db = getDb();
    $total = 0;

    foreach (visibleTrashTypes() as $config) {
        $total += (int) $db->query(
            "SELECT COUNT(*) FROM {$config['table']} WHERE deleted_at IS NOT NULL"
        )->fetchColumn();
    }

    return $total;
}
