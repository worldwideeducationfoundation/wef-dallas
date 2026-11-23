<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'skills-development',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Skills, Innovation and Entrepreneurship',
    // Before/after slider after the impact line.
    'before_after' => true,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Diploma.svg',
    'image_alt' => 'Herzing Center for Skills, Innovation and Entrepreneurship at University of Chitral',
    'seo_title' => 'Herzing Center for Skills, Innovation and Entrepreneurship — Worldwide Education Foundation',
    'seo_description' => 'An integrated platform for professional development, teacher training, and workforce readiness hosted at the University of Chitral.',
    'fallback' => [
        'location' => 'Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2026',
        'image' => 'assets/images/programs/skills-development-01.webp',
        'stats' => [],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
