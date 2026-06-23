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
| News admin (staging) | `/test/admin/` (login required) |

**Stack in `/test`:** static HTML/CSS/JS + minimal PHP. **JSON owns news content.**

---

## Routing

- **Article links** from PHP archive/home (`render_news_feed_item`, `render_news_card`):  
  `news/article.php?slug={slug}` via `news_article_href()`.
- **Deprecated:** `news/{slug}.html` — files remain on disk for legacy direct URLs; do not link from PHP lists.
- Static `news.html` / `index.html` use `news/article.php?slug=` for article links (legacy static archive/home HTML updated; PHP lists via `news_article_href()`).

**`news/article.php` (hybrid resolution):**

| Mode | JSON lookup | Legacy HTML |
|------|-------------|-------------|
| `?preview=1` | `load_news_item_by_slug` (draft or published) | Never |
| default | `load_published_news_item_by_slug` only | If JSON miss → `news/{slug}.html` |

1. Normalize `slug` (`news_normalize_article_slug`).
2. Apply table above.
3. **404:** no item for mode → `render_news_article_not_found()` (preview never uses legacy).

Draft preview URL: `news/article.php?slug={slug}&preview=1` (not linked from public lists).

**SEO / Open Graph** (JSON-rendered pages only; legacy `readfile` unchanged): from loaded item — `<title>`, `description`, `keywords` (tags), `canonical`, Open Graph + Twitter Card; absolute URLs `{scheme}://{host}/test/news/article.php?slug={slug}`; `og:image` = cover, else first gallery image, else `test/img/logo.png`. 404: `<title>` only, no SEO meta. Preview uses same tags from draft JSON (canonical without `preview=1`).

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
- Slugs: create via `news_generate_slug_for_create()` (`news_sanitize_slug_candidate` + date); **immutable after save** (edit updates title/date/content only); uniqueness via `news_article_slug_is_taken()` on create.
- `cover` / `gallery` under `img/news/{slug}/`.
- `published === true` for public views; admin/list uses `news_item_is_published()` for display (accepts JSON boolean and common truthy forms).

---

## Admin (`/test/admin`)

PHP UI — **reads/writes only** `/test/data/news.json` (no database). **Session auth** protects all admin pages except `login.php` / `logout.php`.

- **`test/config/admin-auth.php`** — username + `password_hash` (include-only; not a public page).
- **`test/config/admin-auth.example.php`** — template for new installs.
- **`auth.php`** — session, `admin_require_auth()`, `password_verify()`.
- **`login.php`** / **`logout.php`** — sign in / sign out.
- **`change-password.php`** — update `password_hash` in config (authenticated).
- **`hash-password.php`** — CLI helper to generate a new bcrypt hash.
- **`admin-lib.php`**, **`index.php`**, **`save.php`** — CRUD/upload; list **Перегляд** → `../news/article.php?slug=&preview=1` (new tab); **toggle_published** flips `published` in JSON.

**Default staging login:** `admin` / `password` — change `password_hash` in `test/config/admin-auth.php` before production.

---

## PHP API (summary)

- `load_all_news()`, `load_published_news()`, `load_news_item_by_slug()`, `load_published_news_item_by_slug()`
- `news_normalize_article_slug()`, `news_generate_slug_for_create()` (create only), `news_article_slug_is_taken()`, `news_legacy_article_file()`
- `news_item_is_published()` — normalized read for list, filters, and edit form
- `news_format_admin_content()`, `news_content_looks_like_html()` — admin plain-text → `<p>` on save only
- `render_news_feed_item()`, `render_news_card()`, `render_news_article()`, `render_news_article_not_found()`
- `news_article_href()` → `news/article.php?slug=…`

---

## Task log (latest first)

### 2026-06-14 — Immutable article slugs (CMS)

- **Created:** none
- **Modified:** `test/admin/save.php`, `test/includes/news-data.php`, `test/admin/index.php`, `test/admin/admin-lib.php`, `project-memory.md`
- **Logic:** `news_generate_slug_for_create()` on create only; edit keeps JSON slug; no img dir rename.

### 2026-06-14 — Admin auto slug (title + date)

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `test/includes/news-data.php`, `test/admin/admin-lib.php`, `project-memory.md`
- **Logic:** Slug generated once on create; edit keeps stored slug via `old_slug` lookup; uniqueness error on create conflict only.

### 2026-06-14 — Published news XML sitemap

- **Created:** `test/sitemap.php`
- **Modified:** `project-memory.md`
- **Logic:** `load_published_news()` only; absolute `/test/news/article.php?slug=` URLs; no legacy HTML.

### 2026-06-14 — Admin list filter + top-5 collapse

- **Created:** none
- **Modified:** `test/admin/index.php`, `project-memory.md`
- **Logic:** Server-side `?filter=` on loaded JSON; display slice only (sort unchanged in `load_all_news`).

### 2026-06-14 — Admin dashboard UX polish

- **Created:** none
- **Modified:** `test/admin/index.php`, `project-memory.md`
- **Logic:** UI only — table hierarchy, action button groups, English labels, delete confirm unchanged.

### 2026-06-14 — Article SEO + OG + Twitter (JSON)

- **Created:** none
- **Modified:** `test/news/article.php`, `project-memory.md`
- **Logic:** Full head metadata from JSON; dynamic host + `/test/news/article.php`; preview drafts included; legacy HTML unchanged.

### 2026-06-14 — Admin preview + publish toggle UX

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `project-memory.md`
- **Logic:** List preview link (`article.php?slug=&preview=1` new tab); `toggle_published` POST flips `published` in JSON via `admin_persist_data`.

### 2026-06-14 — Admin change password UI

- **Created:** `test/admin/change-password.php`
- **Modified:** `test/admin/auth.php`, `test/admin/index.php`, `project-memory.md`
- **Logic:** Verify old password; `password_hash()` + rewrite `test/config/admin-auth.php` with `LOCK_EX`; nav link on dashboard.

### 2026-06-14 — Admin session authentication

- **Created:** `test/admin/auth.php`, `test/admin/login.php`, `test/admin/logout.php`, `test/admin/hash-password.php`, `test/config/admin-auth.php`, `test/config/admin-auth.example.php`
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `project-memory.md`
- **Logic:** PHP sessions; credentials in `test/config/admin-auth.php`; `index.php` + `save.php` require auth; logout in nav.

### 2026-06-14 — Admin UI aligned with site design

- **Created:** none
- **Modified:** `test/admin/index.php`, `project-memory.md`
- **Logic:** Markup/CSS only — `main.css`, header/footer, `section-title`, `.btn`; no PHP behavior changes.

### 2026-06-14 — Admin published badge fix

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/admin-lib.php`, `test/includes/news-data.php`, `project-memory.md`
- **Root cause:** Status used strict `published === true`; JSON/POST truthy values (`1`, `"true"`, `"1"`) read as draft. Fixed with `news_item_is_published()` (`filter_var` boolean).

### 2026-06-14 — Admin delete confirmation

- **Created:** none
- **Modified:** `test/admin/index.php`, `project-memory.md`
- **Logic:** Native `confirm()` on `form[data-action="delete"]` before POST; `save.php` delete unchanged.

### 2026-06-14 — Preview mode (article resolution)

- **Created:** none
- **Modified:** `test/news/article.php`, `project-memory.md`
- **Logic:** `?preview=1` → JSON-only via `load_news_item_by_slug`; default → published JSON then legacy HTML. Already aligned with draft workflow.

### 2026-06-14 — Admin gallery queue + plain-text content → HTML

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `test/includes/news-data.php`, `project-memory.md`
- **Logic:** Client-side gallery file queue with append/remove; submit syncs all files. `news_format_admin_content()` on save for plain text.

### 2026-06-14 — Slug normalization and uniqueness

- **Created:** none
- **Modified:** `test/includes/news-data.php`, `test/admin/save.php`, `project-memory.md`
- **Logic:** Central slug sanitize/generate/resolve in `news-data.php`; admin save enforces unique slug/id; edit matches by slug or id; legacy HTML fallback unchanged.

### 2026-06-14 — Admin publish + gallery upload fixes

- **Created:** none
- **Modified:** `test/admin/index.php`, `test/admin/save.php`, `test/admin/admin-lib.php`, `project-memory.md`
- **Logic:** Hidden `published=0` + checkbox; `admin_parse_published_from_post()`. Gallery JS no longer clears file input on change; `admin_normalize_uploaded_files()` for multipart gallery.

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
