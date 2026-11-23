<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'secondary-scholarships',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Secondary School Scholarships',
    'before_after' => false,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Scholarship.svg',
    'image_alt' => 'Secondary School Scholarship recipients in Upper Chitral',
    'seo_title' => 'Secondary School Scholarships — Worldwide Education Foundation',
    'seo_description' => 'Investing in Young Futures: removing financial barriers for underserved students in Upper Chitral to continue their education beyond Grade 8.',
    'fallback' => [
        'location' => 'Chitral Upper, Khyber-Pakhtunkhwa, Pakistan.',
        'badge' => 'Active Since 2025',
        'image' => 'assets/images/programs/secondary-scholarships-01.jpg',
        'stats' => [
            ['value' => '115', 'label' => 'Students Supported'],
            ['value' => '5', 'label' => 'Private Schools'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
