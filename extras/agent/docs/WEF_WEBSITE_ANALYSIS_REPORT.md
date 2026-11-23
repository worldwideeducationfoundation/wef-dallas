# Worldwide Education Foundation (WEF) — Comprehensive Website Architecture & Theme Analysis Report

---

## 1. Executive Summary & Website Nature

### Organization & Mission
The **Worldwide Education Foundation (WEF)** website is a digital platform for a 501(c)(3) non-profit organization dedicated to empowering children, youth, and communities through education. The foundation operates primarily in Central and South Asia, with a strong focus on mountain communities in **Chitral, Pakistan**.

* **Core Focus Areas**: Early Childhood Development (ECD), Teacher Professional Training, English Language Learning, Secondary Scholarships, Model Schools, and Community Learning Centers.
* **Website Purpose**: Serve as an impactful presentation and storytelling hub for donors, institutional partners, volunteers, and educators, while providing a modern content management backend for non-technical administrators.
* **Brand Persona & Tone**: Authoritative, editorial, hopeful, transparent, and impact-driven.

---

## 2. Technology Stack & System Architecture

```
[ Frontend Client ]                    [ Web Server Layer ]                [ Database Layer ]
-------------------                    --------------------                ------------------
HTML5 + Vanilla CSS3                   Apache / XAMPP (Local)              MySQL / MariaDB
Vanilla JS ES6+                        PHP 8+ Runtime                      wef_admin Database
Leaflet.js Maps                        PDO Database Helper                 utf8mb4_unicode_ci
Google Fonts (Inter / Jakarta)         Custom Admin CMS Suite
```

### Core Technologies
* **Frontend Structure**: Semantic HTML5 augmented with dynamic PHP rendering across 18+ modular pages (e.g., [index.php](file:///c:/xampp/htdocs/WEF/index.php), [our-mission.php](file:///c:/xampp/htdocs/WEF/our-mission.php), [programs.php](file:///c:/xampp/htdocs/WEF/programs.php), [gallery.php](file:///c:/xampp/htdocs/WEF/gallery.php)).
* **Styling Engine**: Pure Vanilla CSS3 with zero reliance on framework dependencies (No Tailwind CSS or Bootstrap bloat). Styling is centralized in [css/styles.css](file:///c:/xampp/htdocs/WEF/css/styles.css).
* **Client-Side Scripts**: Modern Vanilla JS (ES6+) structured across modular functions in [js/main.js](file:///c:/xampp/htdocs/WEF/js/main.js) and [js/map.js](file:///c:/xampp/htdocs/WEF/js/map.js).
* **Backend Runtime**: PHP 8+ handling request routing, session authentication, content block injection via [includes/content-blocks.php](file:///c:/xampp/htdocs/WEF/includes/content-blocks.php), and database operations.
* **Database Driver**: PHP Data Objects (PDO) in [includes/database.php](file:///c:/xampp/htdocs/WEF/includes/database.php) using prepared statements exclusively.
* **Mapping Engine**: [Leaflet.js](https://leafletjs.com/) with dual-tile support (Carto Voyager street tiles and Esri Satellite imagery).

---

## 3. UI / UX Design System & Visual Elements

### A. Color Tokens & Palette
Defined as CSS Custom Properties in [css/styles.css](file:///c:/xampp/htdocs/WEF/css/styles.css#L10-L51):

| Token Name | Hex Code | Purpose & UX Context |
| :--- | :--- | :--- |
| `--clr-primary` | `#0e611d` | Deep natural green representing growth, sustainability, and trust |
| `--clr-primary-dark` | `#1f4f29` | Hover states, dark card accents |
| `--clr-primary-lighter` | `#e5f2e8` | Subtly tinted background cards, badge backgrounds |
| `--clr-accent` | `#c4691c` | Warm editorial orange for high-priority CTAs and highlights |
| `--clr-accent-light` | `#e69149` | Secondary hover triggers and active tab accents |
| `--clr-cream` | `#fbfaf8` | Warm editorial background tinting |
| `--clr-gray-600` | `#525862` | Optimized body text color guaranteeing **4.9:1 WCAG AA contrast ratio** |

### B. Typography System
* **Primary Headings (`--ff-heading`)**: `Plus Jakarta Sans` (weights 400 to 800). Provides geometric clarity and modern editorial authority.
* **Body & UI Text (`--ff-body`)**: `Inter` (weights 300 to 600). Designed for high legibility across all screen sizes.
* **Fluid Scale**: Spanning `--fs-xs` (`0.75rem`) to `--fs-5xl` (`5.5rem`).

### C. Design Principles & Motion Aesthetics
* **Glassmorphism**: Header navigation ([main-header](file:///c:/xampp/htdocs/WEF/css/styles.css#L70)) and floating control cards use `backdrop-filter: blur(12px)` combined with subtle white border overlays (`rgba(255, 255, 255, 0.15)`).
* **Elevation Shadows**: Layered elevations using CSS variables (`--shadow-sm` through `--shadow-xl`) for soft, natural depth.
* **Cubic-Bezier Animations**: Smooth transitions using `--ease-smooth: cubic-bezier(0.2, 0.8, 0.2, 1)` and spring physics for hover states.
* **Scroll-Triggered Reveals**: `IntersectionObserver` in [js/main.js](file:///c:/xampp/htdocs/WEF/js/main.js#L9) handles progressive fade-up reveals as the user scrolls down the page.

### D. Core Interactive UI Components
1. **Sticky Header & Mobile Menu**: Glassmorphism navbar with sticky scroll triggers and dynamic section highlighting.
2. **Interactive GIS Map**: Custom Leaflet markers categorized by school type (Entrepreneurial, Government, Private, Elementary) with modal popups in [js/map.js](file:///c:/xampp/htdocs/WEF/js/map.js#L18-L47).
3. **Dynamic Statistics Counters**: Animated count-up numbers triggered upon scroll (e.g., number of students reached, schools established).
4. **Lightbox Photo & Video Gallery**: Full-screen modal overlay for high-resolution project images and media.
5. **Impact Goals Chart**: Interactive tabs and visual progress displays on specialized program pages.

---

## 4. Database Architecture & Data Model

The application utilizes a **MySQL / MariaDB** database named `wef_admin` configured with `utf8mb4` encoding and `utf8mb4_unicode_ci` collation.

### Schema Blueprint ([admin/database/schema.sql](file:///c:/xampp/htdocs/WEF/admin/database/schema.sql) & [admin/database/migrate.php](file:///c:/xampp/htdocs/WEF/admin/database/migrate.php))

```
                              +--------------------+
                              |    admin_users     |
                              +--------------------+
                                 |              |
           +---------------------+              +---------------------+
           | (1:N)                                                    | (1:N)
           v                                                          v
+--------------------+                                    +--------------------+
|   activity_logs    |                                    |   content_blocks   |
+--------------------+                                    +--------------------+

+--------------------+        +--------------------+      +--------------------+
|  sponsor_groups    |---(1:N)|      sponsors      |      |   annual_reports   |
+--------------------+        +--------------------+      +--------------------+

+--------------------+        +--------------------+      +--------------------+
|      programs      |        |    team_members    |      |   gallery_items    |
+--------------------+        +--------------------+      +--------------------+

+--------------------+        +--------------------+      +--------------------+
|   login_attempts   |        |   site_settings    |      |  project_updates   |
+--------------------+        +--------------------+      +--------------------+
```

### Table Specifications

1. **`admin_users`**: Stores CMS administrative accounts. Supports RBAC roles (`super_admin`, `administrator`, `editor`), bcrypt password hashes, and login timestamps.
2. **`login_attempts`**: Security tracking table capturing IP addresses and timestamps for both valid and failed logins to prevent email enumeration and brute-force attacks.
3. **`activity_logs`**: Comprehensive audit log capturing actions performed in the admin panel with user ID, module name, client IP, and browser User-Agent.
4. **`programs`**: Manages all educational projects (e.g., Early Childhood Development, Teacher Training). Stores slugs, summaries, descriptions, key metrics (`stat1_value`, `stat1_label`, etc.), and image paths.
5. **`team_members`**: Manages board members, advisors, and staff profiles (name, role, section, bio, photo).
6. **`sponsor_groups` & `sponsors`**: Two-tier structure managing the Honor Board and major donors. `sponsors` maintains a `FOREIGN KEY` to `sponsor_groups` with `ON DELETE CASCADE`.
7. **`gallery_items`**: Manages photo gallery items, display order, and image captions/alt-text.
8. **`annual_reports`**: PDF document manager storing cover image paths, PDF file locations, and release descriptions.
9. **`site_settings`**: Singleton table enforced by a `CHECK (id = 1)` constraint to guarantee site-wide contact email, phone, address, and social media handles.
10. **`content_blocks`**: Stores customizable text copy for non-entity site sections (page intros, mission statements) linked to an authoring admin user ID.
11. **`project_updates`**: Dynamic news/blog articles with status management (`draft`/`active`).

### Database Integrity & Performance Features
* **Soft Deletes**: Tables (`programs`, `team_members`, `gallery_items`, `annual_reports`, `sponsors`, `sponsor_groups`, `project_updates`) utilize a `deleted_at DATETIME` column, enabling a Trash/Recovery feature in the CMS.
* **Performance Indexing**: Includes composite secondary indexes (such as `idx_programs_listing` on `(category, display_order, name)` and `idx_activity_module_time` on `(module, created_at)`) to eliminate MySQL filesort operations.

---

## 5. Security, Performance & Code Standards

### Security Safeguards
* **SQL Injection Immunity**: All database access flows through `getDb()->prepare()` in [includes/database.php](file:///c:/xampp/htdocs/WEF/includes/database.php) with strict PDO execution flags.
* **Authentication Security**: Rate-limiting throttles requests per IP/email combo. Sessions are secured with HttpOnly and secure cookie settings.
* **Content Security Policy (CSP)**: Strict headers configured in `.htaccess` and HTML metadata.

### Performance & Asset Optimization
* **Vanilla Architecture**: High Lighthouse performance scores due to zero framework loading overhead.
* **Optimized Media Assets**: Progressive `.webp` image formats and native `loading="lazy"` attributes across all program listings.
* **Idempotent Database Migrations**: The migration script [admin/database/migrate.php](file:///c:/xampp/htdocs/WEF/admin/database/migrate.php) works seamlessly on both MariaDB (XAMPP) and MySQL 8 (production servers).
