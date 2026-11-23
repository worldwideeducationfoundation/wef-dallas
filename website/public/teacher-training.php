<?php
// Settings for this project's page; the layout is shared by all nine
// (includes/layout/project-page.php). Stats, location, badge, cover photo
// and photos come from admin -> Our Active Projects, the words from
// admin -> Page Content. The fallback values show only if the database
// is unreachable.
$projectPage = [
    'slug' => 'teacher-training',
    // The words of the project's name the cover draws its line under.
    'highlight' => 'Training Program',
    'before_after' => false,
    'icon' => 'assets/icons/SVG/Cart%20Icons/Students.svg',
    'image_alt' => 'Teachers in a training session in Booni, Chitral',
    'seo_title' => 'English Language Skills Training for Teachers — Worldwide Education Foundation',
    'seo_description' => 'A TEFL micro-credential certification program for teachers in the remote valley of Booni, Chitral, in partnership with Bridge Education Group.',
    'fallback' => [
        'location' => 'Booni Chitral, Khyber Pakhtunkhwa, Pakistan',
        'badge' => 'Active since October 2022',
        'image' => 'assets/images/programs/teacher-training-01.webp',
        'stats' => [
            ['value' => '21', 'label' => 'Teachers Trained (Total)'],
            ['value' => '08', 'label' => 'Certified — First Round'],
            ['value' => '13', 'label' => 'Currently in Training'],
        ],
    ],
];

require __DIR__ . '/../includes/layout/project-page.php';
