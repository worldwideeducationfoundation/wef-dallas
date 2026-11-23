<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'rising-stars',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Rising Stars',
    'before_after' => false,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Scholarship.svg',
    'image_alt' => 'AM Rising Stars Undergraduate Scholarship recipients at AUCA and UCA',
    'seo_title' => 'AM Rising Stars Undergraduate Scholarship — Worldwide Education Foundation',
    'seo_description' => 'Removing financial barriers to higher education for talented students from Tajikistan and Kyrgyzstan, funded by Sadiq Ali & Family.',
    'fallback' => [
        'location' => 'Bishkek, Kyrgyzstan & Khorog, Tajikistan',
        'badge' => 'Active Since 2023',
        'image' => 'assets/images/programs/rising-stars-01.jpg',
        'stats' => [
            ['value' => '52', 'label' => 'Recipients at AUCA'],
            ['value' => '14', 'label' => 'Recipients at UCA'],
            ['value' => '66', 'label' => 'Total Students Supported'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
