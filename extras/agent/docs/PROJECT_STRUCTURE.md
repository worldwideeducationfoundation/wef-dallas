# WEF (Worldwide Education Fund) Website & CMS - Complete File & Directory Structure

This document provides a comprehensive overview of the full directory structure, folders, files, assets (images, icons, SVGs, PDFs, maps), scripts, and configurations in the Worldwide Education Fund (WEF) website repository. Each item includes a description explaining its specific purpose and role within the application architecture.

> ## ⚠️ Layout changed — the document root is now `public/`
>
> Everything web-servable was moved into **`public/`**: the page templates, `assets/`, `css/`, `js/`, `api/`, `admin/`, `robots.txt`, `sitemap.xml` and the site `.htaccess`.
>
> **`includes/`, `config/`, `storage/`, `docs/` and `tools/` now sit *alongside* `public/`, not inside it.** They are not reachable over HTTP when the document root is set correctly — not merely denied by a rule, but absent from the served tree. That distinction is the point: the `.git` exposure found in the August 2026 audit happened precisely because a protection rule looked like it covered something and did not.
>
> A `Require all denied` `.htaccess` sits at the project root as insurance, so a deploy that aims the document root at the wrong directory fails closed instead of serving `config.php`.
>
> **Deployment:** point the domain's document root at `.../public`. Include paths in the moved files gained one `../`; `dirname(__DIR__, 2)` became `dirname(__DIR__, 3)` in `backup.php` and `restore.php`, which reach `storage/` outside the root.
>
> **Local development:** `http://localhost/WEF/` still works and always will. The project-root `.htaccess` forwards requests into `public/`, so the local URL behaves the way the production document root does — no `/public/` segment needed in links or bookmarks. Requests for `includes/`, `config/`, `storage/`, `docs/` and `tools/` are refused there rather than forwarded.
>
> The tree below still describes the *contents* of each folder accurately; read the top-level items marked `-> public/` as living under `public/`.

> ## 🧹 Cleanup — 2026-09-22
>
> The leftover root-level `assets/`, `css/`, `js/`, `admin/` and `api/`
> directories described in this document as living at the project root have
> been **removed** — they were stale, unreachable duplicates from before the
> `public/` restructure above (confirmed unreachable by the rewrite rule in
> the root `.htaccess`, and confirmed stale because their CSS had drifted out
> of sync with `public/css/`). The 25 generated `.html` mirrors at the project
> root and the 25 in `public/` (plus the Vercel deployment config that
> generated them) were also removed — deployment target changed from Vercel
> static export to **Hostinger PHP + MySQL**, serving `public/*.php` directly.
> All of it is recoverable from git history.
>
> **Read [`docs/CONTEXT.md`](docs/CONTEXT.md) for the current source of
> truth** — audience, conversion goals, verified defect list, and technical
> state. This document's *tree of `public/`'s contents* remains accurate; its
> description of the root level does not.

---

## 📁 Repository Overview Tree

```
WEF/
├── .htaccess                             # Main Apache server configuration & rewrite rules
├── .gitignore                            # Git file exclusion specifications
├── .editorconfig                         # Editor styling standards across environments
├── robots.txt                            # Search engine crawler directives
├── sitemap.xml                           # XML site map for SEO indexing
│
├── index.php                             # Main homepage template
├── our-mission.php                       # Mission, vision, core values, & history page
├── our-team.php                          # Board members, executive staff, & leadership page
├── active-projects.php                   # Active educational projects listing page
├── completed-projects.php                # Archive of completed projects & impact statistics
├── completed-project-details.php         # Detailed view template for specific completed projects
├── impact-goals.php                      # WEF core impact metrics, goals, & progress indicators
├── research-hub.php                      # Research publications, case studies, & reports repository
├── gallery.php                           # Media photo gallery page
├── video-gallery.php                     # Embedded video showcase page
├── annual-reports.php                    # Financial & narrative annual report downloads page
├── contact.php                           # User contact & inquiry form page
├── volunteer.php                         # Volunteer application & signup page
├── wef-ceo-presentation.html             # Standalone HTML interactive presentation for CEO
│
├── bright-beginnings.php                 # Program page: Early childhood education initiative
├── early-childhood.php                   # Program page: Early childhood development overview
├── language-labs.php                     # Program page: Digital & language laboratory initiatives
├── learning-centers.php                  # Program page: Community learning center networks
├── model-schools.php                     # Program page: Model school development & support
├── secondary-scholarships.php            # Program page: High school / secondary level scholarships
├── scholarships.php                      # Program page: General student scholarship grants
├── skills-development.php                # Program page: Vocational & digital skills training
├── teacher-training.php                  # Program page: Educator professional development
├── rising-stars.php                      # Program page: High-potential student support initiative
│
├── assets/                               # Static media assets container
│   ├── data/                             # GIS & Spatial Data Files
│   │   ├── chitral_border.geojson        # Chitral regional border boundaries for interactive map
│   │   └── locations_map1.geojson        # Project coordinates & marker metadata for map UI
│   ├── PDF/                              # Downloadable Document Storage
│   │   ├── WEF-at-a-Glance-Final-Updated.pdf
│   │   ├── Worldwide-Education-Fund-of-The-Dallas-Foundation_Narrative-report_July-2024.pdf
│   │   └── Worldwide-Education-Fund-of-The-Dallas-Foundation_Narrative-report_July-2025_FINAL.pdf
│   ├── icons/                            # Vector Graphics & Icon Resources
│   │   └── SVG/                          # Scalable Vector Graphics directory
│   │       ├── Facebook.svg              # Social media icon: Facebook
│   │       ├── Instagram.svg             # Social media icon: Instagram
│   │       ├── LinkedIn.svg              # Social media icon: LinkedIn
│   │       ├── X.svg                     # Social media icon: X (formerly Twitter)
│   │       ├── Youtube.svg               # Social media icon: YouTube
│   │       ├── LEARNING SPACES.svg       # Metric feature icon: Learning environments
│   │       ├── STUDENTS.svg              # Metric feature icon: Enrolled students count
│   │       ├── TEACHERS.svg              # Metric feature icon: Trained teachers count
│   │       ├── Years of Experience.svg   # Metric feature icon: Organizational tenure
│   │       └── Cart Icons/               # Program category SVG icons for cards & grids
│   │           ├── Diploma.svg           # Icon for certification & graduation programs
│   │           ├── Investement.svg       # Icon for financial investment & funding
│   │           ├── Lab.svg               # Icon for computer & science laboratories
│   │           ├── Location.svg          # Icon for regional project locations
│   │           ├── Research.svg          # Icon for research hub & publications
│   │           ├── Scholarship.svg       # Icon for financial aid & scholarships
│   │           ├── Schools.svg           # Icon for partner school institutions
│   │           ├── Students.svg          # Icon for student outreach programs
│   │           └── Youth.svg             # Icon for youth empowerment programs
│   └── images/                           # Web Image Assets & Banners
│       ├── logo.svg                      # Primary WEF official SVG vector logo
│       ├── global-map.png                # World graphic visualization background
│       ├── project-advisory.png          # High-resolution card image for Advisory projects
│       ├── project-gift.png              # High-resolution card image for Gift/Donation initiatives
│       ├── project-helping-hands.png     # High-resolution card image for Helping Hands projects
│       ├── project-investment.png        # High-resolution card image for Infrastructure investment
│       ├── project-isolated.png          # High-resolution card image for Remote area outreach
│       ├── project-spaces.png            # High-resolution card image for Learning spaces
│       ├── mobile-portrait/              # Reserved folder for mobile-optimized portrait photos
│       ├── partners/                     # Institutional & Academic Partner Logos
│       │   ├── american-spaces-tajikistan.png
│       │   ├── auca.png                  # American University of Central Asia logo
│       │   ├── fes.png                   # Friedrich Ebert Stiftung logo
│       │   ├── kpesed.png                # KP Elementary & Secondary Education Dept logo
│       │   ├── university-of-central-asia.png
│       │   └── university-of-chitral.png # University of Chitral partner logo
│       └── programs/                     # Program Feature & Hero Banner WebP/JPG Images
│           ├── bright-beginnings-01.webp
│           ├── language-labs-01.jpg
│           ├── learning-centers-01.jpg
│           ├── model-schools-01.jpg
│           ├── research-hub-01.jpg
│           ├── rising-stars-01.jpg
│           ├── secondary-scholarships-01.jpg
│           ├── skills-development-01.webp
│           └── teacher-training-01.webp
│
├── css/                                  # Frontend Style Sheets
│   ├── styles.css                        # Primary Global CSS stylesheet (design system, variables, layouts)
│   ├── impact-goals-new.css              # Custom styling for Impact Goals interactive component
│   └── uiverse-cards.css                 # Special UI component styling for animated card widgets
│
├── js/                                   # Frontend JavaScript Scripts
│   ├── main.js                           # Primary application JS (navigation, modals, form handlers, animations)
│   ├── map.js                            # GeoJSON interactive Leaflet map logic & layer handlers
│   └── impact-goals-new.js               # Interactive tabs, filters, & counters for Impact Goals
│
├── includes/                             # PHP Backend Application Includes & Helpers
│   ├── .htaccess                         # Directory access restriction settings
│   ├── bootstrap.php                     # Central initialization script (autoloaders, constants, sessions)
│   ├── db.php                            # Database Connection Handler (PDO Singleton)
│   ├── database.php                      # Legacy database connection wrapper
│   ├── environment.php                   # Environment variable loader (.env processing)
│   ├── content.php                       # Content fetching & formatting helper functions
│   ├── content-blocks.php                # Reusable HTML content rendering blocks
│   ├── completed-projects-data.php       # Static & dynamic datasets for completed projects
│   ├── form-submissions.php              # Form processor for contact and volunteer requests
│   └── layout/                           # Global Frontend Header/Footer Layout Templates
│       ├── site-header.php               # Global navigation header template
│       ├── footer-full.php               # The site footer — used by every page
│       ├── head-meta.php                 # HTML <head> meta tags, OpenGraph metadata, & favicon links
│       └── not-found.php                 # Custom 404 error page template
│
├── admin/                                # Custom CMS Content Management System
│   ├── .htaccess                         # Admin route protection & security rules
│   ├── .gitignore                        # Admin temporary file exclusion specs
│   ├── index.php                         # CMS entry router (redirects to login/dashboard)
│   ├── login.php                         # Admin authentication login page
│   ├── logout.php                        # Session destruction & logout handler
│   ├── dashboard.php                     # CMS Overview dashboard with activity & metrics
│   ├── account.php                       # Admin profile & password management page
│   ├── assets/                           # CMS Dashboard Custom Styling & Scripts
│   │   ├── css/
│   │   │   └── admin.css                 # Main CSS stylesheet for admin dashboard UI
│   │   └── js/
│   │       └── admin.js                  # Main JS for admin UI interactions, AJAX, & modals
│   ├── database/                         # CMS Database Schemas, Migrations & Management
│   │   ├── .htaccess                     # Protects DB scripts from direct external web access
│   │   ├── schema.sql                    # Main SQL Database schema file (tables, keys, indexes)
│   │   ├── migrate.php                   # Database migration execution script
│   │   ├── backup.php                    # Automated database backup generator
│   │   ├── restore.php                   # Database restore utility from backup SQL files
│   │   ├── create-admin.php              # CLI/Web tool to seed administrative user accounts
│   │   ├── backfill-team-sections.php    # Data migration script to organize team sections
│   │   └── prune-logs.php                # Utility to clean up expired system/activity logs
│   ├── includes/                         # CMS Internal PHP Helpers & Core Modules
│   │   ├── .htaccess                     # Restricted access setting
│   │   ├── bootstrap.php                 # Admin environment initializer & session check
│   │   ├── auth.php                      # Authentication, privilege control & session verification
│   │   ├── listing.php                   # Dynamic table listing & pagination generator
│   │   ├── bulk.php                      # Bulk action handler (delete, status updates)
│   │   ├── trash.php                     # Soft deletion & recycle bin management
│   │   ├── upload.php                    # File/image upload validator & storage handler
│   │   ├── reorder.php                   # AJAX reordering endpoint for drag-and-drop items
│   │   ├── functions.php                 # General admin utility functions
│   │   ├── errors.php                    # Admin error logging and display helper
│   │   ├── csrf.php                      # Anti-CSRF token generation & validation
│   │   ├── media-specs.php               # Image dimension & file type constraint rules
│   │   ├── layout-header.php             # Admin panel top bar & sidebar navigation template
│   │   └── layout-footer.php             # Admin panel footer template
│   ├── modules/                          # CMS Functional Modules (CRUD controllers)
│   │   ├── activity/                     # Module: User & admin activity logging
│   │   ├── gallery/                      # Module: Photo gallery management
│   │   ├── media/                        # Module: Media library file manager
│   │   ├── pages/                        # Module: Dynamic web page content editor
│   │   ├── programs/                     # Module: Educational program management
│   │   ├── reports/                      # Module: PDF & annual report management
│   │   ├── settings/                     # Module: Global site setting parameters
│   │   ├── sponsors/                     # Module: Partner & sponsor logo management
│   │   ├── submissions/                  # Module: Contact & volunteer form submission viewer
│   │   ├── team/                         # Module: Team member profile management
│   │   ├── trash/                        # Module: Recycled/deleted items management
│   │   ├── updates/                      # Module: News & project updates editor
│   │   └── users/                        # Module: CMS Administrator user accounts management
│   ├── logs/                             # Admin audit & error log directory
│   └── uploads/                          # User-uploaded images & document media storage
│
├── api/                                  # Public REST API Endpoints
│   ├── index.php                         # API status and discovery endpoint
│   └── contact.php                       # Asynchronous AJAX contact form endpoint
│
├── config/                               # Application Configuration Files
│   ├── .htaccess                         # Protects configuration files from public URL requests
│   ├── config.php                        # Production database credentials & site settings
│   └── config.sample.php                 # Template configuration file for development setup
│
├── docs/                                 # Technical Architecture & Developer Documentation
│   ├── PROJECT_DETAILS.md                # Overview of project scope and requirements
│   ├── WEF_WEBSITE_ANALYSIS_REPORT.md    # Code audit and website analysis report
│   ├── architecture.md                   # System design, data flow, & architectural specs
│   ├── database.md                       # Complete ER diagram & database schema documentation
│   ├── frontend.md                       # Frontend UI guidelines, CSS variables, & responsive rules
│   ├── admin-panel.md                    # Admin CMS user manual and module walkthrough
│   ├── admin-ui-design.md                # UI/UX design specifications for the CMS panel
│   ├── cms-coverage.md                   # Audit of dynamic content coverage in the CMS
│   ├── security.md                       # Security policies, CSRF, sanitization, & auth docs
│   └── wef-context.md                    # Organizational context and mission background for WEF
│
├── storage/                              # Application Runtime Storage Directory
│   ├── .htaccess                         # Direct download restrictions
│   ├── applications/                     # Stored volunteer & scholarship application files
│   ├── backups/                          # Encrypted automated SQL backups
│   └── logs/                             # Server execution & error log files
│
├── tools/                                # Command-Line & Automation Utilities
│   ├── count_code.py                     # Python script to analyze code line counts & metrics
│   └── security_scan.py                  # Python script to run security & vulnerability audits
│
└── extras/                               # Not part of the running site — see extras/README.md
    ├── README.md                         # What each file is, and why it was kept
```

---

## 🔍 Detailed Component & Asset Descriptions

### 1. Root Pages (`/`)
* **`index.php`**: The primary home page. Contains hero video/banner, interactive statistics, impact map, program previews, partner carousel, and call-to-action buttons.
* **`our-mission.php`**: Explains WEF’s history, vision, core values, and educational philosophy.
* **`our-team.php`**: Displays board members, executive leadership, regional managers, and advisors with bio modals.
* **`active-projects.php`**: Highlights ongoing educational projects with real-time status and funding progress.
* **`completed-projects.php`**: Showcase archive of past successful projects, impact numbers, and beneficiary statistics.
* **`completed-project-details.php`**: Dynamic page template rendering detailed case studies for individual completed projects.
* **`impact-goals.php`**: Interactive metrics page displaying progress toward WEF's long-term educational impact milestones.
* **`research-hub.php`**: Repository of academic research papers, evaluation reports, and whitepapers funded or published by WEF.
* **`gallery.php`**: Image gallery featuring high-resolution photos of students, schools, teacher workshops, and facilities.
* **`video-gallery.php`**: Video showcase page embedding documentary clips, interview videos, and school ground tours.
* **`annual-reports.php`**: Download center for official narrative and audited financial annual reports (PDF format).
* **`contact.php`**: Contact page with location address, interactive map, social links, and an inquiry form.
* **`volunteer.php`**: Application page for individuals and organizations interested in volunteering or mentoring.
* **`wef-ceo-presentation.html`**: An interactive HTML slide deck designed specifically for executive presentations to stakeholders.

### 2. Program Pages (`/`)
* **`bright-beginnings.php`**: Details early childhood care and pre-school foundational education initiatives.
* **`early-childhood.php`**: Overview of early childhood development strategies, nutritional support, and play-based learning.
* **`language-labs.php`**: Focuses on English and regional language acquisition through modern audio-visual equipment.
* **`learning-centers.php`**: Information on community-based learning hubs providing after-school tutoring and digital access.
* **`model-schools.php`**: Showcases public/private school partnerships transformed into state-of-the-art model schools.
* **`secondary-scholarships.php`**: Financial assistance programs designed to prevent secondary school dropouts among underprivileged youth.
* **`scholarships.php`**: Comprehensive scholarship programs covering tuition, textbooks, and supplies for students across all grades.
* **`skills-development.php`**: Vocational training programs focusing on IT literacy, digital skills, coding, and handicrafts.
* **`teacher-training.php`**: Professional development courses, pedagogy workshops, and certifications for rural educators.
* **`rising-stars.php`**: Specialized talent incubator program supporting exceptionally high-performing students.

---

### 3. Assets Directory (`assets/`)

#### A. GIS Data (`assets/data/`)
* **`chitral_border.geojson`**: Coordinates defining the geographical polygon boundaries of Chitral, Pakistan for Leaflet/Mapbox rendering.
* **`locations_map1.geojson`**: GeoJSON data containing point markers, popup text, student count, and status metadata for WEF school locations on the interactive map.

#### B. Downloadable Documents (`assets/PDF/`)
* **`WEF-at-a-Glance-Final-Updated.pdf`**: Summary brochure outlining key organizational metrics, mission statement, and global footprint.
* **`Worldwide-Education-Fund-of-The-Dallas-Foundation_Narrative-report_July-2024.pdf`**: Official 2024 narrative report submitted to stakeholders detailing annual achievements.
* **`Worldwide-Education-Fund-of-The-Dallas-Foundation_Narrative-report_July-2025_FINAL.pdf`**: Official 2025 narrative report detailing updated metrics, financial summaries, and expansion goals.

#### C. Vector Icons & SVGs (`assets/icons/SVG/`)
##### Social Media SVGs
* **`Facebook.svg`**: Facebook brand icon for social links and footer integration.
* **`Instagram.svg`**: Instagram brand icon for social media feed links.
* **`LinkedIn.svg`**: LinkedIn brand icon for corporate networking links.
* **`X.svg`**: X (Twitter) brand icon for updates.
* **`Youtube.svg`**: YouTube brand icon for video channel links.

##### Metric Feature SVGs
* **`LEARNING SPACES.svg`**: Illustrative vector icon for physical classroom and school building count metrics.
* **`STUDENTS.svg`**: Illustrative vector icon representing total enrolled student reach.
* **`TEACHERS.svg`**: Illustrative vector icon representing trained educator statistics.
* **`Years of Experience.svg`**: Illustrative vector icon representing organizational tenure and history.

##### Card Category SVGs (`assets/icons/SVG/Cart Icons/`)
* **`Diploma.svg`**: Graduation cap/diploma icon used on education certification cards.
* **`Investement.svg`**: Financial growth chart icon used on donation and project funding cards.
* **`Lab.svg`**: Flask/microscope icon used on science and language lab program cards.
* **`Location.svg`**: Map pin icon used for location filter cards and map tooltips.
* **`Research.svg`**: Document magnifying glass icon used for research hub cards.
* **`Scholarship.svg`**: Financial aid ribbon icon used on scholarship program cards.
* **`Schools.svg`**: School building icon used on model school cards.
* **`Students.svg`**: Student silhouette icon used on beneficiary cards.
* **`Youth.svg`**: Energetic youth vector icon used on skills development cards.

#### D. Image Assets (`assets/images/`)
##### Root Web Images
* **`logo.svg`**: Official primary vector logo of the Worldwide Education Fund.
* **`global-map.png`**: High-resolution world map graphic used in background sections.
* **`project-advisory.png`**: High-resolution thumbnail image for advisory board and mentorship initiatives.
* **`project-gift.png`**: High-resolution thumbnail image for donation programs and gift matching.
* **`project-helping-hands.png`**: High-resolution thumbnail image for community volunteer programs.
* **`project-investment.png`**: High-resolution thumbnail image for infrastructure investment projects.
* **`project-isolated.png`**: High-resolution thumbnail image for remote and isolated village education projects.
* **`project-spaces.png`**: High-resolution thumbnail image for classroom transformation projects.

##### Partner Logos (`assets/images/partners/`)
* **`american-spaces-tajikistan.png`**: Logo of American Spaces Tajikistan.
* **`auca.png`**: Logo of American University of Central Asia.
* **`fes.png`**: Logo of Friedrich Ebert Stiftung.
* **`kpesed.png`**: Logo of Khyber Pakhtunkhwa Elementary & Secondary Education Department.
* **`university-of-central-asia.png`**: Logo of University of Central Asia.
* **`university-of-chitral.png`**: Logo of University of Chitral.

##### Program Banner Images (`assets/images/programs/`)
* **`bright-beginnings-01.webp`**: Hero background graphic for Bright Beginnings program.
* **`language-labs-01.jpg`**: Hero background graphic for Language Labs program.
* **`learning-centers-01.jpg`**: Hero background graphic for Learning Centers program.
* **`model-schools-01.jpg`**: Hero background graphic for Model Schools program.
* **`research-hub-01.jpg`**: Hero banner for Research Hub repository.
* **`rising-stars-01.jpg`**: Hero background graphic for Rising Stars scholarship program.
* **`secondary-scholarships-01.jpg`**: Hero graphic for Secondary Scholarships page.
* **`skills-development-01.webp`**: Hero background graphic for Skills Development initiative.
* **`teacher-training-01.webp`**: Hero background graphic for Teacher Training initiative.

---

### 4. Stylesheets (`css/`)
* **`styles.css`**: Master CSS stylesheet containing design system tokens (colors, typography, grid layouts, mobile responsive breakpoints, utility classes, and global animations).
* **`impact-goals-new.css`**: Dedicated component stylesheet for the interactive tabbed Impact Goals dashboard.
* **`uiverse-cards.css`**: Specialized card component styling providing glassmorphism and modern UI hover effects.

---

### 5. JavaScript Scripts (`js/`)
* **`main.js`**: Core frontend script handling mobile drawer menus, smooth scrolling, modal dialogs, lazy image loading, accordion menus, and form submissions.
* **`map.js`**: Map rendering script leveraging Leaflet.js to load GeoJSON border polygons (`chitral_border.geojson`) and point markers (`locations_map1.geojson`) with custom popups.
* **`impact-goals-new.js`**: Dynamic script powering counter animations, tab switching, and data filtering on `impact-goals.php`.

---

### 6. Application Core & Layout (`includes/`)
* **`bootstrap.php`**: Main application bootstrap file initializing session handling, constants, error handlers, and helper loading.
* **`db.php`**: PDO-based Database Singleton connector establishing secure connection parameters with MySQL.
* **`database.php`**: Legacy database helper maintaining backward compatibility for queries.
* **`environment.php`**: Secure loader for environment variables (DB host, credentials, API keys).
* **`content.php`**: Helper library for sanitizing, formatting, and retrieving dynamic DB content.
* **`content-blocks.php`**: Reusable component rendering functions (cards, sliders, banner blocks).
* **`completed-projects-data.php`**: PHP array data structure containing fallback project information.
* **`form-submissions.php`**: Form processing engine that validates inputs and saves messages to the database.

#### Layout Partials (`includes/layout/`)
* **`site-header.php`**: Primary navigation header with logo, mega-menu, search, and language dropdowns.
* **`footer-full.php`**: The single site footer, used by all 24 public pages and the 404 template — branding, quick links, newsletter signup, the 501(c)(3) legal statement and social links. Previously one of two: `footer-slim.php` (a copyright line plus social icons) was on 22 pages and has been removed, because two footers meant two places to update a link and one of them always being missed.
* **`head-meta.php`**: Universal HTML header block managing charset, viewport, SEO title/description tags, OpenGraph social cards, and favicon references.
* **`not-found.php`**: Custom 404 page template rendering when a requested URL is not found.

---

### 7. Custom CMS Admin Panel (`admin/`)

#### Core Admin Pages
* **`admin/index.php`**: Entry point router checking authentication state and directing users to `dashboard.php` or `login.php`.
* **`admin/login.php`**: Secure admin authentication login interface with brute-force rate-limiting and CSRF checks.
* **`admin/logout.php`**: Safely terminates the admin session and destroys session cookies.
* **`admin/dashboard.php`**: Central admin command center showing content statistics, recent activity logs, and quick actions.
* **`admin/account.php`**: Allows logged-in administrators to update personal profile information and change passwords securely.

#### Admin Styling & Scripts (`admin/assets/`)
* **`admin/assets/css/admin.css`**: Custom styling for the admin sidebar, data tables, form elements, modalless views, and dashboard widgets.
* **`admin/assets/js/admin.js`**: JS logic powering admin interactivity, AJAX table operations, live searches, bulk selection checkboxes, and file dropzone uploaders.

#### Admin Core Helpers (`admin/includes/`)
* **`admin/includes/auth.php`**: Enforces user authentication, role-based access permissions, and session timeouts.
* **`admin/includes/bootstrap.php`**: Initializes admin-specific environment settings and dependencies.
* **`admin/includes/listing.php`**: Automates table rendering, sorting columns, search filtering, and pagination across all CMS modules.
* **`admin/includes/bulk.php`**: Batch processing utility handling multi-item deletions and batch status changes.
* **`admin/includes/trash.php`**: Soft delete management handler allowing items to be restored or permanently purged.
* **`admin/includes/upload.php`**: Secure file upload handler validating MIME types, file sizes, image resizing, and disk storage locations.
* **`admin/includes/reorder.php`**: AJAX endpoint allowing drag-and-drop ordering of list items.
* **`admin/includes/functions.php`**: Helper functions for string formatting, date parsing, and URL slug generation.
* **`admin/includes/errors.php`**: Standardized error logging and alert rendering component.
* **`admin/includes/csrf.php`**: Generates and verifies anti-CSRF security tokens for form submissions.
* **`admin/includes/media-specs.php`**: Defines required image aspect ratios, dimensions, and file format constraints for uploaded media.
* **`admin/includes/layout-header.php`**: Top bar header and sidebar navigation template for the CMS interface.
* **`admin/includes/layout-footer.php`**: Footer template for the CMS interface.

#### Admin Database Utilities (`admin/database/`)
* **`admin/database/schema.sql`**: Full MySQL database schema creation script (tables, primary/foreign keys, indexes).
* **`admin/database/migrate.php`**: Automated schema migration runner for updating production databases without data loss.
* **`admin/database/backup.php`**: Creates downloadable or stored SQL backups of the database.
* **`admin/database/restore.php`**: Restores the database from a pre-existing SQL backup file.
* **`admin/database/create-admin.php`**: Secure utility to generate new admin accounts with password hashing.
* **`admin/database/backfill-team-sections.php`**: Migration helper for organizing team members into distinct organizational categories.
* **`admin/database/prune-logs.php`**: Automated cleanup script for purging old audit and system log records.

#### CMS Functional Modules (`admin/modules/`)
* **`activity/`**: View audit logs tracking admin logins, file changes, and data modifications.
* **`gallery/`**: Upload, tag, and arrange photo gallery albums displayed on the public site.
* **`media/`**: Complete media file browser for organizing uploaded images, videos, and PDFs.
* **`pages/`**: Edit editable content regions and meta tags across various public website pages.
* **`programs/`**: Create, edit, and manage educational program pages, impact figures, and banner images.
* **`reports/`**: Upload and manage annual reports, narrative PDFs, and financial audit files.
* **`settings/`**: Configure site-wide settings (contact email, social media links, site title, emergency banners).
* **`sponsors/`**: Add and update organizational sponsor and institutional partner logos.
* **`submissions/`**: View and respond to submitted user contact messages and volunteer applications.
* **`team/`**: Manage team member bios, designations, photos, and display order.
* **`trash/`**: Recycle bin interface for inspecting soft-deleted items before restoring or purging.
* **`updates/`**: Publish news updates, blog posts, and field stories.
* **`users/`**: Administrator user management module (add/edit admin accounts and role levels).

---

### 8. API & Config (`api/` & `config/`)
* **`api/index.php`**: Returns API metadata, status ping, and version information in JSON format.
* **`api/contact.php`**: RESTful endpoint processing contact form submissions sent via JavaScript AJAX requests.
* **`config/config.php`**: Production configuration file defining database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`), base URL, and debug settings.
* **`config/config.sample.php`**: Sanitized example template provided for setting up local development environments.

---

### 9. Developer Documentation (`docs/`)
* **`docs/PROJECT_DETAILS.md`**: Summary of project scope, core objectives, and project metadata.
* **`docs/WEF_WEBSITE_ANALYSIS_REPORT.md`**: In-depth analysis of code quality, performance benchmarks, and recommendations.
* **`docs/architecture.md`**: Architectural blueprint detailing backend data flows, layout pipelines, and directory structures.
* **`docs/database.md`**: Complete database documentation including table schemas, relationships, and data dictionary.
* **`docs/frontend.md`**: Style guide detailing CSS design system variables, UI components, typography scales, and responsive design guidelines.
* **`docs/admin-panel.md`**: Comprehensive administration guide detailing CMS features and content editing procedures.
* **`docs/admin-ui-design.md`**: Interface design principles, color palettes, and component layouts for the admin dashboard.
* **`docs/cms-coverage.md`**: Audit matrix mapping public website content regions to their managing CMS modules.
* **`docs/security.md`**: Security architecture document covering password hashing, CSRF prevention, SQL injection protection, XSS sanitization, and upload validation.
* **`docs/wef-context.md`**: Mission context, terminology, and background information regarding the Worldwide Education Fund.

---

### 10. Maintenance & Storage (`storage/` & `tools/`)
* **`storage/applications/`**: Encrypted/secure directory storing applicant file submissions.
* **`storage/backups/`**: Storage location for server-generated database SQL backup dumps.
* **`storage/logs/`**: Log files capturing runtime PHP errors and system exceptions.
* **`tools/count_code.py`**: Python script to analyze line counts across PHP, JS, CSS, and Markdown files.
* **`tools/security_scan.py`**: Python script to scan the codebase for potential security vulnerabilities and exposed keys.
