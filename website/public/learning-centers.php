<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'learning-centers',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Learning Centers',
    // Before/after slider after the impact line.
    'before_after' => true,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Schools.svg',
    'image_alt' => 'Learning Center classroom in a Chitral government school',
    'seo_title' => 'Government School Learning Centers — Worldwide Education Foundation',
    'seo_description' => 'Integrating Early Childhood Development and Technology-Assisted English Language Labs into government primary schools across Chitral.',
    'fallback' => [
        'location' => 'Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active Since 2024',
        'image' => 'assets/images/programs/learning-centers-01.jpg',
        'stats' => [
            ['value' => '2,000', 'label' => 'Children Reached'],
            ['value' => '20', 'label' => 'Teachers Trained'],
            ['value' => '10', 'label' => 'ECD Educators Trained'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
