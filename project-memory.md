# Project memory — trainings-landing

**Active development:** only **`/test`**. Do not change site files outside `/test` except this file.

---

## Working context

| Area | Location |
|------|----------|
| News data (SSOT) | `/test/data/news.json` |
| Load / filter | `/test/includes/news-data.php` |
| Render | `/test/includes/news-render.php` |
| Archive | `/test/news.php` |
| Article (dynamic) | `/test/news/article.php?slug=` |
| Homepage news (3 items) | `/test/index.php` |
| Legacy static articles | `/test/news/*.html` (fallback when slug not in JSON; files not edited) |
| News admin (staging) | `/test/admin/index.php` → `save.php` |

**Stack in `/test`:** static HTML/CSS/JS + minimal PHP. **JSON owns news content.**

---

## Routing

- **Article links** from PHP archive/home (`render_news_feed_item`, `render_news_card`):  
  `news/article.php?slug={slug}` via `news_article_href()`.
- **Deprecated:** `news/{slug}.html` — files remain on disk for legacy direct URLs; do not link from PHP lists.
- Static `news.html` / `index.html` use `news/article.php?slug=` for article links (legacy static archive/home HTML updated; PHP lists via `news_article_href()`).

**`news/article.php` (hybrid resolution):**

1. Normalize `slug` (`news_normalize_article_slug`).
2. **Preview** (`?preview=1`): `load_news_item_by_slug` (draft or published) → PHP render; **no** legacy HTML fallback.
3. **Normal:** `load_published_news_item_by_slug` → render; if missing, legacy `news/{slug}.html` via `readfile`.
4. **404:** no JSON match (per mode) and no legacy (normal mode only) → `render_news_article_not_found()`.

Draft preview URL: `news/article.php?slug={slug}&preview=1` (not linked from public lists).

**SEO / Open Graph** (JSON-rendered pages only; legacy `readfile` unchanged): `<title>` `{title} | Сила інтелекту`, `meta description` = excerpt; `og:title`, `og:description`, `og:type=article`, `og:image` = `https://silaintellect.org/{cover}`, `og:url` = canonical `https://silaintellect.org/news/article.php?slug={slug}`. 404: title `Новина не знайдена | Сила інтелекту`, description `Запис відсутній` (no OG tags).

---

## Strict rules (agents)

1. Work **only** in `/test` (except this file).
2. No parallel news systems; all CRUD via `/test/data/news.json`.
3. New posts → dynamic `article.php?slug=`, not new static HTML files.
4. After each task → update **this file** only (task log). No other memory files.

---

## Roadmap (after publish to production — not started)

Order of intent:

1. **Draft system** — `published` in JSON + admin list/checkbox; public lists use `load_published_news()` only.
2. **Preview mode** — **done in `/test`:** `article.php?slug=…&preview=1` (see routing above).
3. **SEO meta per article** — **done in `/test`:** dynamic meta + Open Graph in `article.php` from `title`, `excerpt`, `cover`, `slug`.
4. **Auto sitemap generation** — from published JSON items (and static routes as applicable).
5. **THEN** — optimize / retire **legacy HTML** articles (only after JSON + routing are stable in prod).

Public routing (`article.php?slug=`) stays the contract; roadmap items should not fork parallel article URLs.

---

## News data consistency

- `id` === `slug`; `date` → `YYYY-MM-DD`.
- `cover` / `gallery` under `img/news/{slug}/`.
- `published === true` for public views.

---

## Admin (`/test/admin`)

PHP UI — **reads/writes only** `/test/data/news.json` (no database). Public routing unchanged (`news/article.php?slug=`).

- **`admin-lib.php`** — load/save JSON (`LOCK_EX`), validation, slug uniqueness, **image uploads** to `test/img/news/{slug}/`, gallery path safety.
- **`index.php`** — item list; create/edit form with cover upload + preview, gallery keep/remove + multi-file upload previews (`enctype="multipart/form-data"`).
- **`save.php`** — create / update / delete; on save: optional folder rename when slug changes; cover file overrides manual path; `gallery_keep[]` + new uploads → `gallery[]` in JSON.

**Images:** stored under `/test/img/news/{slug}/` (`cover.{ext}`, `{slug}-NN.{ext}`). JSON paths: `img/news/{slug}/…`.

**JSON mutation:** read full file → decode → modify `items` → encode → `LOCK_EX` write.

Staging only; no auth in this minimal build.

---

## PHP API (summary)

- `load_all_news()`, `load_published_news()`, `load_news_item_by_slug()`, `load_published_news_item_by_slug()`
- `news_normalize_article_slug()`, `news_legacy_article_file()` — hybrid article resolution
- `render_news_feed_item()`, `render_news_card()`, `render_news_article()`, `render_news_article_not_found()`
- `news_article_href()` → `news/article.php?slug=…`

---

## Task log (latest first)

### 2026-06-14 — Article SEO + Open Graph

- **Created:** none
- **Modified:** `test/news/article.php`, `project-memory.md`
- **Logic:** Meta title/description + OG tags from JSON (`title`, `excerpt`, `cover`, `slug`); works in normal and `preview=1` modes; 404 fallbacks.

### 2026-06-14 — Article preview mode (`?preview=1`)

- **Created:** none
- **Modified:** `test/news/article.php`, `test/includes/news-data.php`, `project-memory.md`
- **Logic:** Preview loads any JSON item by slug (including drafts); legacy HTML skipped. Normal requests unchanged.

### 2026-06-14 — Admin image upload + gallery CMS UI

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `test/admin/admin-lib.php`, `project-memory.md`
- **Logic:** Cover/gallery uploads to `test/img/news/{slug}/`; gallery manager with keep/remove + previews; JSON `gallery[]` from kept paths + new files. No changes to `article.php` / public routing.

### 2026-06-14 — Admin edit / delete (JSON CRUD)

- **Created:** `test/admin/admin-lib.php`
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `project-memory.md`
- **Logic:** List all items; edit by slug with rename (`id` synced); delete via POST; full-file read/write. `article.php` / public routing not touched.

### 2026-06-14 — Minimal news admin (JSON write)

- **Created:** `test/admin/index.php`, `test/admin/save.php`
- **Modified:** `project-memory.md`
- **Logic:** Admin appends validated items to `test/data/news.json`; existing items preserved; duplicate slug rejected.

### 2026-06-14 — Hybrid article resolution (JSON + legacy HTML)

- **Created:** none
- **Modified:** `test/news/article.php`, `test/includes/news-data.php`, `project-memory.md`
- **Logic:** JSON-first render; missing slug in JSON falls back to readable `test/news/{slug}.html`; invalid slug or dual miss → 404. Legacy HTML not modified.

### 2026-06-14 — Legacy article link sweep

- **Created:** none
- **Modified:** `test/index.html`, `test/news.html`, `project-memory.md`
- **Routing:** All `href="news/{slug}.html"` in static archive/home pages → `href="news/article.php?slug={slug}"`. PHP paths (`news-render.php`, `index.php`, `news.php`) already used `news_article_href()`. **`/test/news/*.html` article files not modified** (preserved as direct-URL fallback).

### 2026-06-14 — Article routing from PHP lists

- **Created:** none
- **Modified:** `test/includes/news-render.php`, `project-memory.md`
- **Logic:** `news_article_href()` now points to `news/article.php?slug=` instead of legacy `news/{slug}.html`; affects `news.php` and `index.php` output only. Legacy HTML in `/test/news/*.html` unchanged.

### 2026-06-14 — Dynamic article page

- **Created:** `test/news/article.php`
- **Modified:** `test/includes/news-data.php`, `test/includes/news-render.php`
- **Logic:** Slug lookup; full article render + 404 fallback.

### 2026-06-14 — Homepage + archive PHP

- **Created:** `test/index.php`, `test/news.php`, `test/includes/news-data.php`, `test/includes/news-render.php`
- **Logic:** JSON-driven archive and homepage cards.

### 2026-06-14 — News SSOT

- **Created:** `test/data/news.json`

---

*Update the **Task log** after every `/test` change.*
