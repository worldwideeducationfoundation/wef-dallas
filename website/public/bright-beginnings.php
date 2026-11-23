<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'bright-beginnings',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Bright Beginnings',
    // Before/after slider after the impact line.
    'before_after' => true,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Students.svg',
    'image_alt' => 'Bright Beginnings classroom in Chitral',
    'seo_title' => 'Bright Beginnings: Early Childhood Learning Centers — Worldwide Education Foundation',
    'seo_description' => 'Bright Beginnings Early Childhood Learning Centers bring Montessori-based ECD to Chitral, Pakistan, led by trained women entrepreneurs.',
    'fallback' => [
        'location' => 'Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2025',
        'image' => 'assets/images/programs/bright-beginnings-01.webp',
        'stats' => [
            ['value' => '100+', 'label' => 'Centers launched across Chitral'],
            ['value' => '3,000', 'label' => 'Students'],
            ['value' => '200', 'label' => 'Women educators trained & leading'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
