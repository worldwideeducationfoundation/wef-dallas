<?php
/**
 * Editable content blocks for the parts of the site that aren't one of
 * the six CMS content types.
 *
 * The problem: 18 public pages were 100% hardcoded. Headings, intro
 * paragraphs, mission statements, contact copy — none of it could be
 * changed without editing PHP. This makes any of it editable in the
 * admin, one block at a time, without restructuring the pages.
 *
 * How it works
 * ------------
 * BLOCK_REGISTRY below is the single source of truth for *structure*:
 * which blocks exist, what they're called, what type of input to show,
 * and the default text (which is the copy that was hardcoded on the page).
 *
 * The content_blocks table stores *overrides only*. So:
 *   - a page renders correctly with an empty table (falls back to default)
 *   - "Reset to default" just deletes the row
 *   - nothing is duplicated between code and database
 *
 * Adding a new editable block is two lines: one registry entry, and one
 * block() call replacing the hardcoded text on the page.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/content.php';

/**
 * page => [ block key => [label, type, default, group?, help?] ]
 *
 * type: 'text'     single-line input, rendered with block()
 *       'textarea' multi-line, newlines become <br>, rendered with blockRich()
 *       'body'     long-form copy, rendered with blockBody() — see below
 *
 * group: optional heading the edit form files this block under. Pages with
 *        more than a handful of blocks are unreadable as one flat column of
 *        inputs, and the grouping mirrors the sections a visitor sees, so
 *        "the paragraph under Who We Are" is findable without counting fields.
 *
 * help:  optional note shown under the field, for blocks whose effect isn't
 *        obvious from the label alone.
 */

/**
 * Character ceilings per type, shared by the edit form and its save handler so
 * the two can never disagree about what fits. They were previously written out
 * separately in each file.
 */
const BLOCK_LIMITS = [
    'text' => 500,
    'textarea' => 4000,
    'body' => 20000,
    // An image block stores the uploaded file's path (admin/uploads/pages/...).
    'image' => 255,
];

function blockLimit(string $type): int
{
    return BLOCK_LIMITS[$type] ?? BLOCK_LIMITS['text'];
}
const BLOCK_REGISTRY = [
    'home' => [
        'label' => 'Home',
        'url' => '/',
        'blocks' => [
            'section1_title' => [
                'label' => 'Section 1 heading',
                'type' => 'text',
                'default' => 'Who We Are',
            ],
            'section1_intro' => [
                'label' => 'Section 1 paragraph',
                'type' => 'textarea',
                'default' => 'Guided by our vision, driven by our mission, and grounded in our belief that every child deserves a chance to learn and thrive.',
            ],
            'section2_title' => [
                'label' => 'Section 2 heading',
                'type' => 'text',
                'default' => 'Our Active Projects',
            ],
            'section2_intro' => [
                'label' => 'Section 2 paragraph',
                'type' => 'textarea',
                'default' => 'Our active projects across Chitral and Central Asia are building a foundation for lifelong learning and community empowerment.',
            ],
            'section3_title' => [
                'label' => 'Section 3 heading',
                'type' => 'text',
                'default' => 'Project Updates',
            ],
            'section3_intro' => [
                'label' => 'Section 3 paragraph',
                'type' => 'textarea',
                'default' => 'Stay informed about our latest accomplishments, new initiatives, and field activities across Chitral.',
            ],
            'section4_title' => [
                'label' => 'Section 4 heading',
                'type' => 'text',
                'default' => 'WEF Beginnings',
            ],
            'section5_title' => [
                'label' => 'Section 5 heading',
                'type' => 'text',
                'default' => 'Get In Touch',
            ],
            'section5_intro' => [
                'label' => 'Section 5 paragraph',
                'type' => 'textarea',
                'default' => 'Have questions about our work or want to get involved? We\'d love to hear from you.',
            ],
            'honor_board_title' => [
                'label' => 'Honor Board heading',
                'type' => 'text',
                'default' => 'Honor Board - Circle of Friends - Chitral, Pakistan',
            ],
            'partners_title' => [
                'label' => 'Partners section heading',
                'type' => 'text',
                'default' => 'Our Partners',
            ],
            'purpose_badge' => [
                'label' => 'Purpose banner eyebrow',
                'type' => 'text',
                'group' => 'Purpose banner',
                'default' => 'Our Core Purpose',
            ],
            'purpose_headline' => [
                'label' => 'Purpose banner headline',
                'type' => 'textarea',
                'group' => 'Purpose banner',
                'default' => 'We aim to break the cycle of generational poverty and foster prosperity',
                'help' => 'Shown in large type between the hero and the About section. Plain text — the accent colour on "prosperity" is fixed styling, not part of the editable wording.',
            ],
            'about_vision_title' => [
                'label' => 'Vision card title',
                'type' => 'text',
                'group' => 'About cards',
                'default' => 'Our Vision',
            ],
            'about_vision_text' => [
                'label' => 'Vision card text',
                'type' => 'textarea',
                'group' => 'About cards',
                'default' => 'We envision a world where every child, regardless of background, has access to quality education that empowers them to break the cycle of poverty and build a brighter future for themselves and their communities.',
            ],
            'about_mission_title' => [
                'label' => 'Mission card title',
                'type' => 'text',
                'group' => 'About cards',
                'default' => 'Our Mission',
            ],
            'about_mission_text' => [
                'label' => 'Mission card text',
                'type' => 'textarea',
                'group' => 'About cards',
                'default' => 'We invest in the potential of children and youth across underserved communities in Central and South Asia through early childhood development, teacher training, English language learning, and scholarships.',
            ],
            'about_belief_title' => [
                'label' => 'Belief card title',
                'type' => 'text',
                'group' => 'About cards',
                'default' => 'Our Belief',
            ],
            'about_belief_text' => [
                'label' => 'Belief card text',
                'type' => 'textarea',
                'group' => 'About cards',
                'default' => 'We believe talent is universal, but opportunity is not. Every child deserves the chance to learn, grow, and thrive, because education unlocks human potential and creates lasting change.',
            ],
            'beginnings_step1' => [
                'label' => 'Timeline step 1',
                'type' => 'textarea',
                'group' => 'WEF Beginnings timeline',
                'default' => 'For many years, Connie and Kamal Daya provided modest financial support to orphanages, hostels, and schools. A trip to Asia in 2004 brought them face-to-face with urgent institutional needs, leading them to commit to deeper engagement and to do more.',
            ],
            'beginnings_step2' => [
                'label' => 'Timeline step 2',
                'type' => 'textarea',
                'group' => 'WEF Beginnings timeline',
                'default' => 'The Connie and Kamal Daya Gift Fund of The Dallas Foundation invited others who shared their vision to participate in initiatives designed to benefit underserved children and youth, expanding collective support and strengthening long-term impact.',
            ],
            'beginnings_step3' => [
                'label' => 'Timeline step 3',
                'type' => 'textarea',
                'group' => 'WEF Beginnings timeline',
                'default' => 'WEF officially began in 2009 with a generous donation from the Connie & Kamal Daya Gift Fund of The Dallas Foundation, establishing a structured foundation to advance its mission and broaden support for children and youth.',
            ],
            'beginnings_step4' => [
                'label' => 'Timeline step 4',
                'type' => 'textarea',
                'group' => 'WEF Beginnings timeline',
                'default' => 'Over the years, the organization has supported the poorest communities, and more than 20,000 children and youth have benefited from various WEF projects, reflecting sustained commitment and measurable impact across underserved regions.',
            ],
            'globally_title' => [
                'label' => 'WEF Globally heading',
                'type' => 'text',
                'group' => 'WEF Globally',
                'default' => 'WEF Globally',
            ],
            'globally_map_image' => [
                'label' => 'World map picture',
                'type' => 'image',
                'group' => 'WEF Globally',
                'default' => 'assets/images/global-map.png',
                'help' => 'The map beside the WEF Globally numbers; visitors can click it to enlarge.',
            ],
            'globally_text1' => [
                'label' => 'WEF Globally paragraph 1',
                'type' => 'textarea',
                'group' => 'WEF Globally',
                'default' => 'We are actively operating in Pakistan and Tajikistan, with a focus on our ongoing projects in these regions.',
            ],
            'globally_text2' => [
                'label' => 'WEF Globally paragraph 2',
                'type' => 'textarea',
                'group' => 'WEF Globally',
                'default' => 'We successfully implemented the Global Virtual School (GVS) initiative, which grew to 30 centers across 9 countries over 5 years. Although the GVS project has concluded, it remains a significant milestone in our journey.',
            ],
            'impact_teachers_count' => [
                'label' => 'Teachers stat number',
                'type' => 'text',
                'group' => 'WEF Globally',
                'default' => '200',
                'help' => 'Digits only — this number animates as a count-up.',
            ],
            'impact_experience_count' => [
                'label' => 'Years of experience stat number',
                'type' => 'text',
                'group' => 'WEF Globally',
                'default' => '16',
                'help' => 'Digits only — this number animates as a count-up.',
            ],
            'impact_students_count' => [
                'label' => 'Students stat number',
                'type' => 'text',
                'group' => 'WEF Globally',
                'default' => '20000',
                'help' => 'Digits only — this number animates as a count-up.',
            ],
            'impact_spaces_count' => [
                'label' => 'Learning spaces stat number',
                'type' => 'text',
                'group' => 'WEF Globally',
                'default' => '100',
                'help' => 'Digits only — this number animates as a count-up.',
            ],
            'map_title' => [
                'label' => 'Centers map heading',
                'type' => 'text',
                'group' => 'Interactive map',
                'default' => 'Our ECLC Centers Network',
            ],
            'map_intro' => [
                'label' => 'Centers map introduction',
                'type' => 'textarea',
                'group' => 'Interactive map',
                'default' => 'Explore the spatial distribution of our {count} active Early Childhood Learning Centers across Chitral, Pakistan. Click any marker to view details, or use the filters below to explore by district, sponsor or category.',
                'help' => '{count} is replaced by the number of centers the map is showing, which changes as visitors filter.',
            ],
            'contact_connect_title' => [
                'label' => "'Let's Connect' heading",
                'type' => 'text',
                'group' => 'Contact',
                'default' => "Let's Connect",
            ],
            'contact_connect_text' => [
                'label' => "'Let's Connect' paragraph",
                'type' => 'textarea',
                'group' => 'Contact',
                'default' => "Whether you're interested in partnering with us, volunteering, or learning more about our projects, reach out and we'll get back to you promptly.",
            ],
        ],
    ],
    'programs' => [
        'label' => 'Programs',
        'url' => '/programs',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Our Projects',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => '9 active projects across Chitral and Central Asia building a foundation for lifelong learning and community empowerment.',
            ],
        ],
    ],
    'gallery' => [
        'label' => 'Gallery',
        'url' => '/gallery',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Our Gallery',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Moments of learning, growth, and transformation from our projects across Chitral and Central Asia.',
            ],
        ],
    ],
    'annual-reports' => [
        'label' => 'Annual Reports',
        'url' => '/annual-reports',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Annual Reports',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Transparency, accountability, and the measurable impact of your support.',
            ],
            // NOTE: this page used to render the Annual Reports CMS module and
            // had a "Transparency in Action" section plus an empty-state
            // message. It was rewritten to link four PDFs directly, and the
            // four blocks describing that section were left behind here —
            // editable in the CMS, attached to markup that no longer existed.
            // They are removed rather than reattached, because the section
            // they described is genuinely gone. See docs/cms-coverage.md for
            // the larger problem this is one symptom of: the Annual Reports
            // module still writes to a table no public page reads.
        ],
    ],
    'our-mission' => [
        // The page behind this key is now the "WEF At A Glance" projects
        // table, not the old mission statement — it was rebuilt without the
        // block() calls being carried over, so both entries here were
        // unreachable and their defaults described copy that had been deleted.
        // The defaults below are the wording the page actually shows today.
        'label' => 'Our Mission',
        'url' => '/our-mission',
        'blocks' => [
            'pillar1_title' => [
                'label' => 'Pillar 1 heading',
                'type' => 'text',
                'default' => 'Our Vision',
            ],
            'pillar1_text' => [
                'label' => 'Pillar 1 text',
                'type' => 'textarea',
                'default' => 'We envision a world where every child — regardless of background — has access to the education they need to break the cycle of poverty and shape a brighter, more prosperous future.',
            ],
            'pillar2_title' => [
                'label' => 'Pillar 2 heading',
                'type' => 'text',
                'default' => 'Our Mission',
            ],
            'pillar2_text' => [
                'label' => 'Pillar 2 text',
                'type' => 'textarea',
                'default' => 'We invest in potential of children and youth across under served communities in Central and South Asia. Through early childhood development programs, English language learning, and scholarships, we open doors to brighter future – empowering young learners with the skills and confidence to transform their lives and uplift their communities.',
            ],
            'pillar3_title' => [
                'label' => 'Pillar 3 heading',
                'type' => 'text',
                'default' => 'Our Belief',
            ],
            'pillars_image' => [
                'label' => 'Photo beside the pillars',
                'type' => 'image',
                'default' => 'assets/images/programs/bright-beginnings-01.webp',
            ],
            'pillar3_text' => [
                'label' => 'Pillar 3 text',
                'type' => 'textarea',
                'default' => 'We believe that talent is universal, but opportunity is not. Every child deserves the chance to learn, grow and thrive — regardless of financial means, caste, religion, or gender. With the right support, education can unlock human potential and create lasting change.',
            ],
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'WEF AT A GLANCE',
            ],
            'subtitle' => [
                'label' => 'Subheading under the page title',
                'type' => 'text',
                'default' => 'OUR ACTIVE PROJECTS',
            ],
            'footer_note' => [
                'label' => 'Footer legal note',
                'type' => 'textarea',
                'group' => 'Footer',
                'default' => 'Worldwide Education Foundation is a 501(c)(3). Federal Tax ID 81–2135633. 100% of all dollars raised through your contributions go towards implementation, operation, and maintenance of WEF\'s projects and programs. All administrative expenses are paid by the Founders, Connie and Kamal Daya Gift Fund of the Dallas Foundation.',
            ],
            'footer_date' => [
                'label' => 'Footer "as of" date',
                'type' => 'text',
                'group' => 'Footer',
                'default' => 'April 2026',
            ],
            'download_button_label' => [
                'label' => 'PDF download button text',
                'type' => 'text',
                'group' => 'Footer',
                'default' => 'Download WEF At a Glance PDF',
            ],
        ],
    ],
    'contact' => [
        'label' => 'Contact',
        'url' => '/contact',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Contact Us',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Have questions about our work or want to get involved? We\'d love to hear from you.',
            ],
            'connect_title' => [
                'label' => "'Let's Connect' heading",
                'type' => 'text',
                'default' => "Let's Connect",
            ],
            'connect_text' => [
                'label' => "'Let's Connect' paragraph",
                'type' => 'textarea',
                'default' => "Whether you're interested in partnering with us, volunteering, or learning more about our projects, reach out and we'll get back to you promptly.",
            ],
        ],
    ],
    'volunteer' => [
        'label' => 'Volunteer',
        'url' => '/volunteer',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Volunteer With Us',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Give your time and expertise to make a lasting impact.',
            ],
            'connect_title' => [
                'label' => "'Join the Mission' heading",
                'type' => 'text',
                'default' => 'Join the Mission',
            ],
            'connect_text' => [
                'label' => "'Join the Mission' paragraph",
                'type' => 'textarea',
                'default' => 'We are always looking for passionate educators, coordinators, and professionals to help us scale our impact. Fill out the form to become a volunteer.',
            ],
        ],
    ],
    'completed-projects' => [
        'label' => 'Completed Projects',
        'url' => '/completed-projects',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Completed Projects',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Celebrating the milestones and initiatives that have laid the foundation for our ongoing mission.',
            ],
        ],
    ],
    'footer' => [
        'label' => 'Footer (shown on every page)',
        'url' => '/',
        'blocks' => [
            'tagline' => [
                'label' => 'Tagline under the logo',
                'type' => 'text',
                'default' => 'Empowering through education.',
            ],
            'about_text' => [
                'label' => 'About paragraph',
                'type' => 'textarea',
                'default' => 'We invest in the potential of children and youth across underserved communities in Central and South Asia through early development programs, English language learning, and scholarships that open doors to opportunity.',
            ],
            'newsletter_title' => [
                'label' => 'Newsletter heading',
                'type' => 'text',
                'group' => 'Newsletter',
                'default' => 'Stay Updated',
            ],
            'newsletter_text' => [
                'label' => 'Newsletter paragraph',
                'type' => 'textarea',
                'group' => 'Newsletter',
                'default' => "Subscribe to receive updates on our projects and the impact we're making together.",
            ],
            'legal_text1' => [
                'label' => 'Legal line 1 (status + tax ID)',
                'type' => 'textarea',
                'group' => 'Legal',
                'default' => 'Worldwide Education Foundation is a 501(c)(3) nonprofit · Federal Tax ID 81-2135633',
            ],
            'legal_text2' => [
                'label' => 'Legal line 2 (funding statement)',
                'type' => 'textarea',
                'group' => 'Legal',
                'default' => "100% of all dollars raised go towards implementation, operation, and maintenance of WEF's projects. All administrative expenses are paid by the Founders, Connie and Kamal Daya Gift Fund of the Dallas Foundation.",
            ],
        ],
    ],
    'bright-beginnings' => [
        'label' => 'Bright Beginnings',
        'url' => '/bright-beginnings',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Bright Beginnings: Early Childhood Learning Centers',
            ],
            'body_1' => [
                'label' => 'Main text, part 1',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'Bright Beginnings: Early Childhood Learning Centers (ECLC) bring high-quality Early Childhood Development (ECD) opportunities to communities across Chitral, Pakistan, with a strong focus on remote and underserved areas. Each center follows a Montessori-inspired learning approach that nurtures curiosity, creativity, independence, and hands-on learning among children aged 3–5 years.

## Montessori Learning for Young Minds

At Bright Beginnings, every classroom is thoughtfully designed to encourage exploration and discovery. Using Montessori learning materials, child-friendly furniture, and smart TVs for interactive learning, children develop foundational skills while exploring the world beyond their surroundings — from nature to different cultures.

## Empowering Women Entrepreneurs

Each center is led by trained women entrepreneurs who receive professional training in Montessori pedagogy, early childhood care, and educational technology. These women are supported by assistant teachers, creating sustainable income opportunities and empowering local women to lead positive change in their own communities.

## Integrating Technology in Learning

While preserving traditional Montessori principles, technology in our centers enhances creativity and engagement. Through guided visual content and interactive sessions, children explore global ideas while staying rooted in experiential learning.

## Community-Driven Education

Parents and community members are vital to our success. Regular meetings and engagement sessions ensure that every BB ECLC remains a shared space for collaboration, belonging, and growth — where families and educators work hand in hand to support every child\'s journey.',
            ],
            'body_2' => [
                'label' => 'Main text, part 2',
                'type' => 'body',
                'group' => 'Article',
                'default' => '## Our Impact So Far',
            ],
            'body_3' => [
                'label' => 'Main text, part 3',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'Communities actively involved in sustaining and expanding the initiative.

## Our Vision for the Future

- By 2030: 500 centers serving 15,000 children and 1000 women will be empowered
- By 2035: 1,200 centers serving 36,000 children and 2,400 women will be empowered

WEF\'s investment over term of the project is projected to be 4 million Dollars / 120 crore Rupees.

Through Bright Beginnings, Worldwide Education Foundation (WEF) aims to transform early childhood education in Chitral, ensuring that every child has access to a stimulating learning environment, and every woman has the opportunity to lead change within her community.',
            ],
        ],
    ],
    'learning-centers' => [
        'label' => 'Learning Centers',
        'url' => '/learning-centers',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Learning Centers: Integrating Early Childhood Development and Technology-Assisted English Language Labs',
            ],
            'body_1' => [
                'label' => 'Main text',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'This project is a collaborative initiative of Worldwide Education Foundation (WEF), USA, Foundation to Empower Students (FES) Pakistan, and the Elementary and Secondary Education Department, Government of Khyber Pakhtunkhwa. The project brings Montessori-based Early Childhood Development (ECD) into government schools in Chitral for the first time, ensuring young children have access to structured, high-quality early learning during their most critical developmental years.

This initiative integrates two complementary components within government primary schools. In the morning, Learning Centers operate as Montessori-based ECD classrooms for children aged 3–5, providing structured, hands-on learning experiences that foster cognitive, social, emotional, language, and motor development. In the afternoon, the same spaces function as Technology-Enhanced English Language Labs, supporting primary school students in developing foundational English language and communication skills through interactive and technology-assisted learning.

Each Learning Center is equipped with Montessori learning materials, educational technology, and child-friendly furniture designed to create engaging and stimulating learning environments. Recognizing that quality education depends on well-prepared educators, the project also invests in teacher capacity building through teacher training enabling teachers and ECD educators to effectively implement Montessori principles and child-centered teaching practices.

By introducing Montessori-based ECD within government schools and strengthening English language learning, the project is helping to improve educational quality, enhance school readiness, and expand opportunities for children in underserved communities across Chitral.

## Current Impact

- Introduced Montessori-based Early Childhood Development education in government schools
- Reached 2000 children through ECD and English language learning programs.
- Trained 20 teachers and 10 ECD educators in Montessori and child-centered teaching methodologies.
- Created inclusive and engaging learning environments that help bridge educational disparities between public and private schools.

Through this innovative model, this Project is laying a strong foundation for lifelong learning while advancing equitable access to quality education for children in remote and underserved communities.',
            ],
        ],
    ],
    'model-schools' => [
        'label' => 'Model Schools',
        'url' => '/model-schools',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Model Schools: Transforming Government Primary Schools in Upper Chitral',
            ],
            'body_1' => [
                'label' => 'Main text, part 1',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'This project is one of the most impactful initiatives of Worldwide Education Foundation (WEF), USA, in Chitral Valley. It addresses a critical gap in early primary education within government schools by improving the learning environment beyond Early Childhood Development (ECD) classrooms.

For several years, WEF has strengthened early education through the establishment of Learning Centers, which integrate Montessori-based Early Childhood Development (ECD) classrooms for children aged 3–6 and Technology-Assisted English Language Labs for children aged 6–11 across 10 government schools. These centers have already reached more than 2,000 students and trained 20 teachers, creating engaging, child-centered, and technology-supported early learning environments.

However, a key challenge emerged: after experiencing vibrant ECD classrooms, children moved into Grade 1 classrooms that were under-resourced and not child-friendly. This sudden shift risked disrupting their motivation, confidence, and continuity of learning.

To address this gap, WEF launched the Model School Project, a focused initiative to transform the rest of the classrooms into welcoming, developmentally appropriate, and stimulating learning spaces. The aim is to ensure a smooth and positive transition from ECD to primary education, preserving children\'s curiosity and enthusiasm for learning.

With the support of dedicated Chitrali team and generous sponsors in the US, WEF has:

- Renovated all the classrooms in 10 Government Primary Schools.
- Created clean, bright, carpeted, and child-friendly learning environments
- Introduced access to educational technology and interactive learning tools
- Strengthened continuity between ECD and primary education

## See the Transformation',
            ],
            'body_2' => [
                'label' => 'Main text, part 2',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'As a result, children entering Grade 1 now experience an environment that reflects the same warmth, structure, and encouragement they received in their early learning years—helping them build confidence and a sustained love for learning.

## Growing Momentum through Partnership

This progress has been further strengthened through a formal Memorandum of Understanding (MoU) between Foundation to Empower Students (FES), Pakistan and the Elementary and Secondary Education Department, Government of Khyber Pakhtunkhwa. This partnership ensures long-term sustainability, institutional support, and the potential for scale.',
            ],
            'body_3' => [
                'label' => 'Main text, part 3',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'WEF is envisioning setting up 600 more Model Schools by 2035 with projected investment of 2 million Dollars / 60 Crore Rupees.',
            ],
        ],
    ],
    'language-labs' => [
        'label' => 'Language Labs',
        'url' => '/language-labs',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'In-School Language Labs',
            ],
            'body_1' => [
                'label' => 'Main text',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'In-School Language Labs represents a collaborative initiative between the Worldwide Education Foundation (WEF), USA, and its project delivery partner, Foundation to Empower Students (FES), operating in Pakistan. Functioning as tangible educational spaces, these Language Labs, established by WEF, aim to impart high-quality English language skills to students attending low-income private schools in Chitral Pakistan.

The chosen schools serve as dedicated hubs for the installation of English Language Labs and Career Counseling and Coaching Resource Centers. WEF/FES facilitates the provisioning of essential technology and internet-based content, obtained through licensed agreements with platforms such as Fluent U, Lingua Phone, and similar proprietary sources. Qualified English Language Facilitators, recruited and trained by FES, spearhead the delivery of English language instruction and career counseling resources within these labs. Importantly, these facilitators also play a pivotal role in training existing English language teachers at the schools, empowering them to assume leadership roles within the lab environment.

The primary focus of these language labs is to enhance the listening and speaking skills of students in primary and secondary schools. By fostering an environment that encourages the exchange of ideas and questions, the labs provide students with insights into the thoughts and actions of their peers in the broader world. Additionally, these labs serve as valuable resources for local teachers who may lack sufficient classroom time to address these aspects of language learning and face challenges in accessing relevant educational materials.

## Current Impact

At present, there are 5 language labs operational at low-income schools in Chitral where we have around 1250 students enrolled who are being trained by 100 school teachers and 5 facilitators with the language skills needed to pursue higher education, vocational training, and improved career prospects.',
            ],
        ],
    ],
    'teacher-training' => [
        'label' => 'Teacher Training',
        'url' => '/teacher-training',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'English Language Skills Training for Teachers',
            ],
            'body_1' => [
                'label' => 'Main text',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'English Language Skills Training for Teachers is an initiative of Worldwide Education Foundation (WEF), USA in partnership with Bridge Education Group. Bridge has offered and sponsored the three – month, self-paced, tutor guided, online Micro Credential TEFL certification to 08 teachers from the remote valley of Booni, Chitral in Northern Pakistan.

WEF provides the platform and necessary technology support to the teachers for taking the course which otherwise would not have been possible. To ensure active and effective engagement with the course, WEF organized weekly reflection sharing sessions. The teachers gather once in a week and share their understanding of the module and engage in discussion regarding the course. At the end of the course the teacher will do a practicum and submit a comprehensive assignment based on their understanding of the course. WEF will provide the practicum opportunities for the teachers at their In-School Language Labs to put theory into practice

## Current Impact

Under this program, 08 teachers received certificates in the first round of the training. We are currently impacting 13. So in total, we have trained 21 teachers to teach quality English Language skills and classroom management.',
            ],
        ],
    ],
    'research-hub' => [
        'label' => 'Research Hub',
        'url' => '/research-hub',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Herzing–Daya Research & Learning Hub at University of Chitral',
            ],
            'tagline' => [
                'label' => 'Hero tagline',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Generating Knowledge from the Mountains. Creating Impact for Children Everywhere.',
            ],
            'body_1' => [
                'label' => 'Main text',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'The Herzing–Daya Research & Learning Hub is one of Pakistan\'s first Early Childhood Development (ECD) research hubs located in a remote mountain region. Hosted at the University of Chitral and led by the Worldwide Education Foundation (WEF), USA, the Hub is dedicated to producing locally grounded evidence that strengthens ECD practice, informs policy, and improves outcomes for young children in underserved communities.

## Why Chitral?

- Home to 52,000 children ages 3–5
- More than 40,000 primary school students
- Unique mountain communities often overlooked in education research

## What We Do

- Conduct research on early childhood development and school readiness
- Strengthen ECD and primary education practices
- Build research capacity through academic partnerships
- Generate evidence to inform education policy and investment

## Who Benefits?

- Young children and families
- ECD educators and primary school teachers
- Researchers and university students
- Policymakers and education stakeholders

## Expected Impact

- Stronger early childhood education systems
- Smoother transition from ECD to primary school
- Evidence-based, scalable rural ECD models
- Research that informs policy and practice across Pakistan',
            ],
        ],
    ],
    'skills-development' => [
        'label' => 'Skills Development',
        'url' => '/skills-development',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Herzing Center for Skills, Innovation and Entrepreneurship at University of Chitral',
            ],
            'tagline' => [
                'label' => 'Hero tagline',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Building Capacity. Developing Leaders. Creating Workforce-Ready Talent.',
            ],
            'body_1' => [
                'label' => 'Main text',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'An Integrated Platform for Professional Development, Teacher Training, and Workforce Readiness

Herzing Center for Skills, Innovation and Entrepreneurship is a comprehensive capacity-building initiative designed to strengthen human capital in Chitral and its surrounding regions. Hosted at the University of Chitral, the program seamlessly bridges the gap between education and employment by bringing academic learning, vocational training, and professional certification under one integrated platform.

Our Vision: To create a scalable, inclusive, and future-ready skills development ecosystem that equips youth, educators, and professionals with the precise competencies needed to thrive in evolving local and global labor markets.

## Core Objectives

- Forge Strategic Partnerships: Build and strengthen alliances with elite training institutions both within Pakistan and internationally.
- Cultivate High-Demand Talent: Develop workforce-ready professionals tailored specifically for high-growth economic sectors.
- Prioritize Practical Outcomes: Emphasize certification-based learning, hands-on training, and robust job placement support.
- Launch Careers: Create highly structured, dependable pathways from initial training directly into employment and entrepreneurship.

## Targeted Program Tracks

The Herzing Center for Skills, Innovation and Entrepreneurship delivers specialized diploma and certification pathways designed to meet urgent market demands:

- Early Childhood Development (ECD): Transforming regional education by training the next generation of specialized foundational educators.
- Hospitality, Travel, and Tourism: Equipping talent to lead and professionalize the region\'s rapidly growing tourism ecosystem.
- Information Technology & Digital Skills: Building future-proof tech competencies, from basic digital literacy to advanced freelance capabilities.
- Higher Education Progression: Creating seamless bridging pathways for students to transition into advanced degree and diploma programs.

## Key Features & Ecosystem

- Prestigious Institutional Anchor: Centered and hosted directly at the University of Chitral.
- Unified Learning Platform: A singular, integrated ecosystem combining formal university degrees, professional diplomas, and rapid short courses.
- Centralized Opportunity Hub: A single-window resource center tracking and distributing scholarships, job placements, and startup incubation support.
- Applied Learning Focus: Heavy emphasis on industry-aligned skills and practical, real-world application over rote memorization.

## Hybrid Delivery Model

To guarantee maximum reach and equity, the program is deployed through a highly resilient, blended delivery model:

- In-Person: Herzing Center at the University of Chitral + 12 satellite hubs
- Online: Zoom-based learning and offline-enabled digital platforms (Chip) for low-connectivity areas.

## Who Benefits?

The program serves:

- Youth & Job Seekers looking to break into the modern workforce.
- Educators & Trainers seeking international-standard pedagogical skills.
- Emerging Professionals looking to upskill and accelerate their career trajectories.

## Funding & Support

The program is proudly launched and sustained through the visionary backing of the Herzing Family Grant. This foundational support enables the development of entirely accessible, high-quality training opportunities specifically designed to uplift and empower underserved

Together, we can ensure that every child in Chitral has the opportunity to begin their educational journey with confidence, curiosity, and hope.',
            ],
        ],
    ],
    'secondary-scholarships' => [
        'label' => 'Secondary Scholarships',
        'url' => '/secondary-scholarships',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'Investing in Young Futures: Secondary School Scholarships',
            ],
            'body_1' => [
                'label' => 'Main text, part 1',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'The Worldwide Education Foundation (WEF) USA, in collaboration with the Foundation to Empower Students (FES) Pakistan, is implementing the Secondary School Scholarship Program in Upper Chitral, Pakistan.

In this region, many students are unable to continue their education beyond Grade 8 because of financial hardship. Parents are committed to their children\'s future, often paying school fees even if it means cutting back on essential needs like healthcare. This scholarship program directly responds to that challenge. By covering tuition fees, WEF and FES reduce the pressure on families, allowing them to reallocate their limited resources toward other basic necessities while keeping their children in school.

WEF has a long track record of advancing education globally, while FES brings decades of grassroots experience in Chitral. Together, they form a strong partnership that ensures the program is both effective and deeply connected to local realities.

## Current Impact

At present, the program supports 115 students enrolled in five private schools in Upper Chitral:

- Oxford Public School
- Orion School of Learning
- Frontier Corps Public School
- Alkhidmat Foundation School (Space Era Campus Booni)
- Aga Khan High School Booni

For these students, the scholarships mean much more than just financial help. They represent encouragement, dignity, and hope. One student expressed it simply:

"Without this scholarship, I would have had to leave school to help my family."

Stories like these show the true impact of the program—restoring belief in education and building confidence for the future.

This initiative also reflects our wider vision: "We invest in the potential of children and youth across underserved communities in Central and South Asia. Through early childhood development programs, English language learning, and scholarships, we open doors to brighter futures—empowering young learners with the skills and confidence to transform their lives and uplift their communities."

Looking ahead, WEF and FES are preparing to expand the program to include students entering 1st Year and 2nd Year (Intermediate level). By 2026, our goal is to double the number of students supported, ensuring that bright learners continue seamlessly into higher secondary education without financial barriers.',
            ],
            'body_2' => [
                'label' => 'Main text, part 2',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'The Secondary School Scholarship Program is more than an education initiative—it is an investment in a generation of leaders, thinkers, and change-makers who will shape the future of Chitral and beyond.',
            ],
        ],
    ],
    'rising-stars' => [
        'label' => 'AM Rising Stars',
        'url' => '/rising-stars',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'group' => 'Hero',
                'default' => 'AM Rising Stars Undergraduate Scholarship',
            ],
            'body_1' => [
                'label' => 'Main text, part 1',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'Launched in 2023, the AM Rising Stars Undergraduate Scholarship Program removes financial barriers that prevent talented young people from pursuing higher education and reaching their full potential.

Funded by Sadiq Ali & Family, administered by the Worldwide Education Foundation (WEF), the program provides deserving students from Tajikistan & Kyrgyzstan with access to world-class undergraduate education at leading regional universities.

Scholarship recipients are selected through a competitive process that identifies academically promising students with strong leadership potential and a commitment to contributing to their communities.

The program currently supports students enrolled at:

- American University of Central Asia (AUCA), Bishkek, Kyrgyzstan
- University of Central Asia (UCA), Khorog, Tajikistan

Beyond financial support, the scholarship program seeks to nurture a new generation of educated professionals, community leaders, and change makers who will contribute to social and economic development in Central Asia.

## Impact

- 52 scholarship recipients supported at AUCA.
- 14 scholarship recipients supported at UCA.
- 66 students provided access to quality undergraduate education.
- Creating pathways to leadership, professional success, and community impact for talented youth from underserved backgrounds.',
            ],
            'body_2' => [
                'label' => 'Main text, part 2',
                'type' => 'body',
                'group' => 'Article',
                'default' => 'By investing in promising students today, the AM Rising Stars Undergraduate Scholarship Program is helping build a stronger, more educated, and more prosperous future for the region.',
            ],
        ],
    ],
    'success-stories' => [
        'label' => 'Success Stories',
        'url' => '/success-stories',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Success Stories',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Real voices, real impact. See how your support is transforming lives in Chitral.',
            ],
        ],
    ],
    'video-gallery' => [
        'label' => 'Video Gallery',
        'url' => '/video-gallery',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Video Gallery',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Watch the impact of our projects through moving stories and footage.',
            ],
        ],
    ],
    'our-team' => [
        'label' => 'Our Team',
        'url' => '/our-team',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'Our Team',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Meet the dedicated leaders guiding our mission to empower communities through education.',
            ],
        ],
    ],
    'impact-goals' => [
        'label' => 'Impact Goals',
        'url' => '/impact-goals',
        'blocks' => [
            'page_title' => [
                'label' => 'Page heading',
                'type' => 'text',
                'default' => 'IMPACT GOALS 2027-2031',
            ],
            'intro' => [
                'label' => 'Intro paragraph',
                'type' => 'textarea',
                'default' => 'Transforming lives through education, empowering teachers, and expanding learning opportunities across underserved communities.',
            ],
            'chart_students_label' => [
                'label' => 'Chart bar label — students',
                'type' => 'text',
                'default' => 'STUDENTS IMPACTED',
            ],
            'chart_teachers_label' => [
                'label' => 'Chart bar label — teachers/entrepreneurs',
                'type' => 'text',
                'default' => 'TRAINED TEACHERS / ENTREPRENEURS',
            ],
            'summary_centers_label' => [
                'label' => 'Summary card 1 — title',
                'type' => 'text',
                'default' => 'Learning Centers',
            ],
            'summary_centers_text' => [
                'label' => 'Summary card 1 — description',
                'type' => 'textarea',
                'default' => 'Establishing community-run centers to serve as local hubs for education.',
            ],
            'summary_students_label' => [
                'label' => 'Summary card 2 — title',
                'type' => 'text',
                'default' => 'Students Impacted',
            ],
            'summary_students_text' => [
                'label' => 'Summary card 2 — description',
                'type' => 'textarea',
                'default' => 'Providing direct access to quality learning resources and mentorship.',
            ],
            'summary_teachers_label' => [
                'label' => 'Summary card 3 — title',
                'type' => 'text',
                'default' => 'Teachers Trained',
            ],
            'summary_teachers_text' => [
                'label' => 'Summary card 3 — description',
                'type' => 'textarea',
                'default' => 'Empowering local educators with modern teaching methodologies.',
            ],
            'cta_title' => [
                'label' => 'Call-to-action heading',
                'type' => 'text',
                'default' => "Together, We're Building the Future.",
            ],
            'cta_text' => [
                'label' => 'Call-to-action paragraph',
                'type' => 'textarea',
                'default' => 'Every milestone represents another step toward equitable education and stronger communities.',
            ],
        ],
    ],
];

/**
 * Search & sharing text per page: [browser tab / Google title, one-line
 * description]. Also used for the Facebook / X / LinkedIn preview card.
 * These are the defaults (what each page printed before this became
 * editable); an edit in Page Content → Search & sharing overrides them.
 * Merged into every page's block list by blockRegistry() below.
 */
const SEO_PAGE_DEFAULTS = [
    'home' => ['Worldwide Education Foundation — Empowering Communities Through Education in Chitral', 'Worldwide Education Foundation (WEF) invests in the potential of children and youth across underserved communities in Chitral, Pakistan through early childhood development, teacher training, English language learning, and scholarships.'],
    'our-mission' => ['WEF At A Glance — Worldwide Education Foundation', 'Worldwide Education Foundation (WEF) At A Glance: Our Active Projects, Vision, Mission, and Beliefs.'],
    'our-team' => ['Our Team — Worldwide Education Foundation', 'Meet the Advisory Board Members and leadership team of the Worldwide Education Foundation.'],
    'programs' => ['Our Projects — Worldwide Education Foundation', 'Explore all 9 active projects by Worldwide Education Foundation — from Early Childhood Learning Centers to Teacher Training and Scholarships across Chitral, Pakistan.'],
    'bright-beginnings' => ['Bright Beginnings: Early Childhood Learning Centers — Worldwide Education Foundation', 'Bright Beginnings Early Childhood Learning Centers bring Montessori-based ECD to Chitral, Pakistan, led by trained women entrepreneurs.'],
    'learning-centers' => ['Government School Learning Centers — Worldwide Education Foundation', 'Integrating Early Childhood Development and Technology-Assisted English Language Labs into government primary schools across Chitral.'],
    'model-schools' => ['Government Model Schools — Worldwide Education Foundation', 'Transforming government primary school classrooms in Upper Chitral into welcoming, developmentally appropriate learning spaces.'],
    'language-labs' => ['In-School Language Labs — Worldwide Education Foundation', 'High-quality English language labs in low-income private schools across Chitral, strengthening students\' speaking and listening skills.'],
    'teacher-training' => ['English Language Skills Training for Teachers — Worldwide Education Foundation', 'A TEFL micro-credential certification program for teachers in the remote valley of Booni, Chitral, in partnership with Bridge Education Group.'],
    'research-hub' => ['Herzing–Daya Research & Learning Hub — Worldwide Education Foundation', 'Pakistan\'s first ECD research hub in a remote mountain region, hosted at the University of Chitral and led by WEF.'],
    'secondary-scholarships' => ['Secondary School Scholarships — Worldwide Education Foundation', 'Investing in Young Futures: removing financial barriers for underserved students in Upper Chitral to continue their education beyond Grade 8.'],
    'rising-stars' => ['AM Rising Stars Undergraduate Scholarship — Worldwide Education Foundation', 'Removing financial barriers to higher education for talented students from Tajikistan and Kyrgyzstan, funded by Sadiq Ali & Family.'],
    'skills-development' => ['Herzing Center for Skills, Innovation and Entrepreneurship — Worldwide Education Foundation', 'An integrated platform for professional development, teacher training, and workforce readiness hosted at the University of Chitral.'],
    'completed-projects' => ['Completed Projects — Worldwide Education Foundation', 'Explore the completed projects by Worldwide Education Foundation.'],
    'impact-goals' => ['Impact Goals — Worldwide Education Foundation', 'Our ten-year roadmap: growing from 10 to 1,200 learning centers, reaching 36,000 students and training 2,400 teachers and entrepreneurs by 2035.'],
    'gallery' => ['Gallery — Worldwide Education Foundation', 'Photo gallery showcasing moments of learning, growth, and transformation from WEF\'s projects across Chitral, Pakistan and Central Asia.'],
    'video-gallery' => ['Video Gallery — Worldwide Education Foundation', 'Watch videos of our projects and impact in action.'],
    'success-stories' => ['Success Stories — Worldwide Education Foundation', 'Read our success stories and see the real impact of our education initiatives.'],
    'annual-reports' => ['Annual Reports — Worldwide Education Foundation', 'Read our annual reports and see our financial and impact metrics.'],
    'contact' => ['Contact Us — Worldwide Education Foundation', 'Get in touch with Worldwide Education Foundation.'],
    'volunteer' => ['Volunteer — Worldwide Education Foundation', 'Volunteer with Worldwide Education Foundation.'],
    404 => [NULL, NULL],
];

/**
 * BLOCK_REGISTRY plus a "Search & sharing" group (seo_title +
 * seo_description) on each page listed in SEO_PAGE_DEFAULTS. Everything that
 * reads the registry goes through this, so the SEO fields show up in the
 * Page Content editor like any other block.
 */
function blockRegistry(): array
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $registry = BLOCK_REGISTRY;
    foreach (SEO_PAGE_DEFAULTS as $page => [$title, $description]) {
        if (!isset($registry[$page])) {
            continue;
        }
        $registry[$page]['blocks']['seo_title'] = [
            'label' => 'Page title (browser tab and Google)',
            'type' => 'text',
            'default' => $title,
            'group' => 'Search & sharing',
            'help' => 'Shown on the browser tab, as the headline in Google results and on link previews in Facebook, X, LinkedIn and WhatsApp. Aim for under 60 characters.',
        ];
        $registry[$page]['blocks']['seo_description'] = [
            'label' => 'Page description (Google and link previews)',
            'type' => 'text',
            'default' => $description,
            'group' => 'Search & sharing',
            'help' => 'The grey line under the title in Google results, and the text on link previews. Aim for 120-160 characters.',
        ];
    }

    return $registry;
}

/**
 * All stored overrides for a page, loaded once per request.
 *
 * @return array<string, string>
 */
function blockOverrides(string $page): array
{
    static $cache = [];

    if (!array_key_exists($page, $cache)) {
        try {
            $db = getDb();
            if ($db !== null) {
                $stmt = $db->prepare('SELECT block_key, value FROM content_blocks WHERE page = :page');
                $stmt->execute(['page' => $page]);
                $cache[$page] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } else {
                $cache[$page] = [];
            }
        } catch (Throwable $e) {
            $cache[$page] = [];
        }
    }

    return $cache[$page];
}

/**
 * The raw (unescaped) value of a block: the stored override if there is
 * one, otherwise the registry default. Returns '' for an unknown block
 * rather than throwing, so a typo in a template degrades to empty text
 * rather than a fatal error on a live page.
 */
function blockValue(string $page, string $key): string
{
    $overrides = blockOverrides($page);

    if (isset($overrides[$key]) && trim((string) $overrides[$key]) !== '') {
        return (string) $overrides[$key];
    }

    return (string) (blockRegistry()[$page]['blocks'][$key]['default'] ?? '');
}

/**
 * Escaped single-line block, for headings and short strings.
 * Use directly in templates: <h1><?= block('home', 'hero_title') ?></h1>
 */
function block(string $page, string $key): string
{
    return e(blockValue($page, $key));
}

/**
 * Escaped multi-line block with newlines turned into <br> — same
 * escape-then-convert order as richText(), so stored text can never
 * become live HTML.
 */
function blockRich(string $page, string $key): string
{
    return richText(blockValue($page, $key));
}

/**
 * URL of an 'image' block: the photo uploaded in Page Content, or the
 * picture the page shipped with (the block's default, a path under assets/).
 * An uploaded value is a path relative to admin/uploads/, never a URL typed
 * by someone, so it cannot point anywhere else.
 */
function blockImage(string $page, string $key): string
{
    $value = blockValue($page, $key);
    if ($value !== '' && !str_starts_with($value, 'assets/')
        && preg_match('#^[a-z0-9_-]+/[A-Za-z0-9_.-]+$#', $value)
        && is_file(__DIR__ . '/../public/admin/uploads/' . $value)) {
        return 'admin/uploads/' . $value;
    }

    return (string) (blockRegistry()[$page]['blocks'][$key]['default'] ?? '');
}

/**
 * Long-form page copy: paragraphs, bullet lists and subheadings.
 *
 * Why this exists. The program pages carry real articles — model-schools.php
 * alone has six paragraphs, a four-item list and an <h3>. blockRich() can only
 * turn newlines into <br>, so making that copy editable through it would have
 * flattened the structure into one grey slab. The alternative was a separate
 * registry block per paragraph, which means ten labelled textareas to edit one
 * article and a registry that has to change whenever someone adds a sentence.
 *
 * So this understands three marks, and deliberately no more:
 *
 *   blank line   starts a new paragraph
 *   - at line start   a bullet; consecutive bullets become one <ul>
 *   ## at line start  a subheading (<h3> — the pages' own level, since the
 *                     page title is the <h1> and the section the <h2>)
 *
 * It is NOT Markdown and NOT an HTML passthrough. Everything is escaped with
 * e() FIRST and structure is added afterwards, exactly as richText() does, so
 * text typed into the CMS can never become live HTML no matter what it
 * contains. There is no way to express a link, an image or a class here, which
 * is the point: this edits the words, not the design.
 */
function blockBody(string $page, string $key): string
{
    return renderBodyText(blockValue($page, $key));
}

/**
 * The renderer behind blockBody(), split out so it can be unit-checked and so
 * the admin preview can use it without going through the registry.
 */
function renderBodyText(?string $value): string
{
    $text = str_replace("\r\n", "\n", (string) $value);
    if (trim($text) === '') {
        return '';
    }

    $html = '';
    $listOpen = false;

    // Split on blank lines first: a paragraph is the unit people actually
    // think in, and lists live inside one of those units.
    foreach (preg_split('/\n\s*\n/', trim($text)) as $chunk) {
        $lines = preg_split('/\n/', trim($chunk)) ?: [];
        $paragraph = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '## ')) {
                $paragraph = flushParagraph($paragraph, $html);
                $html .= closeList($listOpen);
                $html .= '<h3>' . e(trim(substr($line, 3))) . '</h3>';
                continue;
            }

            if (str_starts_with($line, '- ')) {
                $paragraph = flushParagraph($paragraph, $html);
                if (!$listOpen) {
                    $html .= '<ul>';
                    $listOpen = true;
                }
                $html .= '<li>' . e(trim(substr($line, 2))) . '</li>';
                continue;
            }

            $html .= closeList($listOpen);
            // A soft line break inside a paragraph stays a line break, which
            // is what someone typing an address or a verse expects.
            $paragraph .= ($paragraph === '' ? '' : "<br>\n") . e($line);
        }

        flushParagraph($paragraph, $html);
    }

    $html .= closeList($listOpen);

    return $html;
}

/**
 * Emits a pending paragraph, if there is one, and returns the empty buffer.
 * Split out only because the three places that need it in renderBodyText()
 * were otherwise the same four lines each.
 */
function flushParagraph(string $paragraph, string &$html): string
{
    if ($paragraph !== '') {
        $html .= '<p>' . $paragraph . '</p>';
    }

    return '';
}

/** Closes an open <ul> and clears the flag, by reference. */
function closeList(bool &$listOpen): string
{
    if (!$listOpen) {
        return '';
    }

    $listOpen = false;

    return '</ul>';
}

/**
 * True when the block has an admin-set override (as opposed to falling
 * back to the registry default). Used by the admin UI to show which
 * blocks have been customised.
 */
function blockIsOverridden(string $page, string $key): bool
{
    $overrides = blockOverrides($page);

    return isset($overrides[$key]) && trim((string) $overrides[$key]) !== '';
}
