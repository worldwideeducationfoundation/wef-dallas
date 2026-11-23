<?php
/**
 * Site-wide 404 handler: public/.htaccess rewrites any path that does not exist
 * here (rule 4).
 *
 * includes/layout/not-found.php has existed for a while and renders a proper
 * WEF-styled "page not found" — header, navigation, footer, the lot. Nothing
 * ever pointed Apache at it, so a visitor following a dead link got Apache's
 * own 295-byte default page instead: no styling, no branding, and no way back
 * into the site. The template was doing nothing for anyone.
 *
 * This is the entry point that connects the two. It is deliberately thin:
 * every decision about what a not-found page says or looks like stays in the
 * partial, which the slug-driven pages (completed-project-details.php) already
 * require directly.
 */

// The partial calls e() and the shared header/footer expect the public
// content layer to be loaded. completed-project-details.php — the only caller
// until now — pulls this in before requiring the partial, so the dependency
// was satisfied by accident rather than declared. Without it the page fatals
// on the first e() and the visitor gets a bare <head> and nothing else.
require_once __DIR__ . '/../includes/content-blocks.php';

// Reached through an internal rewrite, which answers 200 unless told otherwise,
// so the status is set here — otherwise search engines
// would index a "not found" page as real content.
http_response_code(404);

// Search engines must never index an error page, whatever URL produced it.
$seoNoIndex = true;
$seoTitle = 'Page not found — Worldwide Education Foundation';
$seoDescription = 'The page you were looking for is not here.';

require __DIR__ . '/../includes/layout/not-found.php';
