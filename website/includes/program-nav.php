<?php
/**
 * Makes a programme page's "back" link point where the visitor actually came
 * from.
 *
 * The problem it solves. Each of the nine programme pages hardcodes one parent
 * — model-schools.php always says "Early Childhood Programs", because that is
 * its category in the CMS. But every one of the nine is also listed on Active
 * Projects, which renders all of them regardless of category. So the journey
 *
 *     Active Projects  ->  Model Schools  ->  back
 *
 * lands on Early Childhood Programs: a page the visitor has never seen, with
 * a different set of projects on it. The link is not wrong about the
 * hierarchy, it is wrong about the history, and to the person clicking it
 * those are the same thing.
 *
 * How it works. Hub pages append ?from=<hub> to their card links. This reads
 * that parameter, checks it against the allow-list below, and returns the
 * matching hub. Anything unrecognised — or absent, which is the case for a
 * direct visit, a search result or a shared link — falls back to the page's
 * own canonical parent, so behaviour is unchanged unless the visitor genuinely
 * arrived from a hub.
 *
 * The value is never used as a URL. It is a key into PROGRAM_HUBS and the href
 * comes from there, so a crafted ?from= cannot point the link anywhere of its
 * own choosing.
 */

/**
 * The pages that list programmes. Key is what appears in ?from=.
 *
 * @var array<string, array{href: string, label: string}>
 */
const PROGRAM_HUBS = [
    'active-projects' => ['href' => 'active-projects', 'label' => 'All Projects'],
    'completed-projects' => ['href' => 'completed-projects', 'label' => 'Completed Projects'],
];

/**
 * Resolves the back link for a programme page.
 *
 * @param string $defaultHub key into PROGRAM_HUBS — the page's canonical parent
 * @return array{href: string, label: string}
 */
function programBackLink(string $defaultHub): array
{
    $from = (string) ($_GET['from'] ?? '');

    if (isset(PROGRAM_HUBS[$from])) {
        return PROGRAM_HUBS[$from];
    }

    return PROGRAM_HUBS[$defaultHub] ?? PROGRAM_HUBS['active-projects'];
}

/**
 * The ?from= suffix a hub page appends to its own card links.
 *
 * Kept here rather than typed into each hub so the parameter name only exists
 * in one place — the reader and the writer of it cannot drift apart.
 */
function programHubParam(string $hub): string
{
    return isset(PROGRAM_HUBS[$hub]) ? '?from=' . rawurlencode($hub) : '';
}

/**
 * The fixed order the "Previous Program" / "Next Program" pager at the
 * bottom of each of the 9 program pages cycles through.
 *
 * This is a deliberate learner's-journey narrative (early years through
 * scholarships through vocational/research), not the programs table's own
 * `category, display_order, name` ordering — those group early_childhood,
 * programs and scholarships into three separate blocks, which would break
 * this cycle's intentional cross-category sequence. So the *order* stays a
 * fixed constant here rather than being derived from the table, and only
 * the *labels* (each neighbour's current name) are looked up live — see
 * programPagerLinks(). Reordering programs within a category in the admin
 * must not silently reshuffle this narrative.
 *
 * @var list<string> program slugs
 */
const PROGRAM_JOURNEY = [
    'bright-beginnings',
    'learning-centers',
    'model-schools',
    'language-labs',
    'teacher-training',
    'research-hub',
    'secondary-scholarships',
    'rising-stars',
    'skills-development',
];

/**
 * Names shown if a program's row is missing or unpublished — the same
 * defensive-fallback role every other per-page $program* variable already
 * plays elsewhere on these pages (see e.g. bright-beginnings.php's
 * $programLocation/$programBadge). Keeps the pager showing *something*
 * sensible rather than a blank label if the database is unreachable.
 */
const PROGRAM_JOURNEY_FALLBACK_NAMES = [
    'bright-beginnings' => 'Bright Beginnings: Early Childhood Learning Centers',
    'learning-centers' => 'Government School Learning Centers',
    'model-schools' => 'Government Model Schools',
    'language-labs' => 'In-School Language Labs',
    'teacher-training' => "Teachers' Training Program",
    'research-hub' => 'Herzing-Daya Research and Learning Hub',
    'secondary-scholarships' => 'Secondary School Scholarships',
    'rising-stars' => 'AM Rising Stars Undergraduate Scholarship',
    'skills-development' => 'Herzing Center for Skills, Innovation and Entrepreneurship',
];

/**
 * The previous/next neighbour for one program page in the fixed journey
 * above. Each neighbour's name comes from publishedPrograms() — the same
 * live-with-fallback source every program page already reads for its own
 * content — so a name changed in the admin panel updates every page that
 * links to it, not just the program's own page.
 *
 * Requires includes/content.php to already be loaded (every caller already
 * requires it for its own $program lookup).
 *
 * @return array{prev: array{slug: string, name: string}, next: array{slug: string, name: string}}
 */
function programPagerLinks(string $currentSlug): array
{
    $index = array_search($currentSlug, PROGRAM_JOURNEY, true);
    $count = count(PROGRAM_JOURNEY);

    if ($index === false || $count === 0) {
        return [
            'prev' => ['slug' => $currentSlug, 'name' => PROGRAM_JOURNEY_FALLBACK_NAMES[$currentSlug] ?? ''],
            'next' => ['slug' => $currentSlug, 'name' => PROGRAM_JOURNEY_FALLBACK_NAMES[$currentSlug] ?? ''],
        ];
    }

    $names = [];
    foreach (publishedPrograms() as $row) {
        $names[$row['slug']] = $row['name'];
    }

    $resolve = static function (string $slug) use ($names): array {
        return ['slug' => $slug, 'name' => $names[$slug] ?? (PROGRAM_JOURNEY_FALLBACK_NAMES[$slug] ?? $slug)];
    };

    return [
        'prev' => $resolve(PROGRAM_JOURNEY[($index - 1 + $count) % $count]),
        'next' => $resolve(PROGRAM_JOURNEY[($index + 1) % $count]),
    ];
}
