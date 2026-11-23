<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'model-schools',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Model Schools',
    // Before/after slider after the impact line.
    'before_after' => true,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Schools.svg',
    'image_alt' => 'Renovated Model School classroom in Upper Chitral',
    'seo_title' => 'Government Model Schools — Worldwide Education Foundation',
    'seo_description' => 'Transforming government primary school classrooms in Upper Chitral into welcoming, developmentally appropriate learning spaces.',
    'fallback' => [
        'location' => 'Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2025',
        'image' => 'assets/images/programs/model-schools-01.jpg',
        'stats' => [
            ['value' => '10', 'label' => 'Government Primary Schools Renovated'],
            ['value' => '600', 'label' => 'More Model Schools by 2035'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
