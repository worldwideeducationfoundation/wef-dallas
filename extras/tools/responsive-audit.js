/**
 * Responsive audit for the public site, using real Playwright viewport
 * emulation (NOT `msedge --headless --window-size`, which proved unreliable —
 * see docs/CONTEXT.md §10).
 *
 * playwright-core is deliberately not a project dependency. Install it
 * somewhere outside the repo and point NODE_PATH at it:
 *   npm install --prefix %TEMP%\wef-pw playwright-core
 *   set NODE_PATH=%TEMP%\wef-pw\node_modules        (PowerShell: $env:NODE_PATH=...)
 *
 * Usage (XAMPP running, from the repo root):
 *   node extras/tools/responsive-audit.js audit [outDir]             -> outDir/audit.json + printed summary
 *   node extras/tools/responsive-audit.js shots <page> <width> [outDir] -> outDir/<width>-<page>-N.png tiles
 *
 * Screenshots scroll the page first and force scroll-reveal / text-animate
 * content visible, because the site hides that content until it is in view.
 * A blank band in a raw full-page capture is an animation artifact, not a bug.
 */
const { chromium } = require('playwright-core');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BASE = process.env.WEF_BASE || 'http://localhost/WEF/';
const PAGES = ['index.php', 'our-mission.php', 'our-team.php', 'active-projects.php',
  'bright-beginnings.php', 'learning-centers.php', 'model-schools.php', 'language-labs.php', 'teacher-training.php',
  'research-hub.php', 'secondary-scholarships.php', 'rising-stars.php', 'skills-development.php',
  'completed-projects.php', 'completed-project-details.php?slug=computers-to-uca-spce', 'impact-goals.php',
  'gallery.php', 'video-gallery.php', 'success-stories.php', 'annual-reports.php', 'contact.php', 'donate.php', 'volunteer.php', '404.php'];
const WIDTHS = [320, 375, 390, 768, 1024, 1280, 1440];
const FORCE_VISIBLE = '[class*="reveal"], .text-animate-word, .summary-card, .cta { opacity:1 !important; transform:none !important; visibility:visible !important; }';

function context(browser, w) {
  const mobile = w < 768;
  return browser.newContext({ viewport: { width: w, height: 800 }, isMobile: mobile, hasTouch: mobile, deviceScaleFactor: 1 });
}

async function measure(page, mobile) {
  return page.evaluate((mobile) => {
    const vw = document.documentElement.clientWidth;
    const out = { vw, innerWidth: window.innerWidth, scrollWidth: document.documentElement.scrollWidth, overflow: [], smallTargets: [], tinyText: [], smallInputs: [], distorted: [] };
    const sel = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.classList.length ? '.' + [...el.classList].slice(0, 2).join('.') : '');
    const clipped = el => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) if (/(hidden|auto|scroll|clip)/.test(getComputedStyle(a).overflowX)) return true; return false; };
    for (const el of document.body.querySelectorAll('*')) {
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden') continue;
      const b = el.getBoundingClientRect();
      if (!b.width || !b.height) continue;
      if (el.closest('.nav-menu')) continue; // closed off-canvas drawer lives off-screen by design
      if (b.right > vw + 1 && !clipped(el)) {
        const p = el.parentElement;
        if (!(p && p.getBoundingClientRect().right > vw + 1 && !clipped(p))) out.overflow.push(`${sel(el)} right=${Math.round(b.right)}`);
      }
      if (mobile && el.matches('a, button, input, select, textarea, [role="button"]') && (b.height < 44 || b.width < 44) && !el.closest('p'))
        out.smallTargets.push(`${sel(el)} ${Math.round(b.width)}x${Math.round(b.height)}`);
      if ([...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim()) && parseFloat(cs.fontSize) < 12)
        out.tinyText.push(`${sel(el)} ${cs.fontSize} "${el.textContent.trim().slice(0, 20)}"`);
      if (mobile && el.matches('input:not([type=checkbox]):not([type=radio]):not([type=hidden]), select, textarea') && parseFloat(cs.fontSize) < 16)
        out.smallInputs.push(`${sel(el)} ${cs.fontSize}`);
      if (el.tagName === 'IMG' && el.naturalWidth && cs.objectFit === 'fill') {
        const ar = el.naturalWidth / el.naturalHeight, rr = b.width / b.height;
        if (Math.abs(ar - rr) / ar > 0.08) out.distorted.push(`${sel(el)} ${(el.getAttribute('src') || '').split('/').pop()}`);
      }
    }
    return out;
  }, mobile);
}

async function audit(outDir) {
  const browser = await chromium.launch({ channel: 'msedge' });
  const results = [];
  for (const w of WIDTHS) {
    const ctx = await context(browser, w);
    const page = await ctx.newPage();
    for (const p of PAGES) {
      await page.goto(BASE + p, { waitUntil: 'load' }).catch(() => {});
      await page.waitForTimeout(300);
      results.push({ page: p, width: w, ...(await measure(page, w < 768).catch(e => ({ error: String(e) }))) });
    }
    await ctx.close();
  }
  await browser.close();
  fs.writeFileSync(path.join(outDir, 'audit.json'), JSON.stringify(results, null, 1));

  const bad = results.filter(r => r.innerWidth && r.innerWidth !== r.width);
  console.log(bad.length ? `WARNING viewport mismatch on ${bad.length} runs — results unreliable` : 'viewport emulation OK');
  for (const r of results) {
    const issues = [];
    if (r.scrollWidth > r.vw + 1) issues.push(`PAGE SCROLLS SIDEWAYS (${r.scrollWidth} > ${r.vw})`);
    if (r.overflow?.length) issues.push(`overflow: ${r.overflow.slice(0, 3).join(' | ')}`);
    if (r.smallTargets?.length) issues.push(`${r.smallTargets.length} tap targets <44px`);
    if (r.tinyText?.length) issues.push(`${r.tinyText.length} text <12px`);
    if (r.smallInputs?.length) issues.push(`inputs <16px: ${r.smallInputs.join(', ')}`);
    if (r.distorted?.length) issues.push(`distorted img: ${[...new Set(r.distorted)].join(', ')}`);
    if (issues.length) console.log(`${r.page} @${r.width}: ${issues.join('; ')}`);
  }
  console.log(`\nFull detail: ${path.join(outDir, 'audit.json')}`);
}

async function shots(p, w, outDir) {
  const browser = await chromium.launch({ channel: 'msedge' });
  const ctx = await context(browser, w);
  const page = await ctx.newPage();
  await page.goto(BASE + p, { waitUntil: 'load' });
  await page.evaluate(async () => {
    for (let y = 0; y < document.documentElement.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 80)); }
    window.scrollTo(0, 0);
  });
  await page.addStyleTag({ content: FORCE_VISIBLE });
  await page.waitForTimeout(2500); // let counters finish
  const h = await page.evaluate(() => document.documentElement.scrollHeight);
  const name = p.replace(/[?=]/g, '_').replace('.php', '');
  const TILE = 2200;
  for (let i = 0; i * TILE < h; i++) {
    const file = path.join(outDir, `${w}-${name}-${i}.png`);
    await page.screenshot({ path: file, fullPage: true, clip: { x: 0, y: i * TILE, width: w, height: Math.min(TILE, h - i * TILE) } });
    console.log(file);
  }
  await browser.close();
}

(async () => {
  const [mode, a, b, c] = process.argv.slice(2);
  if (mode === 'audit') {
    const outDir = a || path.join(os.tmpdir(), 'wef-audit');
    fs.mkdirSync(outDir, { recursive: true });
    await audit(outDir);
  } else if (mode === 'shots' && a && b) {
    const outDir = c || path.join(os.tmpdir(), 'wef-audit');
    fs.mkdirSync(outDir, { recursive: true });
    await shots(a, parseInt(b, 10), outDir);
  } else {
    console.log('usage: node extras/tools/responsive-audit.js audit [outDir] | shots <page.php> <width> [outDir]');
  }
})();
