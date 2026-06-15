# Project memory — trainings-landing

Concise orientation for AI agents. **Do not scan the whole repo** unless this doc is insufficient.  
**Rule:** Treat HTML inside `<!-- ... -->` as non-existent legacy unless the user asks to restore it.

---

## Architecture

| Layer | Entry | Notes |
|-------|--------|--------|
| HTML | `index.html` | Single page, Ukrainian (`lang="uk"`) |
| CSS | `css/main.css` | `@import`s all section styles; `:root` design tokens |
| JS | `js/main.js` | ES modules, `type="module"` in HTML |

**Stack:** Static HTML + CSS + vanilla JS. **No React/Vue/build step.**

**Live DOM section order** (active markup only):

1. `header.site-header` (contains `nav.nav` + `button.burger`)
2. `section.hero`
3. `section#about.about`
4. `section#directions.directions`
5. `section#portfolio.portfolio` → nested `section.stories`
6. `footer#contacts.footer`

**Commented out in `index.html` (~lines 365–469):** entire **Aktualno** and **Services** sections. Header nav still links to `#aktualno` and `#services` (dead anchors until HTML or nav is updated).

**Assets:** `img/` has few files; HTML/CSS reference missing paths (`logo.png`, `logo-text.png`, `main.jpg`, favicon, `img-rewiev-*.png`).

```
index.html → css/main.css → section CSS files
index.html → js/main.js → header, burger, hero*, gallery*, directions,
            portfolio, actualno*, services, footer, back-to-top
            (* hero/gallery/actualno are empty stub modules)
```

---

## Important files

### Entry & design system

- **`index.html`** — Structure, section IDs, external fonts (Google) + Font Awesome CDN.
- **`css/main.css`** — Variables, reset, typography, `section` padding, `.btn` / `.cta-btn`, `.back-to-top`, `.reveal`, responsive section padding, **global `h2::after` underline overrides** (end of file).
- **`js/main.js`** — Import order; all imports must resolve or **entire bundle fails**.

### Design tokens (`:root` in `main.css`)

| Token | Value | Use |
|-------|--------|-----|
| `--color-primary` | `#0f4c5c` | Headings, header accents, footer bg |
| `--color-accent` | `#e76f51` | CTAs, underlines, hovers |
| `--color-secondary` | `#bfd8bd` | Accents |
| `--color-bg` | `#f6f1e9` | Page background |
| `--color-text` | `#1e2d2f` | Body text |

Spacing: `--space-1` (8px) … `--space-6` (64px). Prefer variables over hardcoded colors.

### Header & navigation

- **`css/header.css`** — Fixed header, `.scrolled` shrink, desktop nav, burger + mobile full-screen `.nav.open` (`position: fixed; inset: 0`).
- **`js/header.js`** — Adds `.scrolled` when `scrollY > 50`.
- **`js/burger.js`** — Toggles `.active` / `.open`, `body overflow: hidden`, closes on nav link click and Escape.

### Section CSS (active sections)

- `hero.css`, `about.css`, `directions.css`, `portfolio.css`, `footer.css`
- `aktualno.css`, `services.css` — styled but **no live HTML** while commented
- `programs.css`, `gallery.css` — stubs; no HTML sections

### Section JS

- **`directions.js`** — `.direction-card` click toggles `.active` / `.inactive`; document click resets.
- **`portfolio.js`** — IntersectionObserver → `.visible`, typing classes on titles.
- **`services.js`** — Observer on `.service-card` (no cards in DOM while services commented).
- **`footer.js`** — Observer adds `.visible` to footer (required to see footer).
- **`back-to-top.js`** — Shows `.back-to-top.visible` after 400px scroll; smooth scroll to top.

### Stub modules (required for bundle)

- `js/hero.js`, `js/gallery.js`, `js/actualno.js` — empty; do not delete without updating `main.js`.

---

## Solved bugs (root causes — do not re-break)

1. **All JS dead** — Missing `hero.js` / `gallery.js` / `actualno.js` → 404 on import graph. **Fix:** stub files + full import list in `main.js`.
2. **Burger inert** — Same as (1); `burger.js` never ran.
3. **Mobile menu = header-height strip after scroll** — `nav` is **child of** `header`. `backdrop-filter` on `.site-header.scrolled` created a containing block so `position: fixed` nav clipped to header (~60px). **Fix:** `backdrop-filter` only `@media (min-width: 769px)` in `header.css` (see comment lines 22–26). **Never** put `backdrop-filter` on `.site-header` at mobile widths.
4. **Portfolio nesting** — `section#portfolio` unclosed; aktualno/services were inside portfolio in DOM. **Fix:** closing `</section>` after stories (~361–362).
5. **Hero invalid markup** — Stray `</div>` removed; slogans stay in `.hero`.
6. **Broken anchors** — Hero CTA `#programs` → `#directions`; `#documents` / `#partners` via empty `<span id="...">` in about links.
7. **Responsive** — About img `max-width: 100%`; portfolio stacks `.project-item` at ≤768px; directions mobile uses `min-height` + `height: auto` (not fixed `vh`).
8. **Hero slogans vs content overlap (~800–1200px)** — Desktop: centered `.hero-content`; slogans `position: absolute` left. **Fix:** `max-width: min(260px, 22vw)` on `.hero-slogans`; mobile `position: static`. Do not convert desktop hero to two-column flex without explicit UX approval.
9. **Burger X animation** — Flex `gap` + `translateY(7px)` + middle span `width: 0` caused jitter. **Fix:** absolute spans, `translate(-50%, ±9px)`, active rotate at center; middle span opacity only.
10. **Back-to-top stays orange after tap** — Sticky `:hover` on touch. **Fix:** `.back-to-top:hover` inside `@media (hover: hover)` in `main.css`.
11. **Short section underlines** — Per-file `width: 40px` on `h2::after`. **Fix:** shared override at end of `main.css` (`calc(100% - var(--space-2))` + side margins).

---

## Design decisions (do not revert without user ask)

- CSS variables for palette and spacing; youth NGO visual system (primary teal, accent coral, warm bg).
- Breakpoint **768px**: burger + full-screen overlay nav; desktop nav from **769px**.
- **Commented HTML = ignore** for structure/tasks unless user requests restore.
- **`nav` stays inside `header`** — required for current layout; mobile overlay fix depends on desktop-only `backdrop-filter`.
- Footer default `opacity: 0` until JS adds `.visible` — intentional reveal.
- Directions: desktop `.more-btn` on hover; mobile whole-card tap; `.close-btn` in CSS but **not in HTML**.
- `html { scroll-behavior: smooth }` in `main.css`.

---

## Known limitations

- Missing image files break hero background and logos offline.
- Nav `#aktualno`, `#services` with no target sections (commented).
- About `#documents` / `#partners` — scroll to empty anchors only.
- `services.js` runs but no `.service-card` in DOM while services commented.
- `burger.js` — no null checks on `.burger` / `.nav`.
- CDN: Google Fonts, Font Awesome.
- Portfolio typing animations use fixed `ch` — may not match Ukrainian glyph widths.
- `README.md` is placeholder only.

---

## Open tasks (backlog)

- Restore or remove **aktualno** / **services** in HTML and sync header nav.
- Add or fix **image assets** paths.
- Optional: overlay click-outside to close mobile nav.
- Optional: add `.close-btn` in directions HTML or remove unused CSS.
- Expand `README.md` if needed for humans.

---

## Last known stable state

- **JS:** `main.js` loads all listed modules; burger, header scroll, directions, portfolio observer, footer reveal, back-to-top work when DOM present.
- **CSS fixes in tree:** desktop-only scrolled `backdrop-filter`; burger animation; h2 underline bundle; back-to-top hover guard.
- **Live UX path:** Hero → About → Directions → Portfolio/Stories → Contacts; back-to-top button before `</body>`.

### Regression smoke test

1. Mobile: open burger at `scrollY = 0` and after `scrollY > 50` — full-screen menu, not header strip.
2. Desktop: nav links, scrolled header blur (≥769px).
3. Footer visible after scrolling to contacts.
4. Direction cards expand/collapse.
5. ~320px width: no horizontal scroll on about image.

---

*Last updated from repo state: static landing, aktualno/services commented in `index.html`.*
