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
| Legacy static articles | `/test/news/*.html` (read-only; do not edit) |

**Stack in `/test`:** static HTML/CSS/JS + minimal PHP. **JSON owns news content.**

---

## Routing

- **Article links** from PHP archive/home (`render_news_feed_item`, `render_news_card`):  
  `news/article.php?slug={slug}` via `news_article_href()`.
- **Deprecated:** `news/{slug}.html` — files remain on disk for legacy direct URLs; do not link from PHP lists.
- Static `news.html` / `index.html` use `news/article.php?slug=` for article links (legacy static archive/home HTML updated; PHP lists via `news_article_href()`).

---

## Strict rules (agents)

1. Work **only** in `/test` (except this file).
2. No parallel news systems; all CRUD via `/test/data/news.json`.
3. New posts → dynamic `article.php?slug=`, not new static HTML files.
4. After each task → update **this file** only (task log). No other memory files.

---

## News data consistency

- `id` === `slug`; `date` → `YYYY-MM-DD`.
- `cover` / `gallery` under `img/news/{slug}/`.
- `published === true` for public views.

---

## PHP API (summary)

- `load_published_news()`, `load_published_news_item_by_slug()`
- `render_news_feed_item()`, `render_news_card()`, `render_news_article()`, `render_news_article_not_found()`
- `news_article_href()` → `news/article.php?slug=…`

---

## Task log (latest first)

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
