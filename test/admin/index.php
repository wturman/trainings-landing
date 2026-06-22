<?php

declare(strict_types=1);

require __DIR__ . '/admin-lib.php';

$message = '';
$messageType = '';

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $message = 'Новину додано в news.json.';
    $messageType = 'ok';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Новину оновлено в news.json.';
    $messageType = 'ok';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $message = 'Новину видалено з news.json.';
    $messageType = 'ok';
} elseif (isset($_GET['ok']) && $_GET['ok'] === '1') {
    $message = 'Новину збережено в news.json.';
    $messageType = 'ok';
} elseif (isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== '') {
    $message = $_GET['error'];
    $messageType = 'error';
}

$jsonPath = __DIR__ . '/../data/news.json';
$items = load_all_news($jsonPath);

$editSlug = isset($_GET['edit']) ? news_normalize_article_slug((string) $_GET['edit']) : null;
$editItem = $editSlug !== null ? load_news_item_by_slug($jsonPath, $editSlug) : null;
$isEdit = $editItem !== null;

$today = (new DateTimeImmutable('now'))->format('Y-m-d');

$formTitle = $isEdit ? (string) ($editItem['title'] ?? '') : '';
$formSlug = $isEdit ? (string) ($editItem['slug'] ?? '') : '';
$formDate = $isEdit ? (string) ($editItem['date'] ?? $today) : $today;
$formExcerpt = $isEdit ? (string) ($editItem['excerpt'] ?? '') : '';
$formContent = $isEdit ? (string) ($editItem['content'] ?? '') : '';
$formCover = $isEdit ? (string) ($editItem['cover'] ?? '') : '';
$formGalleryPaths = $isEdit && is_array($editItem['gallery'] ?? null) ? $editItem['gallery'] : [];
$formTags = $isEdit ? admin_tags_to_text(is_array($editItem['tags'] ?? null) ? $editItem['tags'] : []) : '';
$formPublished = $isEdit ? news_item_is_published($editItem) : false;
$coverPreviewSrc = $formCover !== '' && admin_is_safe_news_asset_path($formCover)
    ? admin_public_asset_href($formCover)
    : '';
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="icon" type="image/png" href="../img/favicon-16x16.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title>Адмін — новини — ГО «Сила інтелекту»</title>
    <link rel="stylesheet" href="../css/main.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
    <style>
      .admin-page { padding-bottom: var(--space-6); }
      .admin-page .news-archive { max-width: 52rem; margin: 0 auto; padding: var(--space-4) var(--space-2); }
      .admin-panel__meta { color: var(--color-text-muted); font-size: 0.95rem; margin-bottom: var(--space-4); }
      .admin-panel__meta code { font-size: 0.85em; }
      .admin-msg { padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-3); border: 1px solid var(--color-border); }
      .admin-msg.ok { background: #e8f5e9; color: #1b5e20; border-color: #c8e6c9; }
      .admin-msg.error { background: #ffebee; color: #b71c1c; border-color: #ffcdd2; }
      .admin-section { margin-top: var(--space-5); }
      .admin-section h2.section-title { margin-bottom: var(--space-3); }
      .admin-table-wrap { overflow-x: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
      .admin-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; font-family: var(--font-body); }
      .admin-table th, .admin-table td { text-align: left; padding: var(--space-2); border-bottom: 1px solid var(--color-border); vertical-align: top; }
      .admin-table th { font-family: var(--font-heading); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted); background: var(--color-bg); }
      .admin-table tr:last-child td { border-bottom: none; }
      .admin-table code { font-size: 0.8em; color: var(--color-primary); }
      .admin-actions { white-space: nowrap; }
      .admin-actions form { display: inline; margin: 0; }
      .admin-actions .btn-link { margin-right: var(--space-2); color: var(--color-primary); font-weight: 600; text-decoration: underline; }
      .admin-actions .btn-link:hover { color: var(--color-accent); }
      .admin-form { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: var(--space-4); margin-top: var(--space-3); }
      .admin-form label { display: block; margin-top: var(--space-3); font-family: var(--font-heading); font-weight: 600; color: var(--color-primary); font-size: 0.95rem; }
      .admin-form label:first-of-type { margin-top: 0; }
      .admin-form input[type="text"], .admin-form input[type="date"], .admin-form textarea, .admin-form input[type="file"] {
        width: 100%; box-sizing: border-box; margin-top: var(--space-1); padding: var(--space-2);
        border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 1rem; background: var(--color-surface);
      }
      .admin-form textarea { min-height: 6rem; resize: vertical; }
      .admin-form #content { min-height: 10rem; }
      .admin-hint { font-weight: 400; font-size: 0.875rem; color: var(--color-text-muted); margin-top: var(--space-1); }
      .admin-form .row-check { margin-top: var(--space-3); font-family: var(--font-heading); }
      .admin-form .row-check input[type="checkbox"] { width: auto; margin-right: var(--space-1); }
      .admin-form-actions { margin-top: var(--space-4); display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
      .admin-form-actions .btn-cancel { color: var(--color-text-muted); font-weight: 600; }
      .admin-form-actions .btn-cancel:hover { color: var(--color-primary); }
      .admin-media-block { margin-top: var(--space-2); padding: var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-bg); }
      .admin-cover-preview { margin-top: var(--space-2); max-width: 280px; }
      .admin-cover-preview img { display: block; max-width: 100%; height: auto; border-radius: var(--radius-sm); border: 1px solid var(--color-border); }
      .admin-gallery-grid { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-2); }
      .admin-gallery-item { width: 96px; }
      .admin-gallery-item img { width: 96px; height: 72px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--color-border); display: block; }
      .admin-gallery-item .btn { width: 100%; margin-top: var(--space-1); padding: 6px 8px; font-size: 0.75rem; }
      .admin-gallery-item .path { font-size: 0.65rem; color: var(--color-text-muted); word-break: break-all; margin-top: 4px; }
      .admin-badge { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: var(--radius-sm); font-family: var(--font-heading); }
      .admin-badge-published { background: #e8f5e9; color: #1b5e20; }
      .admin-badge-draft { background: #fff3e0; color: #e65100; }
      .btn.btn-danger { background: #c62828; }
      .btn.btn-danger:hover { background: #b71c1c; }
      .btn.btn-secondary { background: var(--color-primary); }
      .btn.btn-secondary:hover { background: #0a3340; }
    </style>
  </head>
  <body>
    <header class="site-header">
      <div class="header-inner">
        <a href="../index.html" class="logo-link" aria-label="Перейти на головну сторінку">
          <div class="logo-block">
            <img src="../img/logo.png" alt="Логотип громадської організації Сила інтелекту" class="logo-img" />
            <img src="../img/logo-text.png" alt="Логотип громадської організації Сила інтелекту" class="logo-text-img" />
          </div>
        </a>
        <nav class="nav">
          <ul>
            <li><a href="../index.html">Головна</a></li>
            <li><a href="../news.php">Новини</a></li>
            <li><a href="index.php" aria-current="page">Адмін</a></li>
          </ul>
        </nav>
        <button class="burger" aria-label="Відкрити меню" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </header>

    <main class="news-archive-page admin-page">
      <section class="news-archive admin-panel" aria-labelledby="admin-page-title">
        <p class="news-archive__back"><a href="../news.php">← Публічний архів новин</a></p>
        <h1 id="admin-page-title">Керування новинами</h1>
        <p class="news-archive__lead">Редагування записів у <code>data/news.json</code>. Публічні посилання: <code>news/article.php?slug=</code>.</p>

<?php if ($message !== ''): ?>
        <p class="admin-msg <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

        <div class="admin-section">
          <h2 class="section-title">Усі записи</h2>
<?php if ($items === []): ?>
          <p class="admin-hint">Записів немає.</p>
<?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Дата</th>
                  <th>Заголовок</th>
                  <th>Slug</th>
                  <th>Статус</th>
                  <th>Дії</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($items as $row): ?>
<?php
    if (!is_array($row)) {
        continue;
    }
    $rowSlug = (string) ($row['slug'] ?? '');
    if ($rowSlug === '') {
        continue;
    }
    $rowPublished = news_item_is_published($row);
?>
                <tr>
                  <td><?= htmlspecialchars((string) ($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><code><?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?></code></td>
                  <td>
<?php if ($rowPublished): ?>
                    <span class="admin-badge admin-badge-published">Published</span>
<?php else: ?>
                    <span class="admin-badge admin-badge-draft">Draft</span>
<?php endif; ?>
                  </td>
                  <td class="admin-actions">
                    <a class="btn-link" href="index.php?<?= htmlspecialchars(http_build_query(['edit' => $rowSlug]), ENT_QUOTES, 'UTF-8') ?>">Редагувати</a>
                    <form method="post" action="save.php" class="admin-delete-form" data-action="delete">
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="slug" value="<?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?>" />
                      <button type="submit" class="btn btn-danger" data-action="delete">Видалити</button>
                    </form>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php endif; ?>
        </div>

        <div class="admin-section">
          <h2 class="section-title"><?= $isEdit ? 'Редагувати новину' : 'Нова новина' ?></h2>
<?php if ($editSlug !== null && !$isEdit): ?>
          <p class="admin-msg error">Запис з slug «<?= htmlspecialchars($editSlug, ENT_QUOTES, 'UTF-8') ?>» не знайдено.</p>
<?php endif; ?>

          <form id="news-admin-form" class="admin-form" method="post" action="save.php" enctype="multipart/form-data">
<?php if ($isEdit): ?>
            <input type="hidden" name="old_slug" value="<?= htmlspecialchars($formSlug, ENT_QUOTES, 'UTF-8') ?>" />
<?php endif; ?>
            <label for="title">Заголовок</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?>" required />

            <label for="slug">Slug <span class="admin-hint">(a-z, 0-9, дефіс; при створенні порожньо = авто)</span></label>
            <input
              type="text"
              id="slug"
              name="slug"
              value="<?= htmlspecialchars($formSlug, ENT_QUOTES, 'UTF-8') ?>"
              pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              <?= $isEdit ? 'required' : '' ?>
            />

            <label for="date">Дата</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($formDate, ENT_QUOTES, 'UTF-8') ?>" required />

            <label for="excerpt">Короткий опис (excerpt)</label>
            <textarea id="excerpt" name="excerpt" required><?= htmlspecialchars($formExcerpt, ENT_QUOTES, 'UTF-8') ?></textarea>

            <label for="content">Контент</label>
            <textarea id="content" name="content" required><?= htmlspecialchars($formContent, ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="admin-hint">Звичайний текст: порожній рядок між абзацами → при збереженні обгортається в &lt;p&gt;. Якщо вставляєте HTML — залишається без змін.</p>

            <label for="cover">Обкладинка</label>
            <div class="admin-media-block">
              <label for="cover_file" class="admin-hint">Завантажити файл (перекриває шлях нижче)</label>
              <input type="file" id="cover_file" name="cover_file" accept="image/jpeg,image/png,image/gif,image/webp" />

              <label for="cover" class="admin-hint" style="display:block;margin-top:var(--space-2);">Або шлях вручну</label>
              <input type="text" id="cover" name="cover" value="<?= htmlspecialchars($formCover, ENT_QUOTES, 'UTF-8') ?>" placeholder="img/news/{slug}/cover.jpg" />

              <div class="admin-cover-preview" id="cover-preview"<?= $coverPreviewSrc === '' ? ' hidden' : '' ?>>
                <img id="cover-preview-img" src="<?= htmlspecialchars($coverPreviewSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" />
              </div>
            </div>

            <label for="gallery_files">Галерея</label>
            <div class="admin-media-block">
              <p class="admin-hint">Поточні зображення (зніміть «Прибрати» перед збереженням, щоб видалити з JSON). Додайте нові файли нижче.</p>
              <div class="admin-gallery-grid" id="gallery-keep">
<?php foreach ($formGalleryPaths as $gPath): ?>
<?php
    if (!is_string($gPath) || $gPath === '' || !admin_is_safe_news_asset_path($gPath)) {
        continue;
    }
    $gHref = admin_public_asset_href($gPath);
?>
                <div class="admin-gallery-item" data-keep-item>
                  <img src="<?= htmlspecialchars($gHref, ENT_QUOTES, 'UTF-8') ?>" alt="" />
                  <input type="hidden" name="gallery_keep[]" value="<?= htmlspecialchars($gPath, ENT_QUOTES, 'UTF-8') ?>" />
                  <button type="button" class="btn btn-danger gallery-remove">Прибрати</button>
                  <div class="path"><?= htmlspecialchars($gPath, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
<?php endforeach; ?>
              </div>

              <label for="gallery_files" class="admin-hint" style="display:block;margin-top:var(--space-3);">Додати зображення</label>
              <p class="admin-hint">Оберіть файл(и) — можна повторювати вибір; усі з’являться у списку нижче і збережуться після «Зберегти».</p>
              <input type="file" id="gallery_files" name="gallery_files[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple />

              <p id="gallery-pending-count" class="admin-hint" style="display: none;"></p>
              <div class="admin-gallery-grid" id="gallery-new-preview"></div>
              <button type="button" id="gallery-clear-new" class="btn btn-secondary" style="margin-top: var(--space-2); display: none;">Очистити нові файли</button>
            </div>

            <label for="tags">Теги <span class="admin-hint">(через кому)</span></label>
            <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($formTags, ENT_QUOTES, 'UTF-8') ?>" />

            <p class="row-check">
              <label>
                <input type="hidden" name="published" value="0" />
                <input type="checkbox" name="published" value="1" <?= $formPublished ? 'checked' : '' ?> />
                Published <span class="admin-hint">(знято = чернетка, не показується на сайті)</span>
              </label>
            </p>

            <div class="admin-form-actions">
              <button type="submit" class="btn"><?= $isEdit ? 'Зберегти зміни' : 'Додати новину' ?></button>
<?php if ($isEdit): ?>
              <a href="index.php" class="btn-cancel">Скасувати редагування</a>
<?php endif; ?>
            </div>
          </form>
        </div>
      </section>
    </main>

    <footer id="contacts" class="footer">
      <h2>Контакти</h2>
      <p>Email: <a href="mailto:sintelektu@gmail.com">sintelektu@gmail.com</a></p>
      <p>Телефон: <a href="tel:+380958896063">+38 0958896063</a></p>
      <div class="social-links">
        <a
          href="https://www.facebook.com/share/18ganyaLtY/"
          class="social-icon facebook"
          aria-label="Facebook"
          target="_blank"
          rel="noopener noreferrer"
        >
          <i class="fab fa-facebook-f"></i>
        </a>
      </div>
    </footer>

    <button class="back-to-top" aria-label="Вгору">&#8593;</button>
    <script type="module" src="../js/main.js"></script>
    <script>
      (function () {
        const deleteConfirmMessage = 'Ви впевнені, що хочете видалити цю новину? Цю дію неможливо скасувати.';

        document.querySelectorAll('form[data-action="delete"]').forEach(function (form) {
          form.addEventListener('submit', function (e) {
            if (!window.confirm(deleteConfirmMessage)) {
              e.preventDefault();
            }
          });
        });
      })();
    </script>
    <script>
      (function () {
        const coverInput = document.getElementById('cover');
        const coverFile = document.getElementById('cover_file');
        const coverPreview = document.getElementById('cover-preview');
        const coverPreviewImg = document.getElementById('cover-preview-img');
        let coverObjectUrl = null;

        function setCoverPreview(src) {
          if (!src) {
            coverPreview.hidden = true;
            coverPreviewImg.removeAttribute('src');
            return;
          }
          coverPreview.hidden = false;
          coverPreviewImg.src = src;
        }

        function coverPathToSrc(path) {
          const trimmed = (path || '').trim();
          if (!trimmed) return '';
          if (/^https?:\/\//i.test(trimmed)) return trimmed;
          return '../' + trimmed.replace(/^\//, '');
        }

        coverInput.addEventListener('input', function () {
          if (coverObjectUrl) return;
          setCoverPreview(coverPathToSrc(coverInput.value));
        });

        coverFile.addEventListener('change', function () {
          if (coverObjectUrl) {
            URL.revokeObjectURL(coverObjectUrl);
            coverObjectUrl = null;
          }
          const file = coverFile.files && coverFile.files[0];
          if (!file) {
            setCoverPreview(coverPathToSrc(coverInput.value));
            return;
          }
          coverObjectUrl = URL.createObjectURL(file);
          setCoverPreview(coverObjectUrl);
        });

        document.getElementById('gallery-keep').addEventListener('click', function (e) {
          const btn = e.target.closest('.gallery-remove');
          if (!btn) return;
          const item = btn.closest('[data-keep-item]');
          if (item) item.remove();
        });

        const galleryFiles = document.getElementById('gallery_files');
        const galleryNewPreview = document.getElementById('gallery-new-preview');
        const galleryClearNew = document.getElementById('gallery-clear-new');
        const galleryPendingCount = document.getElementById('gallery-pending-count');
        const newsForm = document.getElementById('news-admin-form');
        let galleryPendingFiles = [];
        let galleryObjectUrls = [];

        function revokeGalleryObjectUrls() {
          galleryObjectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
          galleryObjectUrls = [];
        }

        function renderGalleryPendingPreviews() {
          revokeGalleryObjectUrls();
          galleryNewPreview.innerHTML = '';

          galleryPendingFiles.forEach(function (entry, index) {
            const url = URL.createObjectURL(entry.file);
            galleryObjectUrls.push(url);

            const wrap = document.createElement('div');
            wrap.className = 'admin-gallery-item';
            wrap.dataset.pendingIndex = String(index);

            const img = document.createElement('img');
            img.src = url;
            img.alt = entry.file.name;
            wrap.appendChild(img);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger gallery-remove-pending';
            removeBtn.textContent = 'Прибрати';
            removeBtn.dataset.index = String(index);
            wrap.appendChild(removeBtn);

            const cap = document.createElement('div');
            cap.className = 'path';
            cap.textContent = entry.file.name;
            wrap.appendChild(cap);

            galleryNewPreview.appendChild(wrap);
          });

          const count = galleryPendingFiles.length;
          if (count > 0) {
            galleryClearNew.style.display = 'inline-block';
            galleryPendingCount.style.display = 'block';
            galleryPendingCount.textContent = 'До збереження: ' + count + ' нов. зображ.';
          } else {
            galleryClearNew.style.display = 'none';
            galleryPendingCount.style.display = 'none';
            galleryPendingCount.textContent = '';
          }
        }

        function clearGalleryPending() {
          galleryPendingFiles = [];
          renderGalleryPendingPreviews();
          galleryFiles.value = '';
        }

        galleryClearNew.addEventListener('click', clearGalleryPending);

        galleryNewPreview.addEventListener('click', function (e) {
          const btn = e.target.closest('.gallery-remove-pending');
          if (!btn) {
            return;
          }
          const index = parseInt(btn.dataset.index || '', 10);
          if (Number.isNaN(index)) {
            return;
          }
          galleryPendingFiles.splice(index, 1);
          renderGalleryPendingPreviews();
        });

        galleryFiles.addEventListener('change', function () {
          const picked = galleryFiles.files ? Array.from(galleryFiles.files) : [];
          picked.forEach(function (file) {
            galleryPendingFiles.push({ file: file });
          });
          galleryFiles.value = '';
          renderGalleryPendingPreviews();
        });

        newsForm.addEventListener('submit', function () {
          const dt = new DataTransfer();
          galleryPendingFiles.forEach(function (entry) {
            dt.items.add(entry.file);
          });
          galleryFiles.files = dt.files;
        });
      })();
    </script>
  </body>
</html>
