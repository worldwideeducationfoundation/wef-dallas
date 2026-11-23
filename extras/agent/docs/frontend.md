# WEF — Frontend

State after the Phase 6 review (1 August 2026). Design was preserved: all 24
pages render byte-identical HTML before and after.

## What changed

| | Before | After |
|---|---|---|
| `css/styles.css` | 158,192 bytes / 7,263 lines / 891 rules | **122,210 bytes / 5,487 lines / 655 rules** |
| Dead CSS selectors | 218 | **15** (all deliberately kept — see below) |
| `!important` | 91 | 87 |
| `js/main.js` | 36,159 bytes / 1,035 lines | **35,923 bytes / 1,014 lines** |
| Title-animation systems running | **2, fighting each other** | 1 |

## Dead CSS removal — 234 blocks, 34.8 KB

Removed rule blocks whose selectors cannot match anything on any page.

The safety argument is simple and worth stating, because it is the whole
justification: **a rule that matches no element cannot affect rendering.** So
the only thing that matters is being right about "matches nothing", which was
established two independent ways that had to agree:

1. **Source grep** — every `.php`, `.js`, `.html` and `.css` in the project
   searched for each class as a whole word.
2. **Rendered DOM gate** — all 24 pages fetched from the live server, every
   `class` and `id` collected, and the removal set checked against them. The
   removal was gated on this passing.

What was removed clusters into recognisable groups: `history-*` (a page that no
longer exists), `pdf-*` (the PDF modal that was dropped when annual reports
started linking straight to files), and `bento-*` / `chapter-*` / `story-*` /
`statement-*` / `cinematic-*` / `era-*` / `hero-split-*` (superseded page
designs).

### The catch that mattered

An early per-class pass flagged `.leaflet-popup-content`,
`.leaflet-popup-content-wrapper` and `.leaflet-popup-tip` as dead. **They are
not.** Leaflet generates that markup at runtime from its CDN bundle, which no
source scan can see, and `styles.css` restyles it — `js/map.js:231` sets
`className: 'custom-leaflet-popup'` on the popup. Deleting those rules would
have visibly reverted the map popups on the homepage and `early-childhood` to
Leaflet's default look.

Two things prevented it: matching per **selector** rather than per class (the
parent class *is* referenced, so the rule survives), plus an explicit keep-list
for anything mentioning `leaflet`. Those three rules are the bulk of the 15
selectors still reported as "dead".

The lesson for anyone repeating this: **a selector that styles a third-party
widget will always look unused.**

## Duplicate JavaScript — two title animations, fighting

`initTitleSlideLeft()` and `initCharRevealHeadings()` both ran on every page and
targeted almost the same selectors (`h1:not(.hero-glass-heading), h2, …`), so
every heading got both treatments at once.

They pulled against each other:

| | `initTitleSlideLeft` | `initCharRevealHeadings` |
|---|---|---|
| Scope | whole heading | per character |
| From | `translateX(-45px)` | `translateX(+20px)` |
| Opacity | 0 → 1 | 0 → 1 |
| Trigger | own IntersectionObserver | own IntersectionObserver |

Opposite directions, both driving opacity, two observers per heading. Its
`.active` rule needed `!important` on both properties — the usual sign of two
systems competing for the same declarations. It also self-invoked at file scope
rather than from the `DOMContentLoaded` list, so it ran on pages whose init list
had never mentioned it.

**Kept:** the per-character version, because it is the effect that was actually
specified (Magic UI `TextAnimate`, `animation="slideLeft" by="character"`).
Its `.title-slide-left` CSS was removed with it, taking 4 `!important`s.

If the whole-title slide was the intended look, this is one revert away —
`js/main.js` and `css/styles.css` are backed up in `C:\xampp\WEF_quarantine\`.

## Accessibility

Audited with a real HTML parser across all 24 rendered pages. An earlier
grep-based pass reported 15 images missing `alt`; that was **wrong** — the regex
was defeated by PHP's `?>` appearing inside tags. The parser found:

| Check | Result |
|---|---|
| Images with `alt` | **156 / 156** |
| Links with no accessible name | **0** |
| Buttons with no accessible name | **0** |
| Pages with `lang` | 24 / 24 |
| Pages with exactly one `<h1>` | 23 / 24 |

> **Correction — 2026-09-22.** The claim below was re-checked with a full HTML
> parser and is **false**: `index.php` has exactly one `<h1>`
> (`class="sr-only"`, line 131); the nine hero slides use
> `<p class="hero-glass-heading">`, not `<h1>`. Every `.php` page has exactly
> one `<h1>` except `completed-project-details.php`, whose second `<h1>` is
> inside a commented-out code path. Left below as historical record of what an
> earlier (apparently regex-based) pass reported.

The exception is `index`, whose hero carries **9 `<h1>` elements**, one per
slide. `js/main.js` sets `aria-hidden` on every inactive slide, so a screen
reader encounters only one — the accessibility side is handled. It remains a
markup/SEO oddity, and fixing it means touching the typewriter effect, which was
explicitly off-limits.

### Layout shift — not the problem it looked like

118 images carry no `width`/`height`, which normally means cumulative layout
shift. It does not here: the containers already reserve space —
`.team-card-image { aspect-ratio: 1 / 1 }`, `.gallery-item { aspect-ratio: 1 }`,
`.project-card-image { height: 220px }`. Adding dimension attributes to those
would gain nothing, so it was not done.

## Still open

### 12 selectors are defined more than once

Not auto-merged: unlike dead-rule removal, merging changes the cascade and
cannot be proven safe without visual comparison. Listed with line numbers so it
can be done deliberately:

| Selector | Lines |
|---|---|
| `.header` | 419, 3278, 4754 |
| `.header.scrolled` | 430, 3282 |
| `.header-logo-text span:first-child` | 469, 4760 |
| `.header-logo-text span:last-child` | 478, 4764 |
| `.hamburger span` | 700, 4778 |
| `.about-card-icon-svg` | 830, 3269 |
| `.impact-card-icon` | 1161, 3262 |
| `.project-card-image img` | 1238, 3273 |
| `.uiverse-card .bottom-section .row .item` | 5117, 5156 |

`.header` is the clearest case: line 419 lays out the element, 3278 adds one
transition, and 4754 overrides background, blur and shadow with three
`!important`s. Anyone editing the header has to know all three exist.

### 18 KB of inline `<style>`

`our-mission` (7.2 KB), `completed-project-details` (5.9 KB),
`completed-projects` (3.8 KB) and `index` (1.4 KB) carry page-specific CSS in
the markup.

**Deliberately left alone.** Moving it into `styles.css` would make all 24 pages
download styles that one page uses — `styles.css` is cached, but it is also the
first-load cost for every visitor. The two "completed" pages were checked for
overlap and share **zero** selectors, so there is no duplication to collect.

Worth revisiting only alongside dropping `style-src 'unsafe-inline'` from the
CSP — and that also needs the 99 inline `style=""` attributes dealt with, so the
`<style>` blocks are not the blocker.

### Other

- `js/main.js` still loads on all 23 pages that use it, initialising features
  most pages do not have. The guards make it harmless, but the file only grows.
- 87 `!important` remain.
