<?php
/**
 * Read-only content layer for the public site.
 *
 * The CMS in admin/ has been writing to these tables for a while, but no
 * public page ever read from them — every visitor-facing page was static
 * markup with the same content copied into it by hand. This is the read
 * side, and it is deliberately narrow:
 *
 *   - SELECT only. There is no function here that writes, and public
 *     pages never include anything from admin/, so a bug on a public page
 *     cannot modify content or touch the admin session.
 *   - Published only. Every query filters status = 'active', so draft
 *     work in the CMS stays invisible until it is deliberately published.
 *   - Escaping is the caller's job, via e() below. Nothing here returns
 *     pre-rendered HTML.
 *
 * For extra safety in production, point DB_USER at a MySQL account with
 * only SELECT on this database and give the admin app its own account —
 * the code is already structured so the public side needs nothing more.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/assets.php';

/**
 * HTML-escape. Mirrors the admin's helper of the same name; guarded so
 * the two can never collide if a page ever pulled in both.
 */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Bios and descriptions are stored as plain text with newlines — checked
 * the real rows, none contain markup. Escaping first and converting
 * newlines afterwards keeps paragraph breaks without ever letting stored
 * text become live HTML.
 */
function richText(?string $value): string
{
    return nl2br(e($value), false);
}

/**
 * Public URL for a file uploaded through the CMS. Uploads live under
 * admin/uploads/, which Apache serves as static files while refusing to
 * execute anything there (see admin/uploads/.htaccess).
 */
function uploadUrl(?string $relativePath): ?string
{
    if (!$relativePath) {
        return null;
    }

    return 'admin/uploads/' . $relativePath;
}

/**
 * Returns $url unchanged if it's a youtube.com/youtube-nocookie.com link,
 * null otherwise. Used everywhere a stored URL is about to be built into an
 * <iframe src> — completed_projects.video_url and video_gallery.youtube_url
 * are both validated to this same allow-list on save (see
 * admin/modules/completed-projects/save.php and
 * admin/modules/video-gallery/save.php), but templates check again here
 * rather than trusting the stored value outright, the same defence-in-depth
 * every other user-supplied URL in this codebase gets.
 */
function safeYoutubeUrl(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $allowed = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];

    return in_array($host, $allowed, true) ? $url : null;
}

/**
 * Filters a static-data fallback array the same way every live DB query in
 * this file already does: status='active' and deleted_at IS NULL.
 *
 * Added 2026-09-23 after finding that NONE of the six publishedXxx()
 * fallback paths applied this filter - they returned getStaticData(...)
 * as-is. static-data.php is a point-in-time export of the real tables, so
 * a row that was draft or soft-deleted AT EXPORT TIME (one program row was
 * both: garbage test content, 'status' => 'active', but 'deleted_at' set)
 * would still render publicly on any request that fell back to static
 * data, on any page, indefinitely - undoing a content editor's delete
 * without them ever knowing the fallback path existed.
 *
 * @param list<array> $rows
 * @return list<array>
 */
function publishedOnly(array $rows): array
{
    return array_values(array_filter(
        $rows,
        static fn(array $row): bool => ($row['status'] ?? 'active') === 'active'
            && ($row['deleted_at'] ?? null) === null
    ));
}

/**
 * Fallback static data helper when database is disabled or offline.
 */
function getStaticData(string $key): array
{
    static $staticData = null;
    if ($staticData === null) {
        $file = __DIR__ . '/static-data.php';
        $staticData = is_file($file) ? require $file : [];
    }
    return $staticData[$key] ?? [];
}

/**
 * Team members grouped by their section heading, each group in display
 * order. Returns an ordered map of section => list of members, with the
 * sections in the order given, so the page renders headings in a fixed
 * sequence rather than whatever the data happens to yield.
 *
 * @param list<string> $sectionOrder
 * @return array<string, list<array>>
 */
function publishedTeamBySection(array $sectionOrder): array
{
    $rows = null;
    try {
        $db = getDb();
        if ($db !== null) {
            $stmt = $db->query(
                "SELECT name, role, section, bio, image_path
                 FROM team_members
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, name"
            );
            $rows = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        $rows = null;
    }

    if (empty($rows)) {
        $rows = publishedOnly(getStaticData('team_members'));
    }

    $grouped = array_fill_keys($sectionOrder, []);

    foreach ($rows as $member) {
        $section = $member['section'] ?? '';
        // A section that isn't in the requested order is skipped rather
        // than appended, so renaming a heading in the CMS can't silently
        // add an unstyled block to the page.
        if (!array_key_exists($section, $grouped)) {
            continue;
        }
        $grouped[$section][] = $member;
    }

    return array_filter($grouped, fn(array $members): bool => $members !== []);
}

/**
 * @return list<array>
 */
function publishedGalleryItems(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            // project_name: the project the photo is filed under (admin →
            // Gallery → Project); NULL for a general photo, or when that
            // project is unpublished or trashed.
            $rows = $db->query(
                "SELECT g.caption, g.image_path, p.name AS project_name
                 FROM gallery_items g
                 LEFT JOIN programs p
                   ON p.id = g.program_id AND p.status = 'active' AND p.deleted_at IS NULL
                 WHERE g.status = 'active' AND g.deleted_at IS NULL AND g.image_path IS NOT NULL
                 ORDER BY g.display_order, g.caption"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('gallery_items'));
}

/**
 * Every photo the Gallery page shows, each with the project it belongs to.
 *
 * The CMS gallery photos, followed by the cover photo (admin → Our Active
 * Projects) of each published project that has no gallery photo yet, so
 * every project appears under its name. A project with gallery photos of
 * its own does not also get its cover: the covers are often the same
 * picture uploaded twice, and the gallery showed it twice side by side.
 *
 * @return list<array{caption: string, image_path: string, project: string}>
 */
function galleryPhotos(): array
{
    $photos = [];
    $seen = [];
    $projectsWithPhotos = [];

    foreach (publishedGalleryItems() as $item) {
        $project = trim((string) ($item['project_name'] ?? '')) ?: 'General';
        $photos[] = [
            'caption' => (string) $item['caption'],
            'image_path' => (string) $item['image_path'],
            'project' => $project,
        ];
        $seen[$item['image_path']] = true;
        $projectsWithPhotos[$project] = true;
    }

    foreach (publishedPrograms() as $program) {
        $path = (string) ($program['image_path'] ?? '');
        $name = (string) $program['name'];
        if ($path === '' || isset($seen[$path]) || isset($projectsWithPhotos[$name])) {
            continue;
        }
        $photos[] = ['caption' => $name, 'image_path' => $path, 'project' => $name];
        $seen[$path] = true;
    }

    // Grouped by project in the order of WEF at a Glance (Our Mission page,
    // admin -> Mission Projects): Bright Beginnings first. A project not in
    // that table follows, in the order it first appears; "General" photos
    // come last. Within a project the photos keep their admin order.
    $rank = [];
    foreach (publishedMissionProjects() as $i => $row) {
        $rank[trim((string) $row['what_header'])] = $i;
    }
    $firstSeen = [];
    foreach ($photos as $i => $photo) {
        $firstSeen[$photo['project']] ??= $i;
    }
    $key = function (array $photo) use ($rank, $firstSeen): array {
        if ($photo['project'] === 'General') {
            return [2, 0];
        }
        return isset($rank[$photo['project']]) ? [0, $rank[$photo['project']]] : [1, $firstSeen[$photo['project']]];
    };
    $indexed = [];
    foreach ($photos as $i => $photo) {
        $indexed[] = [$key($photo), $i, $photo];
    }
    usort($indexed, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

    return array_column($indexed, 2);
}

/**
 * @return list<array>
 */
function publishedReports(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT title, description, cover_image_path, pdf_path
                 FROM annual_reports
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, title DESC"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('annual_reports'));
}

/**
 * @return list<array>
 */
function publishedPrograms(?string $category = null): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $sql = "SELECT slug, category, name, summary, description, badge_text, location_text,
                           year_launched,
                           stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label,
                           image_path
                    FROM programs
                    WHERE status = 'active' AND deleted_at IS NULL";

            if ($category !== null) {
                $sql .= ' AND category = :category';
            }

            $sql .= ' ORDER BY category, display_order, name';

            $stmt = $db->prepare($sql);
            $stmt->execute($category !== null ? ['category' => $category] : []);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    $all = publishedOnly(getStaticData('programs'));
    if ($category !== null) {
        $all = array_values(array_filter($all, fn($p) => ($p['category'] ?? '') === $category));
    }
    // Mirrors the DB query's `ORDER BY category, display_order, name` exactly,
    // rather than trusting static-data.php's own array order - that order can
    // drift out of sync the moment someone reorders rows in the CMS (or, as
    // happened once, in a data-only edit to this file) without also
    // physically re-sorting the fallback array by hand.
    static $categoryRank = ['early_childhood' => 0, 'programs' => 1, 'scholarships' => 2];
    usort($all, function (array $a, array $b) use ($categoryRank): int {
        $rankA = $categoryRank[$a['category'] ?? ''] ?? 99;
        $rankB = $categoryRank[$b['category'] ?? ''] ?? 99;
        return $rankA <=> $rankB
            ?: ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0)
            ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    return $all;
}

/**
 * One program by its slug, for the 9 program detail pages - each of which
 * previously hardcoded its own location/badge/stat tiles/hero image
 * directly in markup rather than reading the programs table row it already
 * duplicates. Returns null if no published row matches, so a caller can
 * fall back to its own hardcoded defaults rather than rendering blanks.
 */
function publishedProgramBySlug(string $slug): ?array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $stmt = $db->prepare(
                "SELECT id, slug, category, name, summary, description, badge_text, location_text,
                        year_launched,
                        stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label,
                        image_path, before_image_path, after_image_path
                 FROM programs
                 WHERE slug = :slug AND status = 'active' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    foreach (publishedOnly(getStaticData('programs')) as $program) {
        if (($program['slug'] ?? null) === $slug) {
            return $program;
        }
    }

    return null;
}

/**
 * The before/after comparison slider for a project page, or '' when the
 * project has no before/after pair (admin → Our Active Projects → Before &
 * after). Built into a slider by initCompareSlider() in js/main.js.
 */
function programCompareSlider(?array $program, bool $placeholders = false): string
{
    $before = uploadUrl($program['before_image_path'] ?? null);
    $after = uploadUrl($program['after_image_path'] ?? null);

    // $placeholders: the project pages always show the slider, and a side
    // without its photo yet shows a "photo coming soon" card instead.
    if ($placeholders) {
        $before ??= 'assets/images/placeholders/before-coming-soon.svg';
        $after ??= 'assets/images/placeholders/after-coming-soon.svg';
    }
    if ($before === null || $after === null) {
        return '';
    }

    $name = (string) ($program['name'] ?? '');

    return '<div class="compare-slider"'
        . ' data-before="' . e($before) . '" data-before-label="Before" data-before-alt="' . e(trim($name . ' before WEF')) . '"'
        . ' data-after="' . e($after) . '" data-after-label="After" data-after-alt="' . e(trim($name . ' after WEF')) . '"></div>';
}

/**
 * Icon for a program stat tile, chosen from the stat's label.
 *
 * The projects table stores stat label/value pairs but no icon, because
 * the hand-written cards picked an icon per label by eye. This map
 * reproduces exactly the pairings that were on programs.php, so the cards
 * render identically — and an unrecognised label falls back to a neutral
 * icon rather than a broken image.
 */
function statIcon(?string $label): string
{
    $map = [
        'Location' => 'Location',
        'Students' => 'Students',
        'Children' => 'Students',
        'Trained' => 'Students',
        'Centers' => 'Schools',
        'Schools' => 'Schools',
        'Labs' => 'Lab',
        'Focus' => 'Research',
        'Recipients' => 'Scholarship',
        'Invested' => 'Investement',
        '& Job Seekers' => 'Youth',
        'Serving' => 'Youth',
        'By 2030' => 'Diploma',
        'Programs' => 'Diploma',
    ];

    $icon = $map[trim((string) $label)] ?? 'Diploma';

    return 'assets/icons/SVG/Cart%20Icons/' . $icon . '.svg';
}

/**
 * Sponsor groups, each with its published sponsors attached. Two queries
 * rather than a join-and-regroup, because the sponsor list is small and
 * this keeps the shape obvious at the call site.
 *
 * @return list<array>
 */
function publishedSponsorGroups(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $groups = $db->query(
                "SELECT id, title, description
                 FROM sponsor_groups
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, title"
            )->fetchAll();

            if ($groups !== []) {
                $sponsors = $db->query(
                    "SELECT group_id, name, badge_text
                     FROM sponsors
                     WHERE status = 'active' AND deleted_at IS NULL
                     ORDER BY display_order, name"
                )->fetchAll();

                $byGroup = [];
                foreach ($sponsors as $sponsor) {
                    $byGroup[$sponsor['group_id']][] = $sponsor;
                }

                foreach ($groups as $index => $group) {
                    $groups[$index]['sponsors'] = $byGroup[$group['id']] ?? [];
                }

                return $groups;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    $groups = publishedOnly(getStaticData('sponsor_groups'));
    $sponsors = publishedOnly(getStaticData('sponsors'));
    $byGroup = [];
    foreach ($sponsors as $sponsor) {
        $byGroup[$sponsor['group_id']][] = $sponsor;
    }
    foreach ($groups as $index => $group) {
        $groups[$index]['sponsors'] = $byGroup[$group['id']] ?? [];
    }
    return $groups;
}

/**
 * Published project updates, newest-first by display order.
 *
 * This is the reader the Project Updates module never had. The module has
 * shipped with create, edit, drag-to-reorder, bulk actions and Trash since it
 * was built, writing to a table nothing read — the home page rendered nine
 * hardcoded cards instead. Adding an update in the CMS changed nothing a
 * visitor saw.
 *
 * tag and link_url were added to the table when those nine cards were imported
 * (admin/database/import-hardcoded-content.php); without them the category
 * chip and the READ MORE destination would have been lost in the move.
 *
 * @param int $limit 0 for all — the home page shows a fixed number
 * @return list<array>
 */
function publishedUpdates(int $limit = 0): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $sql = "SELECT title, slug, content, tag, link_url, image_path
                      FROM project_updates
                     WHERE status = 'active' AND deleted_at IS NULL
                     ORDER BY display_order, title";

            if ($limit > 0) {
                // Cast, not bound: MySQL rejects a placeholder in LIMIT when emulated
                // prepares are off, which they are — see includes/database.php.
                $sql .= ' LIMIT ' . (int) $limit;
            }

            $rows = $db->query($sql)->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    $rows = publishedOnly(getStaticData('project_updates'));
    if ($limit > 0) {
        return array_slice($rows, 0, $limit);
    }
    return $rows;
}

/**
 * Completed projects, newest-first by display order.
 *
 * Falls back to includes/completed-projects-data.php - a 16-entry PHP array
 * that was, until 2026-09-23, the ONLY source for this content (not a
 * fallback for a real table, because no real table existed). It plays the
 * same fallback role static-data.php plays for every other content type now,
 * rather than being duplicated into that file.
 *
 * require_once is here rather than at the top of this file because the two
 * pages that need completed-projects data are two pages out of dozens this
 * file is included on - no reason to parse a 37 KB array on every request.
 *
 * @return list<array>
 */
function publishedCompletedProjects(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT slug, title, summary, description, location, completion_year, category,
                        image_path, secondary_image_path, tertiary_image_path, map_image_path, video_url,
                        stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label
                 FROM completed_projects
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, title"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    require_once __DIR__ . '/completed-projects-data.php';
    return getCompletedProjects();
}

/**
 * One completed project by its slug, for completed-project-details.php.
 * Returns null if no published row matches.
 */
function publishedCompletedProjectBySlug(string $slug): ?array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $stmt = $db->prepare(
                "SELECT slug, title, summary, description, location, completion_year, category,
                        image_path, secondary_image_path, tertiary_image_path, map_image_path, video_url,
                        stat1_value, stat1_label, stat2_value, stat2_label, stat3_value, stat3_label
                 FROM completed_projects
                 WHERE slug = :slug AND status = 'active' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    require_once __DIR__ . '/completed-projects-data.php';
    return getCompletedProjectBySlug($slug);
}

/**
 * Home page slideshow slides, in display order (admin: Home Slideshow).
 *
 * Every row carries image_src, the public-relative URL to render: database
 * rows point into admin/uploads/, the built-in fallback (the 9 slides that
 * were hardcoded in index.php) at the bundled assets/ copies. Active rows
 * without a photo are skipped - save.php refuses them, but a photo file can
 * still go missing on disk, and a slide with no picture is a dark box.
 */
function publishedHeroSlides(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT id, title_lead, title_highlight, description, image_path, image_alt
                 FROM hero_slides
                 WHERE status = 'active' AND deleted_at IS NULL AND image_path IS NOT NULL
                 ORDER BY display_order, id"
            )->fetchAll();
            if (!empty($rows)) {
                return array_map(static function (array $row): array {
                    $row['image_src'] = uploadUrl($row['image_path']);
                    return $row;
                }, $rows);
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('hero_slides'));
}

/**
 * "Our Partners" logos on the home page, in display order (admin: Partner
 * Logos). Same image_src arrangement as publishedHeroSlides(): logo_src is
 * the URL to render, from admin/uploads/ or the bundled fallback set.
 */
function publishedPartners(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT id, name, logo_path, link_url
                 FROM partners
                 WHERE status = 'active' AND deleted_at IS NULL AND logo_path IS NOT NULL
                 ORDER BY display_order, id"
            )->fetchAll();
            if (!empty($rows)) {
                return array_map(static function (array $row): array {
                    $row['logo_src'] = uploadUrl($row['logo_path']);
                    return $row;
                }, $rows);
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('partners'));
}

/**
 * Success story cards, in display order.
 *
 * Falls back to the same 6 entries getStaticData('success_stories') holds —
 * the exact cards that used to be hardcoded on success-stories.php, kept
 * verbatim (including the placeholder-quality title casing/punctuation and
 * the repeated titles) rather than invented or "cleaned up", since this
 * function's job is to read the data, not to improve it.
 */
function publishedSuccessStories(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT id, title, tag, story_date, image_path, link_url
                 FROM success_stories
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, title"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('success_stories'));
}

/**
 * Videos for video-gallery.php and index.php's "Featured Videos" section —
 * both read this same function now, rather than each hardcoding its own
 * copy of the same 2 embeds (see the "WEF Beginnings" section of index.php).
 *
 * @param int $limit 0 for all rows, otherwise the first $limit in display order
 */
function publishedVideos(int $limit = 0): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $sql = "SELECT id, title, youtube_url
                      FROM video_gallery
                     WHERE status = 'active' AND deleted_at IS NULL
                     ORDER BY display_order, title";

            if ($limit > 0) {
                $sql .= ' LIMIT ' . (int) $limit;
            }

            $rows = $db->query($sql)->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    $rows = publishedOnly(getStaticData('video_gallery'));
    if ($limit > 0) {
        return array_slice($rows, 0, $limit);
    }
    return $rows;
}

/**
 * The 9-row "WEF At A Glance" table on our-mission.php.
 */
function publishedMissionProjects(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT id, what_header, what_sub, launch_year, where_text, who_text,
                        impact_text, notes_text, notes_highlight
                 FROM mission_projects
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, what_header"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('mission_projects'));
}

/**
 * The 5-point roadmap chart on impact-goals.php. Ordered by display_order,
 * same as every other listing — the template pairs these positionally with
 * its own fixed 5-slot SVG geometry (see the comment on this table in
 * schema.sql for why the chart's layout isn't stored here too).
 */
function publishedImpactMilestones(): array
{
    try {
        $db = getDb();
        if ($db !== null) {
            $rows = $db->query(
                "SELECT id, milestone_date, centers_target, students_target, teachers_target
                 FROM impact_milestones
                 WHERE status = 'active' AND deleted_at IS NULL
                 ORDER BY display_order, milestone_date"
            )->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    return publishedOnly(getStaticData('impact_milestones'));
}

/**
 * Renders one mission_projects bullet column (where_text / who_text /
 * impact_text / notes_text) as the same <ul class="pdf-ul-list"><li>...
 * markup our-mission.php's table already used when it was hardcoded — one
 * bullet per newline-separated line. Not blockBody(): that parser also
 * handles paragraphs and ## headings this table never uses, and would be
 * the wrong tool for a plain bullet list.
 */
function missionBulletList(?string $text): string
{
    $lines = array_filter(array_map('trim', explode("\n", (string) $text)), fn(string $l): bool => $l !== '');
    if (empty($lines)) {
        return '';
    }

    $items = array_map(fn(string $l): string => '<li>' . e($l) . '</li>', $lines);

    return '<ul class="pdf-ul-list">' . implode('', $items) . '</ul>';
}

/**
 * Internal pages whose green banner can show a photo (admin: Page Content →
 * the page → Header image). Keys are the Page Content keys. The nine program
 * pages are not listed: their banners already carry the program's photo.
 */
const PAGE_HEADER_IMAGE_PAGES = [
    'programs', 'completed-projects', 'our-team', 'gallery',
    'video-gallery', 'success-stories', 'annual-reports', 'contact', 'volunteer',
    // Home: the image goes behind the "Our Core Purpose" banner, not the
    // hero (the hero has its own slideshow, admin: Home Slideshow).
    'home',
];

/**
 * Public URL of a page's header image, or null when none is set (or the
 * table does not exist yet, or the file has gone missing).
 */
function pageHeaderImage(string $page): ?string
{
    static $images = null;

    if (!in_array($page, PAGE_HEADER_IMAGE_PAGES, true)) {
        return null;
    }

    if ($images === null) {
        $images = [];
        try {
            $db = getDb();
            if ($db !== null) {
                $images = $db->query('SELECT page_key, header_image FROM pages WHERE header_image IS NOT NULL')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
            }
        } catch (Throwable $e) {
            $images = [];
        }
    }

    $path = (string) ($images[$page] ?? '');
    // Upload paths are "folder/hex.ext" (admin/includes/upload.php). Anything
    // else is refused, since the value is printed inside a CSS url().
    if (!preg_match('#^[a-z0-9_-]+/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$#', $path)) {
        return null;
    }
    if (!is_file(__DIR__ . '/../public/admin/uploads/' . $path)) {
        return null;
    }

    return uploadUrl($path);
}

/**
 * Attributes and layers for an internal page's banner section.
 *
 * With a header image the banner is three stacked layers: the photo
 * (.page-hero__bg, fed through the --bg-image custom property), the green
 * brand gradient (.page-hero__overlay) and the existing .container with the
 * text. Without one it returns the section exactly as before.
 *
 * @param string $existingStyle inline style the section already carries
 * @return array{class: string, style: string, layers: string}
 */
function pageHero(string $page, string $existingStyle = ''): array
{
    $image = pageHeaderImage($page);
    $style = trim($existingStyle);

    if ($image === null) {
        return [
            'class' => '',
            'style' => $style !== '' ? ' style="' . e($style) . '"' : '',
            'layers' => '',
        ];
    }

    // Root-relative, not page-relative: a url() inside a custom property is
    // resolved where the property is *used* - styles.css in /css/ - so a bare
    // "admin/uploads/..." became /css/admin/uploads/... and never loaded.
    $base = siteBasePath();

    // Size variants written at upload (name-768w.ext ... name-1600w.ext, see
    // includes/image-derivatives.php): phones get the 768px one and larger
    // screens at most 1600px, instead of the original - a banner photo can
    // be a 4,000px, 1.5 MB camera file. Without variants, the original.
    $variant = static function (array $widths) use ($image): ?string {
        $dir = dirname($image);
        $ext = pathinfo($image, PATHINFO_EXTENSION);
        $name = pathinfo($image, PATHINFO_FILENAME);
        foreach ($widths as $w) {
            $candidate = "{$dir}/{$name}-{$w}w.{$ext}";
            if (is_file(__DIR__ . '/../public/' . $candidate)) {
                return $candidate;
            }
        }
        return null;
    };
    $large = $variant([1600, 1200]) ?? $image;
    $small = $variant([768, 1200]) ?? $large;

    $style = trim($style . " --bg-image: url('" . $base . $large . "'); --bg-image-sm: url('" . $base . $small . "');");

    return [
        'class' => ' has-bg-image',
        'style' => ' style="' . e($style) . '"',
        'layers' => '<div class="page-hero__bg" aria-hidden="true"></div>'
            . '<div class="page-hero__overlay" aria-hidden="true"></div>',
    ];
}

/**
 * The checkout the Donate page uses (Settings → Donate link). The header and
 * home Donate buttons link to the /donate page, not to this. Falls back to
 * the Donorbox Bright Beginnings campaign, so an empty field or a missing
 * settings row never leaves a dead button. A donorbox.org campaign link is
 * embedded on the Donate page; any other link is opened. Only http(s)
 * links are accepted here as well as on save - this lands in an href.
 */
function donateUrl(): string
{
    $url = trim((string) (siteSettings()['donate_url'] ?? ''));
    if ($url !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    // Donorbox, the Bright Beginnings campaign (donate.php embeds its form).
    return 'https://donorbox.org/bright-beginnings-972649';
}

/**
 * The single site_settings row, or an empty array if it doesn't exist
 * yet — callers use null-coalescing on individual keys, so a missing row
 * degrades to "no contact details shown" rather than a fatal error.
 */
function siteSettings(): array
{
    static $settings = null;

    if ($settings === null) {
        $row = null;
        try {
            $db = getDb();
            if ($db !== null) {
                $row = $db->query('SELECT * FROM site_settings WHERE id = 1')->fetch();
            }
        } catch (Throwable $e) {
            $row = null;
        }

        if (!$row) {
            $staticSettings = getStaticData('site_settings');
            $row = $staticSettings[0] ?? [
                'contact_address' => 'W159N4940 Graysland Drive, Menomonee Falls, WI 53051 United States',
                'contact_phone' => '+1 (214) 502-8388',
                'contact_email' => 'info@wefdallas.org',
                'facebook_url' => 'https://www.facebook.com/WorldwideEducationFoundationWEF',
                'twitter_url' => 'https://x.com/WEFglobaledu',
                'instagram_url' => 'https://www.instagram.com/worldwideeducationfoundation/',
                'linkedin_url' => 'https://www.linkedin.com/company/worldwide-education-foundation',
                'youtube_url' => 'https://www.youtube.com/@wefdallasorg',
            ];
        }

        $settings = $row ?: [];
    }

    return $settings;
}

