<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'research-hub',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Research & Learning Hub',
    'before_after' => false,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Research.svg',
    'image_alt' => 'Herzing–Daya Research and Learning Hub at University of Chitral',
    'seo_title' => 'Herzing–Daya Research & Learning Hub — Worldwide Education Foundation',
    'seo_description' => 'Pakistan\'s first ECD research hub in a remote mountain region, hosted at the University of Chitral and led by WEF.',
    'fallback' => [
        'location' => 'Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2026',
        'image' => 'assets/images/programs/research-hub-01.jpg',
        'stats' => [
            ['value' => 'Hosted by', 'label' => 'University of Chitral'],
            ['value' => 'Led by', 'label' => 'Worldwide Education Foundation (WEF), USA'],
            ['value' => 'Funded by', 'label' => 'The Herzing and Daya Families'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
