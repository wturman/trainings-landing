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

$data = admin_load_data();
$items = load_all_news(__DIR__ . '/../data/news.json');

$editSlug = isset($_GET['edit']) ? news_normalize_article_slug((string) $_GET['edit']) : null;
$editItem = $editSlug !== null ? admin_find_item_by_slug($data, $editSlug) : null;
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
$formPublished = $isEdit ? (($editItem['published'] ?? false) === true) : false;
$coverPreviewSrc = $formCover !== '' && admin_is_safe_news_asset_path($formCover)
    ? admin_public_asset_href($formCover)
    : '';
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Адмін — новини</title>
    <style>
      body { font-family: system-ui, sans-serif; max-width: 52rem; margin: 1.5rem auto; padding: 0 1rem; }
      label { display: block; margin-top: 1rem; font-weight: 600; }
      input[type="text"], input[type="date"], textarea { width: 100%; box-sizing: border-box; margin-top: 0.25rem; }
      textarea { min-height: 6rem; font-family: inherit; }
      .hint { font-weight: 400; font-size: 0.875rem; color: #555; }
      .msg { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
      .msg.ok { background: #e8f5e9; color: #1b5e20; }
      .msg.error { background: #ffebee; color: #b71c1c; }
      button, .btn { margin-top: 0; padding: 0.35rem 0.75rem; cursor: pointer; font-size: 0.875rem; }
      .row-check { margin-top: 1rem; font-weight: 600; }
      .row-check input { width: auto; margin-right: 0.5rem; }
      table { width: 100%; border-collapse: collapse; margin: 1.5rem 0 2rem; font-size: 0.9rem; }
      th, td { text-align: left; padding: 0.5rem 0.4rem; border-bottom: 1px solid #ddd; vertical-align: top; }
      th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.02em; color: #444; }
      .actions { white-space: nowrap; }
      .actions form { display: inline; margin: 0; }
      .actions a { margin-right: 0.5rem; }
      .btn-danger { color: #b71c1c; border-color: #e57373; }
      h2 { margin-top: 2rem; font-size: 1.1rem; }
      .form-actions { margin-top: 1.25rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
      .form-actions button { margin-top: 0; padding: 0.5rem 1.25rem; }
      .media-block { margin-top: 0.75rem; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
      .cover-preview { margin-top: 0.75rem; max-width: 280px; }
      .cover-preview img { display: block; max-width: 100%; height: auto; border-radius: 4px; border: 1px solid #ddd; }
      .gallery-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem; }
      .gallery-item { position: relative; width: 96px; }
      .gallery-item img { width: 96px; height: 72px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block; }
      .gallery-item button { margin-top: 0.35rem; width: 100%; font-size: 0.75rem; }
      .gallery-item .path { font-size: 0.65rem; color: #666; word-break: break-all; margin-top: 0.2rem; }
      input[type="file"] { margin-top: 0.35rem; max-width: 100%; }
      .badge { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.45rem; border-radius: 4px; }
      .badge-published { background: #e8f5e9; color: #1b5e20; }
      .badge-draft { background: #fff3e0; color: #e65100; }
    </style>
  </head>
  <body>
    <h1>Новини (JSON)</h1>
    <p class="hint">Єдине джерело: <code>/test/data/news.json</code>. Публічні URL не змінюються (<code>news/article.php?slug=</code>).</p>

<?php if ($message !== ''): ?>
    <p class="msg <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

    <h2>Усі записи</h2>
<?php if ($items === []): ?>
    <p class="hint">Записів немає.</p>
<?php else: ?>
    <table>
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
    $rowPublished = ($row['published'] ?? false) === true;
?>
        <tr>
          <td><?= htmlspecialchars((string) ($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td><code><?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?></code></td>
          <td>
<?php if ($rowPublished): ?>
            <span class="badge badge-published">Published</span>
<?php else: ?>
            <span class="badge badge-draft">Draft</span>
<?php endif; ?>
          </td>
          <td class="actions">
            <a href="index.php?<?= htmlspecialchars(http_build_query(['edit' => $rowSlug]), ENT_QUOTES, 'UTF-8') ?>">Редагувати</a>
            <form method="post" action="save.php">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="slug" value="<?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?>" />
              <button type="submit" class="btn btn-danger">Видалити</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>

    <h2><?= $isEdit ? 'Редагувати новину' : 'Нова новина' ?></h2>
<?php if ($editSlug !== null && !$isEdit): ?>
    <p class="msg error">Запис з slug «<?= htmlspecialchars($editSlug, ENT_QUOTES, 'UTF-8') ?>» не знайдено.</p>
<?php endif; ?>

    <form method="post" action="save.php" enctype="multipart/form-data">
<?php if ($isEdit): ?>
      <input type="hidden" name="old_slug" value="<?= htmlspecialchars($formSlug, ENT_QUOTES, 'UTF-8') ?>" />
<?php endif; ?>
      <label for="title">Заголовок</label>
      <input type="text" id="title" name="title" value="<?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?>" required />

      <label for="slug">Slug <span class="hint">(a-z, 0-9, дефіс; при створенні порожньо = авто)</span></label>
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

      <label for="content">Контент (HTML)</label>
      <textarea id="content" name="content" required style="min-height: 10rem;"><?= htmlspecialchars($formContent, ENT_QUOTES, 'UTF-8') ?></textarea>

      <label for="cover">Обкладинка</label>
      <div class="media-block">
        <label for="cover_file" class="hint">Завантажити файл (перекриває шлях нижче)</label>
        <input type="file" id="cover_file" name="cover_file" accept="image/jpeg,image/png,image/gif,image/webp" />

        <label for="cover" style="margin-top: 0.75rem;">Або шлях вручну</label>
        <input type="text" id="cover" name="cover" value="<?= htmlspecialchars($formCover, ENT_QUOTES, 'UTF-8') ?>" placeholder="img/news/{slug}/cover.jpg" />

        <div class="cover-preview" id="cover-preview"<?= $coverPreviewSrc === '' ? ' hidden' : '' ?>>
          <img id="cover-preview-img" src="<?= htmlspecialchars($coverPreviewSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" />
        </div>
      </div>

      <label for="gallery_files">Галерея</label>
      <div class="media-block">
        <p class="hint">Поточні зображення (зніміть «Прибрати» перед збереженням, щоб видалити з JSON). Додайте нові файли нижче.</p>
        <div class="gallery-grid" id="gallery-keep">
<?php foreach ($formGalleryPaths as $gPath): ?>
<?php
    if (!is_string($gPath) || $gPath === '' || !admin_is_safe_news_asset_path($gPath)) {
        continue;
    }
    $gHref = admin_public_asset_href($gPath);
?>
          <div class="gallery-item" data-keep-item>
            <img src="<?= htmlspecialchars($gHref, ENT_QUOTES, 'UTF-8') ?>" alt="" />
            <input type="hidden" name="gallery_keep[]" value="<?= htmlspecialchars($gPath, ENT_QUOTES, 'UTF-8') ?>" />
            <button type="button" class="btn btn-danger gallery-remove">Прибрати</button>
            <div class="path"><?= htmlspecialchars($gPath, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
<?php endforeach; ?>
        </div>

        <label for="gallery_files" style="margin-top: 1rem;">Додати зображення</label>
        <input type="file" id="gallery_files" name="gallery_files[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple />

        <div class="gallery-grid" id="gallery-new-preview"></div>
        <button type="button" id="gallery-clear-new" class="btn" style="margin-top: 0.5rem; display: none;">Очистити нові файли</button>
      </div>

      <label for="tags">Теги <span class="hint">(через кому)</span></label>
      <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($formTags, ENT_QUOTES, 'UTF-8') ?>" />

      <p class="row-check">
        <label>
          <input type="checkbox" name="published" value="1" <?= $formPublished ? 'checked' : '' ?> />
          Published <span class="hint">(знято = чернетка, не показується на сайті)</span>
        </label>
      </p>

      <div class="form-actions">
        <button type="submit"><?= $isEdit ? 'Зберегти зміни' : 'Додати новину' ?></button>
<?php if ($isEdit): ?>
        <a href="index.php">Скасувати редагування</a>
<?php endif; ?>
      </div>
    </form>
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
        let galleryObjectUrls = [];

        function clearGalleryNewPreviews() {
          galleryObjectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
          galleryObjectUrls = [];
          galleryNewPreview.innerHTML = '';
          galleryFiles.value = '';
          galleryClearNew.style.display = 'none';
        }

        galleryClearNew.addEventListener('click', clearGalleryNewPreviews);

        galleryFiles.addEventListener('change', function () {
          clearGalleryNewPreviews();
          const files = galleryFiles.files;
          if (!files || !files.length) return;
          galleryClearNew.style.display = 'inline-block';
          Array.from(files).forEach(function (file) {
            const url = URL.createObjectURL(file);
            galleryObjectUrls.push(url);
            const wrap = document.createElement('div');
            wrap.className = 'gallery-item';
            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            wrap.appendChild(img);
            const cap = document.createElement('div');
            cap.className = 'path';
            cap.textContent = file.name;
            wrap.appendChild(cap);
            galleryNewPreview.appendChild(wrap);
          });
        });
      })();
    </script>
  </body>
</html>
