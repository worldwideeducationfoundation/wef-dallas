# Responsive & spatial fix plan — public site

**Status: Phases 1-4.3 complete, 2026-09-24.** See `docs/CONTEXT.md` §11 ("`docs/RESPONSIVE-PLAN.md` executed end-to-end") for the full log, and §8 defects #5, #18, 28, 29 for specifics. Phase 4.4 (hero swipe gesture) and Phase 5 (structural cleanup) remain deliberately deferred per their own sections below.

Written 2026-09-23 from a measured audit (Playwright viewport emulation, 25 pages × 7 widths: 320, 375, 390, 768, 1024, 1280, 1440). Intended to be executed step by step by a coding agent. Work top to bottom; each step is small, independently verifiable, and gets its own commit.

## Before you start

1. Read `docs/CONTEXT.md` §7 (design system), §8 (known defects), §10 (working agreements). They are binding. In particular: **no new `!important`** (the one exception is step 1.1), use existing tokens (`--space-*`, `--clr-*`, radii, shadows) instead of new magic numbers, and never trust a headless CLI screenshot over direct measurement.
2. Set up the audit tool (it is not a project dependency — keep it outside the repo):
   ```powershell
   npm install --prefix $env:TEMP\wef-pw playwright-core
   $env:NODE_PATH = "$env:TEMP\wef-pw\node_modules"
   node tools/responsive-audit.js audit            # numbers for every page × width
   node tools/responsive-audit.js shots index.php 390   # readable screenshot tiles
   ```
   It drives the Edge already installed on this machine (`channel: 'msedge'`). XAMPP (Apache + MySQL) must be running.
3. Run the audit once and keep the output as your **baseline**. Every step below says what the audit (or a probe) should show once it's done.

### Two traps that will waste your time

- **Blank bands in screenshots are not bugs.** Sections use `.reveal` / `.reveal-left` / `.reveal-right` and headings use word-by-word `.text-animate-word`; all sit at `opacity: 0` until scrolled into view, and `.reveal` hides again when scrolled out (defect #18). `responsive-audit.js shots` scrolls through and force-shows them. If you capture any other way, expect empty sections.
- **Counters read "0" or target−1 mid-animation.** Wait ~2.5s after scroll before judging a number.

### Commit rules

- One commit per step (or per tightly related pair). Run `php -l` on touched PHP, `node -c` on touched JS, re-run the audit, and look at the relevant screenshot widths before committing.
- `git commit -F <tempfile>` (PowerShell mangles `-m` with quotes). End messages with the attribution line your harness gives you.
- After each phase, append a short entry to `docs/CONTEXT.md` §8/§11 in the existing style: what changed, how it was verified.

---

## What the audit found (baseline)

**Good news first:** no page scrolls sideways at any width. The `nav#nav-menu` that measures off-screen is the closed mobile drawer, which is correct.

| # | Issue | Where | Evidence |
|---|---|---|---|
| A | Both donation amount sets (monthly **and** one-time) show at once | `index.php` donate card, every width | 2nd `.donate-amount-selector` has `hidden` but computed `display: grid` |
| B | Desktop nav doesn't fit at tablet-landscape widths | header on **every page**, ~993–1199px | at 1024: logo text wraps to 4 lines and is clipped at the top; "WHAT WE DO" wraps to 3 lines; "DONATE NOW" to 2 |
| C | 5-column "WEF At A Glance" table is a hidden sideways scroll on phones | `our-mission.php` @ <768 | WHERE/WHO/IMPACT/NOTES cut mid-word at the right edge; no scroll affordance |
| D | Roadmap chart is unreadable on phones | `impact-goals.php` @ <600 | SVG scales to ~350px wide → labels render ~5px |
| E | "Our Partners" heading ~2:1 contrast | `index.php` | computed `color: rgb(167,172,179)` on near-white |
| F | Tiny text on cards | `index.php` (90 elements), `active-projects.php` (55) | `.sponsor-badge-tag` 10px ×34, project-card stat `.regular-text` 9px ×27 and `.big-text` 11px ×27, header tagline span 8px, `.hero-glass-eyebrow` 11px |
| G | Tap targets under 44px | every page | 16 footer links 358×32; header logo link 251×40; hero slide dots **6×6**; `#donor-select` 144×42; `#donate-custom-toggle` |
| H | Distorted images (`object-fit: fill`, wrong aspect) | `language-labs`, `research-hub`, `secondary-scholarships`, `rising-stars` (icons `Lab.svg`, `Research.svg`, `Scholarship.svg`); `impact-goals` (3 summary icons: PNGs 256×168 etc. forced to 56×56 by inline style) | all widths |
| I | Program gallery image doesn't fill its frame | program detail pages, e.g. `bright-beginnings.php` @390 | image ~276px wide inside a ~326px dashed frame, empty band below it |
| J | Stray "≡" glyph in the location pill | program detail hero, e.g. "📍 ≡ Upper Chitral" | visible in 390 screenshot |
| K | Stat cards are tall and mostly empty | `index.php` "WEF Globally", @390 | ~220px cards, number at the bottom, blank top (missing icon?) |
| L | Duration renders as a bullet | `completed-project-details.php` | "Duration: September 2017" then "• September 2018" — the " - " separator is being parsed as a list item |
| M | Vision/Mission/Belief cards stay single-column at 1024 | `index.php` | full-width cards with large empty right side |
| N | Honeypot input at 13.3px | `#contact-website` on index/contact/volunteer | only matters if it can ever be focused; make sure it can't |
| O | Hover effects stick after tap | site-wide | 115 `:hover` rules, 1 gated (known defect #5) |
| P | Content hides again when scrolled back up | site-wide `.reveal` | known defect #18 |
| Q | `.pb-sidebar` overflows by exactly 20px | `completed-project-details.php` @1024 and @1280 | **probably** the `.reveal-right` pre-animation `translateX`, not layout — verify before fixing |

---

## Phase 1 — Real bugs (do these first; highest impact, smallest diffs)

### 1.1 Donation amounts: only one set visible (A)
- Add a global rule near the top of `public/css/styles.css` (with the other base resets): `[hidden] { display: none !important; }`. This is the one justified `!important`: it's the standard reset that makes the HTML `hidden` attribute actually win over component `display` rules. Comment why.
- Check `critical.css` too — if it defines `.donate-amount-selector { display: grid }` it needs the same reset, since it loads first.
- **Verify:** probe that exactly one `.donate-amount-selector` has non-`none` display; click Monthly ↔ One-time and confirm they swap. Screenshot 390 + 1440.

### 1.2 Header at tablet-landscape (B)
- Find the breakpoint where `.hamburger` appears and `.nav-menu` becomes the drawer (grep `.hamburger` / `.nav-menu` in `styles.css`; there are overlapping rules around lines 2330–2400 and a 768px layer around 6158–6240 — nav is one of the multiply-declared selectors, so read all of them first).
- Measure how wide the desktop header needs to be with nothing wrapping (logo + 6 links + Donate). Show the drawer below that width (expect ~1200px). Also add `white-space: nowrap` to nav links and the Donate button so they can never wrap.
- Consider hiding the logo tagline ("PROVIDING THE SEEDS FOR GROWTH") at the in-between widths rather than letting it wrap.
- **Verify:** screenshots at 1024, 1100, 1199, 1200, 1280, 1440 — header is one row, logo text never clipped. Open/close the drawer at 1024 with mouse **and** keyboard (Enter, Escape returns focus to toggle — see defect #2).

### 1.3 "Our Partners" contrast (E)
- Find the partners `h2` rule and set it to the same heading colour token as the other section titles (they compute to `rgb(31,79,41)`).
- **Verify:** probe computed colour; contrast ≥ 4.5:1.

### 1.4 Stray "≡" in location pill (J)
- Find where the program hero renders the location pill (the 9 program pages / `includes/program-nav.php` / the partial they share). Likely a second icon glyph or icon-font fallback. Remove it; keep the 📍 or replace both with one inline SVG consistent with other icons on the site.
- **Verify:** 390 screenshot of 2–3 program pages.

### 1.5 Duration bullet (L)
- Find how `completed-project-details.php` renders the description/meta. If the stored text contains "September 2017 - September 2018" and the renderer treats a line starting with "- " as a list item, either fix the renderer (a hyphen mid-sentence after a line wrap must not become a bullet) or render duration from structured fields. **Do not edit content text** to work around it without asking.
- **Verify:** the slug `computers-to-uca-spce` shows "Duration: September 2017 – September 2018" on one line.

### 1.6 Sidebar overflow (Q) — verify first
- Probe `.pb-sidebar` computed `transform` before it's revealed. If it's the reveal's `translateX(20px)`, the fix belongs in the reveal system (phase 4.3): animate with a transform that can't push past the container (e.g. start from `translateX(0)` + opacity only for `reveal-right` on elements that touch the container edge), or clip at the section. If it's a real grid width problem, give the grid columns `minmax(0, 1fr)` / `min-width: 0`.

---

## Phase 2 — Mobile layouts that don't fit

### 2.1 Mission table → stacked cards on phones (C)
- `our-mission.php` renders the table from `publishedMissionProjects()` (DB-backed as of 2026-09-23). Keep the table for ≥768px.
- Below 768px, present each row as a card: add `data-label="WHERE"` (etc.) to each `<td>`, and in the page's `<style>` block switch `table, tbody, tr, td` to `display: block`, hide `thead` visually (keep it for screen readers or rely on `data-label` via `::before`), and show each `td`'s label above its bullets. The WHAT cell becomes the card heading.
- **Verify:** 320/375/390 screenshots — nothing cut off, no sideways scroll inside `.glance-table-wrapper`; 768/1440 unchanged.

### 2.2 Roadmap chart on phones (D)
- `impact-goals.php` builds the SVG from `$chartPoints` (fixed 5-slot geometry, see the comment in that file and in `schema.sql`). Below ~600px, hide the SVG and render the same `$chartPoints` as a vertical milestone list (date, then centers / students / teachers, each a `.counter` so the existing JS still animates them). Same data, no second query.
- While there: the summary-card icons are distorted (H) — see 3.4. And confirm the 3rd summary card and the CTA actually reveal on a real scroll (the audit found both still at `opacity: 0` after a fast scroll-through; `impact-goals-new.js` uses `threshold: 0.1` and `unobserve`s — check whether they ever intersect on a short phone viewport).
- **Verify:** 320/390 screenshots readable at 1×; 768+ still shows the SVG.

### 2.3 Program gallery image fills its frame (I)
- Program detail pages: make the gallery image `width: 100%` with a fixed `aspect-ratio` and `object-fit: cover`, and remove the empty band under it.
- **Verify:** 390 + 1440 on all 9 program pages (they share markup; spot-check 3).

### 2.4 Stat cards (K)
- Find out what the empty top of each `.impact-number` card is for (missing icon/Lottie? lazy image?). If an icon is missing, restore it; otherwise shrink the card to its content and use a 2×2 grid on phones.

### 2.5 Donate amount grid (after 1.1)
- With only one set showing, make the amount buttons an even grid (e.g. 3 columns ≥360px, or `repeat(auto-fit, minmax(88px, 1fr))`) so 5 amounts don't wrap 3+2 raggedly — or accept 3+2 but center the last row. Each button ≥44px tall.

### 2.6 Vision / Mission / Belief at tablet-landscape (M)
- At ≥900px, lay the three `.about-card`s out in 3 columns (they're short). Check 768 (2+1 or stacked) and 1440.

---

## Phase 3 — Touch targets, text size, images

### 3.1 Tap targets (G) — minimum 44×44 hit area, visual size may stay smaller
- **Hero slide dots** (`.slide-dot`, 6×6): keep the visible dot, enlarge the hit area with padding or a `::before` inset — note `.slide-dot` is declared in `styles.css` ~3432, ~6013, ~6694 **and** in `index.php`'s inline `<style>` (~340, ~385); consolidate as you go.
- **Footer links** (16 × 32px tall): `min-height: 44px; display: inline-flex; align-items: center` (or vertical padding) at mobile widths.
- **Header logo link** (40px tall), `#donor-select` (42px), `#donate-custom-toggle`: bring to 44px.
- **Verify:** audit shows 0 tap targets under 44px at 390 (apart from inline links inside paragraphs, which the audit already excludes).

### 3.2 Minimum text size (F)
- Floor visible text at 12px; small uppercase labels read best at 12px with ~0.04em tracking. Targets: `.sponsor-badge-tag` (10px), project-card `.regular-text` (9px) and `.big-text` (11px), the header tagline span (8px), `.hero-glass-eyebrow` (11px).
- **Verify:** audit "text <12px" count at 390 drops to 0 on `index.php` and `active-projects.php`.

### 3.3 Honeypot (N)
- Make sure `#contact-website` is `position:absolute; left:-9999px` (or `clip`), `tabindex="-1"`, `autocomplete="off"`, `aria-hidden="true"` on its wrapper — then its font size is irrelevant. If any of those are missing, add them; set `font-size: 16px` anyway so iOS never zooms if it's ever focused.

### 3.4 Distorted images (H)
- Program-page hero icons (`Lab.svg`, `Research.svg`, `Scholarship.svg`): set `object-fit: contain` or correct the width/height so the ratio matches.
- `impact-goals.php` summary icons: the `<img>` tags carry `width="256" height="168"` (etc.) plus inline `style="width:56px;height:56px"` — replace the inline style with a class using `height: 56px; width: auto` (or `object-fit: contain`).
- **Verify:** audit "distorted img" list empty.

---

## Phase 4 — Touch feel (Apple fluid-interface principles)

### 4.1 Gate hover effects (O, defect #5)
- Wrap card-lift / nav-sweep `:hover` rules in `@media (hover: hover) and (pointer: fine)` so they don't stick after a tap. 115 rules — work component by component, not as one blind regex. Keep `:focus-visible` equivalents outside the media query.

### 4.2 Instant press feedback
- Buttons and tappable cards respond on press, not release: `:active { transform: scale(0.97); transition: transform 100ms ease-out; }` on the primary button system and cards. Respect `prefers-reduced-motion` (the global rule already zeroes durations — confirm).

### 4.3 Reveal system (P, defect #18, and Q)
- Stop removing `.active` when an element leaves the viewport — reveal once, then leave it.
- Make content visible by default without JS: only apply the hidden starting state when JS has run (e.g. a `js` class on `<html>`), so a script error never leaves sections invisible. Same for `.text-animate-word`.
- Keep reveal motion small and inside its container (fixes Q if confirmed).

### 4.4 (Optional, later) Hero swipe
- The hero slideshow has no touch gesture (defect #10). If added: Pointer Events + `setPointerCapture`, 1:1 tracking, ~10px threshold before committing to horizontal, commit by release velocity sign rather than position, never block vertical page scroll (`touch-action: pan-y`).

---

## Phase 5 — Structural cleanup (separate effort — confirm with the owner before starting)

The root cause of most of the above is structural (defects #11–#17): 462 `!important`s, 375 of them in three stacked "mobile fix" layers at the end of `styles.css`; 131 multiply-declared selectors; `critical.css` duplicating ~1,500 lines. Phases 1–4 deliberately fix symptoms in place. Consolidating the mobile layers into the base rules is the right long-term fix but is a large refactor that needs full-site visual regression at every width — do it only as its own planned piece of work, using `tools/responsive-audit.js` before/after on every page.

---

## Done when

- `node tools/responsive-audit.js audit` reports, at every width: no sideways page scroll, no overflow, no distorted images, no inputs under 16px, no text under 12px, and no tap targets under 44px at phone widths.
- Screenshots at 320 / 390 / 768 / 1024 / 1440 of `index`, `our-mission`, `impact-goals`, one program page, `completed-project-details`, `contact` look right with nothing clipped or cramped.
- Mobile drawer works by touch, mouse and keyboard at every width where it's shown.
- `docs/CONTEXT.md` updated for each phase.
