<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'language-labs',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Language Labs',
    // Before/after slider after the impact line.
    'before_after' => true,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Lab.svg',
    'image_alt' => 'Students in an In-School Language Lab in Chitral',
    'seo_title' => 'In-School Language Labs — Worldwide Education Foundation',
    'seo_description' => 'High-quality English language labs in low-income private schools across Chitral, strengthening students\' speaking and listening skills.',
    'fallback' => [
        'location' => 'Low-income Private Schools in Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2021',
        'image' => 'assets/images/programs/language-labs-01.jpg',
        'stats' => [
            ['value' => '5', 'label' => 'Language Labs'],
            ['value' => '1,250', 'label' => 'Students Enrolled'],
            ['value' => '100', 'label' => 'School Teachers'],
            ['value' => '5', 'label' => 'Facilitators'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
