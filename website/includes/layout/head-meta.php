<?php
/**
 * Shared <head> block: security meta, charset/viewport, and the social and
 * search-engine metadata every public page needs.
 *
 * The security meta tags are reproduced verbatim from what was already live --
 * not rewritten -- so including this file cannot change what the browser
 * enforces.
 *
 * Note for a future pass: this CSP still allows 'unsafe-inline' for scripts
 * and styles, and a <meta> CSP cannot express frame-ancestors at all. Moving
 * these to real Apache headers is a separate, deliberate change.
 *
 * ---------------------------------------------------------------------------
 * SEO block
 * ---------------------------------------------------------------------------
 * An audit found canonical, Open Graph, Twitter Card and JSON-LD tags on
 * exactly ONE of the 24 public pages. The other 23 had a title and a
 * description and nothing else, so a link to any of them shared on Facebook,
 * LinkedIn, WhatsApp or X rendered as a bare URL with no title, no summary and
 * no image -- for a charity that depends on people forwarding its work, that
 * is a real cost.
 *
 * Everything below is ADDITIVE. No existing tag is replaced or removed.
 *
 * A page may set any of these before including this file; each has a working
 * fallback, so a page that sets nothing still gets correct canonical, image
 * and Organization data:
 *
 *   $seoTitle       string  page title for social cards
 *   $seoDescription string  one-sentence summary
 *   $seoImage       string  path relative to the site root
 *   $seoType        string  'website' (default) or 'article'
 *   $seoNoIndex     bool    true to ask search engines not to index
 */

// asset() and imageTag() are used by the stylesheet links and images that
// follow this include on every page. Required here rather than assumed,
// because a page may include this file before content.php.
require_once __DIR__ . '/../assets.php';

// --- Canonical URL ----------------------------------------------------------
// Built from the live request rather than a hardcoded domain, so the same code
// is correct on localhost/WEF, on a staging host, and on the production
// domain. Query strings are dropped: ?form=ok and ?id= produce duplicates of
// a page that should have exactly one canonical address.
$seoScheme = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
) ? 'https' : 'http';

// The Host header is client-supplied, so it is only used when it is one of the
// site's own hosts; anything else gets the production domain. Otherwise a
// request with a forged Host would put that host into the canonical and
// og:url/og:image tags - and a page cache in front of PHP (LiteSpeed on
// Hostinger) could keep serving that copy to everyone. Extra domains can be
// added with APP_SITE_HOSTS (array) in config/config.php.
$seoHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$seoAllowedHosts = array_merge(
    ['www.worldwideeducationfoundation.org', 'worldwideeducationfoundation.org', 'localhost', '127.0.0.1'],
    defined('APP_SITE_HOSTS') && is_array(APP_SITE_HOSTS) ? array_map('strtolower', APP_SITE_HOSTS) : []
);
if (!in_array(preg_replace('/:\d+$/', '', $seoHost), $seoAllowedHosts, true)) {
    // Also the case on Hostinger's temporary address (…hostingersite.com)
    // before the real domain is connected. That copy must not be indexed, or
    // search results would keep sending people to the temporary address, so
    // it is marked noindex. On the real domain this switches off by itself.
    $seoHost = 'www.worldwideeducationfoundation.org';
    $seoNoIndex = true;
}
$seoPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

// Serve one address per page: /contact.php and /contact are the same document,
// and the clean URL is the one that should be indexed.
$seoPath = preg_replace('/\.php$/', '', $seoPath) ?? $seoPath;
$seoPath = preg_replace('#/index$#', '/', $seoPath) ?? $seoPath;
if ($seoPath === '') {
    $seoPath = '/';
}

$seoCanonical = $seoScheme . '://' . $seoHost . $seoPath;

// Directory the site is served from ('/WEF/' locally, '/' on the domain), used
// to turn a root-relative asset path into an absolute URL.
$seoBase = siteBasePath();

// --- Admin-edited title/description -----------------------------------------
// Page Content → (page) → Search & sharing. The page is identified by its file
// name, or by $seoPage when a page sets one; its registry defaults are the
// same text the page itself passes in, so an unedited page is unchanged.
// Pages not in SEO_PAGE_DEFAULTS (404, a completed project's detail page,
// whose title is that project's) keep exactly what they pass in.
require_once __DIR__ . '/../content-blocks.php';

// A page switched off in admin -> Pages On/Off shows "Page not found" (404)
// instead of itself. Here, because every public page passes through this
// file with the content layer loaded and nothing yet sent to the browser.
require_once __DIR__ . '/../page-visibility.php';
enforcePageVisibility();

$seoPageKey = $seoPage ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
$seoPageKey = ['index' => 'home', 'active-projects' => 'programs'][$seoPageKey] ?? $seoPageKey;
if (isset(SEO_PAGE_DEFAULTS[$seoPageKey])) {
    $seoTitle = blockValue($seoPageKey, 'seo_title');
    $seoDescription = blockValue($seoPageKey, 'seo_description');
}

// --- Values, with fallbacks -------------------------------------------------
$seoSiteName = 'Worldwide Education Foundation';
$seoTitle = trim((string) ($seoTitle ?? '')) !== '' ? $seoTitle : $seoSiteName;
$seoDescription = trim((string) ($seoDescription ?? '')) !== ''
    ? $seoDescription
    : 'Worldwide Education Foundation invests in the potential of children and youth across underserved communities in Central and South Asia.';
/**
 * Default social-share image.
 *
 * Deliberately a photograph, not the site logo. The logo was the obvious
 * choice and is the wrong one twice over: it is square and small, which the
 * platforms crop badly, and it used to be logo.svg — Facebook, LinkedIn, X and
 * WhatsApp all refuse SVG for og:image, so every shared link would have
 * rendered with no image at all, the exact failure this block exists to fix.
 * (It is a real PNG now, but a 256px square is still the wrong shape here.)
 *
 * model-schools-01.jpg is 1800x1350 (4:3), comfortably above the 200x200
 * floor and close enough to the ideal 1.91:1 that platforms crop it sensibly
 * rather than reject it. A purpose-made 1200x630 card would be better still;
 * this is the best asset already in the repository.
 */
$seoImage = trim((string) ($seoImage ?? '')) !== ''
    ? $seoImage
    : 'assets/images/programs/model-schools-01.jpg';
$seoType = $seoType ?? 'website';

$seoImageUrl = $seoScheme . '://' . $seoHost . $seoBase . ltrim($seoImage, '/');

/** Local escape helper: this file is included before content.php on some pages. */
$seoEsc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
  <!-- Security Headers -->
  <!-- fonts.googleapis.com and fonts.gstatic.com were removed from this policy
       when the web fonts became self-hosted (extras/tools/fetch-fonts.php). Nothing
       on the site requests either origin any more, so font-src is now 'self'
       alone. Keep this in step with the copy in public/.htaccess: the browser
       enforces the intersection of the two, so a source added to only one of
       them still gets blocked. script-src has no 'unsafe-inline': the two
       inline scripts are allowed by hash (inlineScriptHashes() in
       includes/assets.php), every other script is an external file. -->
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' <?= inlineScriptHashes() ?> https://unpkg.com; style-src 'self' 'unsafe-inline' https://unpkg.com; font-src 'self'; img-src 'self' data: https://unpkg.com https://*.cartocdn.com https://server.arcgisonline.com https://mt1.google.com; connect-src 'self'; frame-src 'self' https://www.youtube.com https://donorbox.org; object-src 'none';">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta name="referrer" content="strict-origin-when-cross-origin">

  <!-- Marks the document as JS-capable before first paint, so
       .reveal/.text-animate-word (styles.css) can stay hidden-until-
       animated ONLY when this ran - a page with JS disabled, blocked, or
       one where an earlier init function throws before initScrollReveal()
       gets a turn, now renders that content visible by default instead of
       stuck invisible forever. Inline and unconditional (no defer/async),
       so it executes synchronously here in <head>, before anything paints -
       there is no flash between "no class" and "js class" to hide. -->
  <script><?= INLINE_SCRIPT_JS_CLASS ?></script>
  <meta charset="UTF-8">
  <!-- viewport-fit=cover lets the page paint under the notch/home-indicator
       instead of letterboxing around it; without it every env(safe-area-inset-*)
       below evaluates to 0px. interactive-widget=resizes-content makes the
       software keyboard shrink the layout viewport on Android Chrome, so fixed
       bottom elements react to it the way they already do on iOS. -->
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<?php
// '/' on the live domain, the XAMPP folder locally - see siteBasePath().
$siteBaseHref = siteBasePath();
?>
  <base href="<?= htmlspecialchars($siteBaseHref, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Web fonts.
       Self-hosted (see extras/tools/fetch-fonts.php), so there is no preconnect here
       and no third-party origin to reach: the files come off the connection
       that is already open for this page.

       The two preloads are the faces that above-the-fold text is actually set
       in — body copy in Inter 400, headings in Plus Jakarta Sans 700. They
       start downloading from the initial HTML instead of waiting for fonts.css
       to arrive and parse, which is what removes the flash of fallback text on
       a first visit. Only two, deliberately: every preload competes with the
       hero image for the same early bandwidth, and the other weights are not
       needed until below the fold, by which point fonts.css has long landed.

       crossorigin is required even same-origin — fonts are fetched in CORS
       mode, and a preload without it is fetched a second time rather than
       reused, which makes the page slower rather than faster. -->
  <link rel="preload" as="font" type="font/woff2" crossorigin
        href="<?= $seoEsc(asset('assets/fonts/inter-400-latin.woff2')) ?>">
  <link rel="preload" as="font" type="font/woff2" crossorigin
        href="<?= $seoEsc(asset('assets/fonts/plus-jakarta-sans-700-latin.woff2')) ?>">
  <link rel="stylesheet" href="<?= $seoEsc(asset('css/fonts.css')) ?>">

  <!-- Above-the-fold CSS, inlined.
       Generated from styles.css by extras/tools/build-critical-css.php — rebuild it
       after changing styles.css. This is what lets the header and hero paint
       from the HTML response alone, so the 159 KB stylesheet that follows can
       be loaded without blocking (see asyncStylesheet() in includes/assets.php).
       The two changes are a pair: inlining this without deferring the full
       sheet saves nothing, and deferring the full sheet without this would
       flash an unstyled header on every page. -->
  <?= criticalCss() ?>


  <!-- Canonical + indexing -->
  <link rel="canonical" href="<?= $seoEsc($seoCanonical) ?>">
<?php if (!empty($seoNoIndex)): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <meta name="robots" content="index, follow, max-image-preview:large">
<?php endif; ?>

  <!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
  <meta property="og:site_name" content="<?= $seoEsc($seoSiteName) ?>">
  <meta property="og:type" content="<?= $seoEsc($seoType) ?>">
  <meta property="og:url" content="<?= $seoEsc($seoCanonical) ?>">
  <meta property="og:title" content="<?= $seoEsc($seoTitle) ?>">
  <meta property="og:description" content="<?= $seoEsc($seoDescription) ?>">
  <meta property="og:image" content="<?= $seoEsc($seoImageUrl) ?>">
  <meta property="og:locale" content="en_US">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@WEFglobaledu">
  <meta name="twitter:title" content="<?= $seoEsc($seoTitle) ?>">
  <meta name="twitter:description" content="<?= $seoEsc($seoDescription) ?>">
  <meta name="twitter:image" content="<?= $seoEsc($seoImageUrl) ?>">

  <?php
  /**
   * Organization structured data.
   *
   * Emitted on every page rather than only the homepage: search engines treat
   * the knowledge-panel entity as a property of the site, and repeating it is
   * both valid and the usual practice. json_encode with HEX_TAG guarantees no
   * "</script>" sequence can terminate the block early, which is the one way a
   * JSON-LD island can become an injection point.
   */
  $seoOrganization = [
      '@context' => 'https://schema.org',
      '@type' => 'NGO',
      'name' => $seoSiteName,
      'alternateName' => 'WEF',
      'url' => $seoScheme . '://' . $seoHost . $seoBase,
      'logo' => $seoScheme . '://' . $seoHost . $seoBase . 'assets/images/logo.png',
      'description' => $seoDescription,
      'email' => 'info@wefdallas.org',
      'telephone' => '+1-214-502-8388',
      'address' => [
          '@type' => 'PostalAddress',
          'streetAddress' => 'W159N4940 Graysland Drive',
          'addressLocality' => 'Menomonee Falls',
          'addressRegion' => 'WI',
          'postalCode' => '53051',
          'addressCountry' => 'US',
      ],
      'nonprofitStatus' => 'Nonprofit501c3',
      /**
       * These four came from a second, competing JSON-LD block that the
       * homepage used to emit alongside this one. Two NGO entities on one page
       * describing the same organisation with different addresses is worse
       * than either alone — a search engine has to pick, and nothing tells it
       * which is authoritative. That block is gone; the facts only it carried
       * are merged here so the surviving entity is the more complete one.
       *
       * The postal address above is the registered US address. areaServed is
       * where the work happens, which is the correct property for Chitral —
       * the old block had it as the organisation's own address, which claimed
       * the charity is located in Pakistan.
       */
      'foundingDate' => '2021',
      'taxID' => '81-2135633',
      'areaServed' => ['Central Asia', 'South Asia'],
      'sameAs' => [
          'https://www.facebook.com/WorldwideEducationFoundationWEF',
          'https://x.com/WEFglobaledu',
          'https://www.instagram.com/worldwideeducationfoundation/',
          'https://www.linkedin.com/company/worldwide-education-foundation',
          'https://www.youtube.com/@wefdallasorg',
      ],
  ];

  /**
   * WebSite entity, so the site is eligible for a sitelinks search box and so
   * the name Google shows for the domain is the one intended rather than one
   * inferred from the title tag.
   */
  $seoWebSite = [
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => $seoSiteName,
      'alternateName' => 'WEF',
      'url' => $seoScheme . '://' . $seoHost . $seoBase,
      'publisher' => ['@type' => 'NGO', 'name' => $seoSiteName],
      'inLanguage' => 'en',
  ];

  $seoJsonLdFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
  ?>
  <script type="application/ld+json"><?= json_encode($seoOrganization, $seoJsonLdFlags) ?></script>
  <script type="application/ld+json"><?= json_encode($seoWebSite, $seoJsonLdFlags) ?></script>
<?php
  /**
   * Breadcrumbs.
   *
   * A page opts in by setting $seoBreadcrumbs to an ordered list of
   * ['name' => ..., 'url' => ...] before including this file; the home entry
   * is prepended automatically. Google uses this to show a path instead of a
   * bare URL in results, which on a site whose slugs are all top-level is the
   * only way the hierarchy is expressible at all.
   */
  if (!empty($seoBreadcrumbs) && is_array($seoBreadcrumbs)):
      $seoCrumbItems = [[
          '@type' => 'ListItem',
          'position' => 1,
          'name' => 'Home',
          'item' => $seoScheme . '://' . $seoHost . $seoBase,
      ]];

      foreach (array_values($seoBreadcrumbs) as $seoIndex => $seoCrumb) {
          if (empty($seoCrumb['name'])) {
              continue;
          }

          $seoCrumbItem = [
              '@type' => 'ListItem',
              'position' => $seoIndex + 2,
              'name' => (string) $seoCrumb['name'],
          ];

          // The last crumb is the current page and needs no item URL; giving
          // one is valid but Google prefers the trail to end at the page.
          if (!empty($seoCrumb['url'])) {
              $seoCrumbItem['item'] = $seoScheme . '://' . $seoHost . $seoBase . ltrim((string) $seoCrumb['url'], '/');
          }

          $seoCrumbItems[] = $seoCrumbItem;
      }
?>
  <script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => $seoCrumbItems,
  ], $seoJsonLdFlags) ?></script>
<?php endif; ?>
