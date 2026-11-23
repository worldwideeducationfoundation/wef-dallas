<?php
/**
 * Image specifications per upload field — one place, used for three
 * things at once so they can never drift apart:
 *
 *   1. The guidance text shown under the file input.
 *   2. The crop box's fixed aspect ratio in the cropper.
 *   3. The output size the cropped file is rendered at.
 *
 * Before this, an admin uploading a team photo had no way to know what
 * shape or size was expected, so portraits arrived at wildly different
 * dimensions and the public site letterboxed them inconsistently.
 *
 * The dimensions are the *rendered* target. Uploading something larger
 * is fine and preferable — the cropper downscales to these values, which
 * also cuts the stored file size substantially.
 */

require_once __DIR__ . '/upload.php';

/**
 * The headings the public our-team page groups people under. A plain
 * allow-list rather than a DB enum, so adding a section is a one-line
 * change here and does not need a migration — but it is still validated
 * on save, so the column can never hold a heading the page won't render.
 */
const TEAM_SECTIONS = [
    'Advisory Board Members',
    'Chitral Team',
    'Collaborating Partners',
];

const IMAGE_SPECS = [
    'programs' => [
        'label' => 'Program photo',
        'width' => 1200,
        'height' => 800,
        'ratio' => '3:2',
        'note' => 'Used as the project card image and the program page banner. Landscape.',
    ],
    'page-header' => [
        'label' => 'Page header image',
        'width' => 1920,
        'height' => 640,
        'ratio' => '3:1',
        'note' => 'Shown behind the green banner at the top of the page, under a green tint so the white title stays readable. Wide and fairly even photos work best; the middle is kept when the banner is cropped on phones.',
    ],
    'purpose-background' => [
        'label' => 'Core Purpose background',
        'width' => 1920,
        'height' => 560,
        'ratio' => '24:7',
        'note' => 'Shown behind the "Our Core Purpose" banner on the home page, under a green tint so the white headline stays readable. A calm, wide photo works best; the middle is kept on phones.',
    ],
    'hero-slides' => [
        'label' => 'Slideshow photo',
        'width' => 1920,
        'height' => 1080,
        'ratio' => '16:9',
        'note' => 'Fills the whole first screen of the home page behind the headline, which sits on the left - keep the subject centre or right.',
    ],
    'team' => [
        'label' => 'Team photo',
        'width' => 600,
        'height' => 600,
        'ratio' => '1:1',
        'note' => 'Shown as a circular portrait. Centre the face — the corners get cropped off by the circle.',
    ],
    'gallery' => [
        'label' => 'Gallery photo',
        'width' => 1200,
        'height' => 800,
        'ratio' => '3:2',
        'note' => 'Shown in the gallery grid and the lightbox. Landscape.',
    ],
    'reports' => [
        'label' => 'Report cover',
        'width' => 800,
        'height' => 1035,
        'ratio' => '≈A4 portrait',
        'note' => 'The cover thumbnail of the PDF. Portrait, roughly A4 proportions.',
    ],
    'completed-projects' => [
        'label' => 'Project photo',
        'width' => 1200,
        'height' => 800,
        'ratio' => '3:2',
        'note' => 'Used as the listing card image, the detail page banner, and each gallery photo. Landscape.',
    ],
    'completed-projects-map' => [
        'label' => 'Map image',
        'width' => 1200,
        'height' => 900,
        'ratio' => '4:3',
        'note' => 'A static map screenshot shown beside the project gallery, not an interactive map.',
    ],
];

/**
 * Guidance line under a file input: what to upload and what the limits
 * are. Reads the size cap straight from includes/upload.php so the number
 * shown to the user is always the number the server actually enforces.
 */
function imageSpecHelp(string $key): string
{
    $spec = IMAGE_SPECS[$key] ?? null;
    if ($spec === null) {
        return '';
    }

    $maxMb = (int) (UPLOAD_IMAGE_MAX_BYTES / (1024 * 1024));

    return '<p class="field-help">'
        . '<strong>' . $spec['width'] . ' × ' . $spec['height'] . ' px</strong> (' . e($spec['ratio']) . ') · '
        . 'JPG, PNG or WebP · max ' . $maxMb . 'MB<br>'
        . e($spec['note']) . ' Larger images are fine — you can crop and resize after choosing a file.'
        . '</p>';
}

/**
 * The data-* attributes the cropper reads off a file input. Kept next to
 * the help text so a field can never advertise one size and crop to
 * another.
 */
function imageSpecAttributes(string $key): string
{
    $spec = IMAGE_SPECS[$key] ?? null;
    if ($spec === null) {
        return '';
    }

    return sprintf(
        ' data-crop-width="%d" data-crop-height="%d" data-crop-label="%s"',
        $spec['width'],
        $spec['height'],
        e($spec['label'])
    );
}

/**
 * Guidance for the PDF field, which has its own (larger) size cap.
 */
function documentSpecHelp(): string
{
    $maxMb = (int) (UPLOAD_DOCUMENT_MAX_BYTES / (1024 * 1024));

    return '<p class="field-help">PDF only · max ' . $maxMb . 'MB. '
        . 'This is the file visitors download, so keep it under ~10MB where possible.</p>';
}
