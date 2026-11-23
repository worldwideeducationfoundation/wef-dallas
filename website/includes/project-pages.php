<?php
/**
 * Data for the shared project-page layout (includes/layout/project-page.php).
 *
 * The nine project pages used to be nine near-identical 150-line templates.
 * They are now a few lines of per-page settings each; everything here turns a
 * project's database row and page text into the pieces the layout draws:
 * the cover title, the two-tone headings, the alternating sections, and the
 * photo set.
 */

require_once __DIR__ . '/content-blocks.php';
require_once __DIR__ . '/content.php';

/**
 * Splits a heading around the words to show in colour.
 *
 * $wanted is the phrase to highlight when it occurs in $text (the project's
 * name in its page title); otherwise the last ~40% of the words are used,
 * the way "Bring Empowerment and Hope" colours its second half.
 *
 * @return array{lead: string, highlight: string, tail: string} plain text
 */
function splitHighlight(string $text, ?string $wanted = null): array
{
    $text = trim($text);
    if ($wanted !== null && $wanted !== '' && ($at = mb_stripos($text, $wanted)) !== false) {
        return [
            'lead' => mb_substr($text, 0, $at),
            'highlight' => mb_substr($text, $at, mb_strlen($wanted)),
            'tail' => mb_substr($text, $at + mb_strlen($wanted)),
        ];
    }

    $words = preg_split('/\s+/u', $text) ?: [];
    if (count($words) < 2) {
        return ['lead' => '', 'highlight' => $text, 'tail' => ''];
    }
    $take = max(1, (int) ceil(count($words) * 0.4));

    return [
        'lead' => implode(' ', array_slice($words, 0, -$take)) . ' ',
        'highlight' => implode(' ', array_slice($words, -$take)),
        'tail' => '',
    ];
}

/**
 * The page title as a big main line plus an optional smaller line.
 *
 * Titles in the CMS are often "Name: what it does" - "Learning Centers:
 * Integrating Early Childhood Development and ..." set at display size would
 * run to six lines. The half holding the project's name becomes the display
 * line and the other half a smaller line above it ("kicker") or below it
 * ("sub"), in the order the title reads.
 *
 * @return array{kicker: string, main: array{lead: string, highlight: string, tail: string}, sub: string}
 */
function projectTitleParts(string $title, string $highlight): array
{
    $kicker = '';
    $sub = '';
    $main = trim($title);

    if (str_contains($main, ':')) {
        [$first, $second] = array_map('trim', explode(':', $main, 2));
        if ($second !== '' && mb_stripos($second, $highlight) !== false && mb_stripos($first, $highlight) === false) {
            $kicker = $first . ':';
            $main = $second;
        } elseif ($second !== '') {
            // The colon stays: "Bright Beginnings:" is part of the name.
            $main = $first . ':';
            $sub = $second;
        }
    }

    return ['kicker' => $kicker, 'main' => splitHighlight($main, $highlight), 'sub' => $sub];
}

/**
 * Two-tone heading markup: the highlighted words in the project's gradient.
 * $swoosh adds the hand-drawn underline the page title carries.
 */
function highlightHtml(array $parts, bool $swoosh = false): string
{
    $underline = $swoosh
        ? '<svg class="pp-swoosh" viewBox="0 0 300 16" preserveAspectRatio="none" aria-hidden="true"><path d="M3 11 C 60 4, 120 3, 180 7 S 270 12, 297 6" /></svg>'
        : '';

    return e($parts['lead'])
        . '<span class="pp-hl">' . e($parts['highlight']) . $underline . '</span>'
        . e($parts['tail']);
}

/**
 * The small label above a section heading, chosen from what the heading is
 * about ("Current Impact" -> "Our Impact"). Anything unrecognised gets the
 * project's short name, which always reads correctly.
 */
function projectEyebrow(string $heading, string $fallback): string
{
    $map = [
        '/transform|before|after/i' => 'See the Change',
        '/impact|results|outcome/i' => 'Our Impact',
        '/vision|future|ahead|2030|2035/i' => 'Looking Ahead',
        '/who benefits|beneficiar|who we serve/i' => 'Who We Serve',
        '/^why\b/i' => 'The Need',
        '/what we do|approach|how/i' => 'Our Work',
        '/fund|support|donor/i' => 'Support',
        '/partner/i' => 'Together',
        '/objective|goal/i' => 'Our Goals',
        '/track|program|course|diploma/i' => 'Programs',
        '/deliver|model|hybrid/i' => 'How It Works',
        '/women|entrepreneur/i' => 'Empowering Women',
        '/community|parent/i' => 'Community',
        '/technolog|digital/i' => 'Technology',
        '/montessori|learning|classroom/i' => 'Learning',
        '/feature|ecosystem/i' => 'What Sets It Apart',
    ];
    foreach ($map as $pattern => $label) {
        if (preg_match($pattern, $heading)) {
            return $label;
        }
    }

    return $fallback;
}

/**
 * The page text cut into sections at its "## " subheadings.
 *
 * All of a page's body blocks run together first, so a heading that ends one
 * block and the paragraph that starts the next stay one section. The text
 * before the first subheading is the opening "About" section.
 *
 * @return list<array{eyebrow: string, heading: string, html: string}>
 */
function projectSections(string $slug, string $projectName, string $shortName): array
{
    $registry = blockRegistry()[$slug]['blocks'] ?? [];
    $text = '';
    foreach (['body_1', 'body_2', 'body_3'] as $key) {
        if (isset($registry[$key])) {
            $text .= str_replace("\r\n", "\n", (string) blockValue($slug, $key)) . "\n\n";
        }
    }

    $sections = [];
    $chunks = preg_split('/^## /m', $text) ?: [];
    $intro = trim((string) array_shift($chunks));
    if ($intro !== '') {
        // The cover already carries the project's name, so the opening section
        // is headed "About the Project", with the name as its label.
        $sections[] = ['eyebrow' => $shortName, 'heading' => 'About the Project', 'html' => renderBodyText($intro)];
    }

    foreach ($chunks as $chunk) {
        [$heading, $body] = array_pad(explode("\n", $chunk, 2), 2, '');
        $heading = trim($heading);
        $html = renderBodyText($body);
        if ($heading === '' && $html === '') {
            continue;
        }
        $sections[] = ['eyebrow' => projectEyebrow($heading, $shortName), 'heading' => $heading, 'html' => $html];
    }

    return $sections;
}

/**
 * A project's own photos, in the order the page shows them: the ones added on
 * its admin form (Our Active Projects -> Project photos), then any gallery
 * photos filed under it. The cover photo is not included; the hero shows it.
 *
 * @return list<array{src: string, caption: string}>
 */
function projectPhotos(?array $program): array
{
    $photos = [];
    $id = (int) ($program['id'] ?? 0);
    if ($id === 0) {
        return $photos;
    }

    try {
        $db = getDb();
        if ($db === null) {
            return $photos;
        }
        foreach ([
            'SELECT image_path, caption FROM program_photos WHERE program_id = :id ORDER BY display_order, id',
            "SELECT image_path, caption FROM gallery_items
              WHERE program_id = :id AND status = 'active' AND deleted_at IS NULL AND image_path IS NOT NULL
              ORDER BY display_order, caption",
        ] as $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $id]);
            foreach ($stmt->fetchAll() as $row) {
                $src = uploadUrl($row['image_path']);
                if ($src !== null && !in_array($src, array_column($photos, 'src'), true)) {
                    $photos[] = ['src' => $src, 'caption' => trim((string) ($row['caption'] ?? ''))];
                }
            }
        }
    } catch (Throwable $e) {
        // A database without program_photos yet (migrate.php not run): the
        // page still renders, with its cover photo and section panels.
    }

    return $photos;
}

/**
 * Photos for the carousel at the foot of the page: this project's own only -
 * the cover, the photos added on its admin form, its gallery photos. Never
 * another project's. With fewer than three, "More photos coming soon" slides
 * fill the row, so the carousel keeps its shape until more are added
 * (admin -> Our Active Projects -> edit -> Project photos).
 *
 * @return list<array{src: ?string, caption: string}> src null = placeholder
 */
function projectCarouselPhotos(?string $cover, array $own, string $projectName): array
{
    $slides = [];
    if ($cover !== null) {
        $slides[] = ['src' => $cover, 'caption' => $projectName];
    }
    foreach ($own as $photo) {
        $slides[] = ['src' => $photo['src'], 'caption' => $photo['caption'] !== '' ? $photo['caption'] : $projectName];
    }
    while (count($slides) < 3) {
        $slides[] = ['src' => null, 'caption' => 'More photos coming soon'];
    }

    return $slides;
}

/**
 * The project's video: the active Video Gallery entry (admin -> Video
 * Gallery) whose title is the project's name. Adding a video for any
 * project is just adding it there under that name.
 *
 * @return array{id: string, title: string}|null YouTube id
 */
function projectVideo(string $projectName): ?array
{
    try {
        $db = getDb();
        if ($db === null) {
            return null;
        }
        $stmt = $db->prepare(
            "SELECT title, youtube_url FROM video_gallery
              WHERE status = 'active' AND deleted_at IS NULL AND LOWER(TRIM(title)) = LOWER(:name)
              ORDER BY display_order, id LIMIT 1"
        );
        $stmt->execute(['name' => trim($projectName)]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$row || !preg_match('#(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', (string) $row['youtube_url'], $m)) {
        return null;
    }

    return ['id' => $m[1], 'title' => (string) $row['title']];
}
