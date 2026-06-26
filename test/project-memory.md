# Project memory — `/test` (archived staging)

**Status:** CMS promoted to **site root** (2026-06-14). Do not use `/test` for production runtime; keep in sync only if intentionally mirroring fixes.

**Source of truth for live site:** repo root (`project-memory.md`, `data/news.json`, `includes/`, `admin/`).

**For agents:** Do not scan the whole repo unless this doc is insufficient.  
**Rule:** Treat HTML inside `<!-- ... -->` as non-existent legacy unless the user asks to restore it.

---

## 1. `/test` layout

| Path | Role |
|------|------|
| `index.html` / `index.php` | Landing: hero, about, directions, `#news`, portfolio, stories, footer |
| `news.php` | JSON-driven news archive (canonical list) |
| `news/article.php` | Single article by `?slug=` from `data/news.json` only |
| `data/news.json` | **Single source of truth** for news content (path: `news_data_json_path()` only) |
| `includes/news-data.php` | `load_published_news()` |
| `includes/news-render.php` | `render_news_feed_item()`, `render_news_card()` |
| `css/main.css` | Design tokens + `@import` section styles |
| `js/main.js` | ES module bundle |
| `img/` | `news/`, `directions/`, `projects/`, `vidhuky/` (+ logos, favicons, reviews) |

**Stack:** HTML/CSS/vanilla JS + minimal PHP (no framework). **Planned:** lightweight CMS (JSON → DB), promote from `/test` when ready.

**News slug convention:** `{translit-title}-{YYYY-MM-DD}` → `news/{slug}.html`, `img/news/{slug}/` (`cover.jpg|png`, gallery `{slug}-NN.png`).

---

## 2. `data/news.json`

**Shape:** `{ "items": [ … ] }`

| Field | Type | Rules |
|-------|------|--------|
| `id` | string | Stable key; equals `slug` today |
| `slug` | string | URL-safe, date suffix |
| `title` | string | Headline |
| `date` | string | `YYYY-MM-DD` |
| `excerpt` | string | Cards / archive list |
| `content` | string | HTML for `.news-article__content` (no tags block) |
| `cover` | string | `img/news/{slug}/cover.*` |
| `gallery` | string[] | `img/news/{slug}/{slug}-NN.png` |
| `tags` | string[] | Without `#` |
| `published` | boolean | `false` = hidden from public lists |

**Consumers:**

- `load_published_news()` — filter `published === true`, sort `date` DESC (do not re-sort in templates unless asked).
- `load_published_news_item_by_slug($jsonPath, $slug)` — one published item or `null`.
- `news.php` — full archive feed.
- `index.php` — latest **3** cards in `#news` via `render_news_card()`.
- `news/article.php` — `?slug=` full article (`render_news_article()`); HTTP 404 if missing.

**Authoring:** Prefer editing only `news.json` for news text/metadata once HTML duplication is removed.

**Seed (2026-06-14):** 3 items; full body from `news/nastilni-ihry-2026-05-02.html`; two placeholders until HTML import.

---

## 3. CMS / PHP (implemented)

| File | Purpose |
|------|---------|
| `includes/news-data.php` | Read `data/news.json`, published filter, date DESC |
| `includes/news-render.php` | BEM markup, escaped output |
| `news.php` | JSON-driven archive (`news.html` static copy remains) |
| `index.php` | Dynamic `#news` grid (3 items); `index.html` unchanged |
| `news/article.php` | Single article by `?slug=` from JSON; static `news/*.html` unchanged |

**Migration roadmap:**

1. PHP includes for chrome; render news with existing BEM (`.news-feed__item`, `.news-article`, `.news-gallery`).
2. Admin → JSON or DB; uploads to `img/news/{slug}/`.
3. Retire static `news/*.html`; redirects to slug URL.
4. `BASE_URL` for nav/logo.
5. Gallery: loop `gallery[]`; keep `gallery.js` / `gallery.css`.

---

## 4. Front-end architecture

| Layer | Entry | Notes |
|-------|--------|--------|
| HTML | `index.html` | `lang="uk"` |
| CSS | `css/main.css` | `@import`s sections; `:root` tokens |
| JS | `js/main.js` | ES modules; **all imports must resolve** |

**Live DOM order (active markup):**

1. `header.site-header` (`nav` + `burger`)
2. `section.hero`
3. `section#about.about`
4. `section#directions.directions`
5. `section#news.news-section`
6. `section#portfolio.portfolio` → `section.stories`
7. `footer#contacts.footer`

**Commented in `index.html`:** **Aktualno**, **Services** (CSS/JS may still load). Root nav may still reference `#aktualno` / `#services` on older copies.

```
index.html → css/main.css → section CSS
index.html → js/main.js → header, burger, hero*, gallery, directions,
            portfolio, actualno*, services, footer, back-to-top
            (* hero.js, actualno.js — minimal stubs; gallery.js — lightbox in /test)
```

### Important files

- **`css/main.css`** — Reset, typography, `.btn` / `.cta-btn`, `.back-to-top`, `.reveal`, **global `h2::after` overrides** (end of file).
- **`css/header.css`** — Fixed header, `.scrolled`, burger, mobile `.nav.open` (`fixed; inset: 0`).
- **`css/news.css`** — Archive, cards, article layout, archive header alignment.
- **`css/gallery.css`** + **`js/gallery.js`** — Article grid + lightbox.
- **`js/header.js`** — `.scrolled` when `scrollY > 50`.
- **`js/burger.js`** — `setNavOpen()`; link / overlay / Escape close.
- **`directions.js`** — Card expand; document click resets.
- **`portfolio.js`** — IntersectionObserver, typing classes.
- **`services.js`** — Observer on `.service-card` (no DOM while section commented).
- **`footer.js`** — `.visible` on footer (required to see footer).
- **`back-to-top.js`** — `.visible` after 400px scroll.
- **Section CSS:** `hero`, `about`, `directions`, `portfolio`, `footer`, `aktualno`, `services`, `programs` (programs mostly unused).

### Design tokens (`:root`)

| Token | Value | Use |
|-------|--------|-----|
| `--color-primary` | `#0f4c5c` | Headings, header, footer |
| `--color-accent` | `#e76f51` | CTAs, underlines |
| `--color-secondary` | `#bfd8bd` | Accents |
| `--color-bg` | `#f6f1e9` | Page background |
| `--color-text` | `#1e2d2f` | Body |

Spacing: `--space-1` (8px) … `--space-6` (64px).

---

## 5. Solved bugs (do not re-break)

1. **JS bundle dead** — Missing modules → 404 on import. **Fix:** stubs + full `main.js` import list.
2. **Burger inert** — Same as (1).
3. **Mobile menu = header strip after scroll** — `nav` inside `header`; `backdrop-filter` on `.scrolled` clipped `position: fixed` nav. **Fix:** `backdrop-filter` only `@media (min-width: 769px)` in `header.css`. Never on mobile header.
4. **Portfolio nesting** — Unclosed `#portfolio`; aktualno/services inside portfolio. **Fix:** `</section>` after stories.
5. **Hero invalid markup** — Stray `</div>` removed.
6. **Broken anchors** — Hero `#programs` → `#directions`; `#documents` / `#partners` via empty spans in about.
7. **Responsive** — About img `max-width: 100%`; portfolio stacks ≤768px; directions mobile `min-height` + `height: auto`.
8. **Hero slogans overlap (~800–1200px)** — `max-width: min(260px, 22vw)` on `.hero-slogans`; mobile `position: static`. No desktop two-column hero without UX approval.
9. **Burger X jitter** — Absolute spans, `translate(-50%, ±9px)`; middle line opacity only on active.
10. **Back-to-top sticky hover on touch** — `.back-to-top:hover` inside `@media (hover: hover)` in `main.css`.
11. **Short `h2` underlines** — Shared override in `main.css` (`calc(100% - var(--space-2))`).
12. **Burger overlay desync** — Empty tap changed icon without closing menu. **Fix:** `setNavOpen()`; overlay click (non-link); `pointer-events` on `.nav` / `.nav.open`. Files: `burger.js`, `header.css`.
13. **News archive misalignment** — `.news-archive` header + `.news-feed` share `max-width: var(--content-width)`, `margin-inline: auto` in `news.css`.
14. **Article in-content links not clickable** — `.news-article__content` stacking, cover `pointer-events`, link styles in `news.css`.

---

## 6. Design decisions

- Youth NGO palette (teal / coral / warm bg); CSS variables for spacing/color.
- Breakpoint **768px**: burger overlay; desktop nav **769px+**.
- **`nav` stays inside `header`** (overlay fix depends on desktop-only `backdrop-filter`).
- Footer `opacity: 0` until JS `.visible`.
- Directions: desktop hover `.more-btn`; mobile whole-card tap; `.close-btn` in CSS only.
- `html { scroll-behavior: smooth }` in `main.css`.
- **`data/news.json` is authoritative** for news where PHP/JSON consumers exist.

---

## 7. Known limitations

- Static `news.html` / some `news/*.html` still duplicate metadata vs JSON.
- Nav may mix `index.html`, anchors, production URLs in `/test`.
- `#aktualno` / `#services` commented — dead anchors if nav links remain.
- `#documents` / `#partners` — empty scroll targets in about.
- `services.js` runs without `.service-card` in DOM.
- `burger.js` — guard if `.burger` / `.nav` missing (verify when editing).
- Portfolio typing uses fixed `ch` — may not match Ukrainian glyphs.
- CDN: Google Fonts, Font Awesome.
- `README.md` in `/test` is minimal.

---

## 8. Open tasks (backlog)

- Restore or remove **aktualno** / **services** in HTML and sync nav.
- Wire **full archive** to JSON only (`news.php` vs `news.html` canonical URL).
- **Article template** PHP + retire static `news/*.html`.
- Import remaining article bodies into `news.json`.
- **BASE_URL** config; normalize logo/home links.
- Add or verify **image assets** when adding content.
- Optional: `.close-btn` in directions HTML or remove unused CSS.
- Expand `README.md` for human onboarding.

---

## 9. Stable state & smoke test

**Expected working in `/test`:** header scroll, burger (full overlay + overlay close), directions, portfolio observer, footer reveal, back-to-top, gallery lightbox on article pages, `news.php` / `index.php` news from JSON.

**UX path:** Hero → About → Directions → News → Portfolio/Stories → Contacts.

### Regression smoke test

1. Mobile burger at `scrollY = 0` and after `scrollY > 50` — full-screen menu, not header strip; empty overlay closes menu.
2. Desktop nav; scrolled header blur (≥769px).
3. Footer visible after scrolling to contacts.
4. Direction cards expand/collapse.
5. ~320px: no horizontal scroll on about image.
6. `index.php` `#news` shows 3 cards from JSON; `news.php` lists published items newest first.

---

## 10. Changelog

**2026-06-14 — JSON-only news routing**  
Removed legacy `news/{slug}.html` fallback from `news/article.php`. Archive/nav links use `news.php`; article links use `news_article_href()` → `news/article.php?slug=` from JSON. Static `news/*.html` article files are no longer served by PHP.

**2026-06-14 — Enforced single JSON source of truth**  
All PHP under `/test` resolves news data only via `news_data_json_path()` in `includes/news-data.php` (`test/data/news.json`). Removed `admin_json_path()` alias; updated frontend (`index.php`, `news.php`, `sitemap.php`), admin, migration tools, and CLI scripts. Stale `admin/data/news.json` removed from use.

**2026-06-14 — News load fix (admin + frontend)**  
`test/data/news.json` had invalid JSON (missing comma between first two items), so `json_decode` failed and all PHP loaders returned empty lists. Repaired JSON. Added `news_data_json_path()` in `includes/news-data.php` as the single path to `data/news.json`. Fixed `admin/news.php` and `admin/news/article.php`, which pointed at stale `admin/data/news.json`. Removed duplicate `test/news.json`. Admin `admin-lib.php` now requires canonical `test/includes/news-data.php`.

**2026-06-14 — Memory merge**  
Root `project-memory.md` merged into this file; root doc is redirect only.

**2026-06-14 — `data/news.json` (SSOT)**  
Created schema; seed 3 articles.

**2026-06-14 — PHP news**  
`news-data.php`, `news-render.php`, `news.php`, `index.php` dynamic homepage (3 cards).

**2026-06-14 — Dynamic article (`news/article.php`)**  
`load_published_news_item_by_slug()`; `render_news_article()` / gallery / tags; `?slug=` route; static `news/*.html` retained.

**2026-06-14 — Archive page repair**  
`news.html` restored as full document: `main.news-archive-page` → `.news-feed` rows; `news.css` feed thumbnails 16:10, shared column alignment.

**2026-06-14 — Article content**  
`.news-article__content` links; structure `h2` → `p` → organizer → `p.news-article__tags`.

**2026-06-14 — Burger overlay**  
`setNavOpen()`, overlay click, `pointer-events` on mobile `.nav`.

**2026-06-14 — Dark mode**  
Implemented dark mode with system detection, toggle UI, and localStorage persistence.

**2026-06-14 — Dark mode primary**  
`html.theme-dark` overrides `--color-primary` to `#6eb4c4` for softer teal headings/links and clearer contrast on dark surfaces.

**2026-06-14 — Design system audit**  
Extended `:root` semantic color tokens (hovers, on-primary, overlays, admin status); component CSS and `css/admin.css` now use variables only; fixed admin pages loading stale `admin/css/main.css` without theme support.

**2026-06-14 — Likes removed; views counter fix**  
Removed article like UI/JS; `like.php` returns 410. Fixed view counting: dynamic engagement cookie path, published-only recording, IP meta parsing for JSON objects, cookie set when IP window blocks repeat views.

**2026-06-14 — Dark theme header/footer**  
Dark mode: light brand chip behind header logo (`--color-header-brand-*`). Footer locked to `--color-footer-*` tokens so appearance stays deep-teal + light text in all themes.

**2026-06-14 — Dark theme UI polish**  
Dark theme UI polish pass completed: tuned contrast tokens, elevation shadows, mobile nav overlay colors, and focus/hover/active states for buttons, nav, cards, and links.

**2026-06-14 — Production migration**  
CMS promoted to site root; all runtime `/test` URL prefixes removed from PHP (`news_canonical_article_url`, `sitemap.php`, SEO fallback `img/logo.png`, engagement cookie path). `/test` retained as archive only.

---

*Last updated: merged canonical memory for `/test` staging + CMS.*
