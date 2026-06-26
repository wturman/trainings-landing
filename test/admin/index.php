<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
admin_require_auth();

require __DIR__ . '/admin-lib.php';

if (isset($_GET['slug_preview']) && (string) $_GET['slug_preview'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');

    $previewTitle = trim((string) ($_GET['title'] ?? ''));
    $previewDate = trim((string) ($_GET['date'] ?? ''));

    if ($previewTitle === '' || $previewDate === '' || !admin_validate_date($previewDate)) {
        echo json_encode(['ready' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $previewSlug = news_generate_slug_for_create($previewTitle, $previewDate);
    echo json_encode(
        ['ready' => $previewSlug !== null, 'slug' => $previewSlug],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

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
} elseif (isset($_GET['toggled']) && $_GET['toggled'] === '1') {
    $message = 'Статус публікації оновлено.';
    $messageType = 'ok';
} elseif (isset($_GET['ok']) && $_GET['ok'] === '1') {
    $message = 'Новину збережено в news.json.';
    $messageType = 'ok';
} elseif (isset($_GET['migration']) && $_GET['migration'] === 'imported') {
    $found = (int) ($_GET['found'] ?? 0);
    $imported = (int) ($_GET['imported'] ?? 0);
    $skipped = (int) ($_GET['skipped'] ?? 0);
    $message = sprintf(
        'Міграцію legacy HTML завершено: знайдено %d файлів, імпортовано %d, пропущено %d. Журнал: data/migration-log.json.',
        $found,
        $imported,
        $skipped
    );
    $messageType = 'ok';
} elseif (isset($_GET['migration']) && $_GET['migration'] === 'rollback') {
    $backup = isset($_GET['backup']) ? (string) $_GET['backup'] : '';
    $message = $backup !== ''
        ? 'Відкат виконано: news.json відновлено з ' . $backup . '.'
        : 'Відкат виконано: news.json відновлено з останньої резервної копії.';
    $messageType = 'ok';
} elseif (isset($_GET['error']) && is_string($_GET['error']) && $_GET['error'] !== '') {
    $message = $_GET['error'];
    $messageType = 'error';
}

$jsonPath = news_data_json_path();
$items = load_all_news($jsonPath);

$adminListFilter = isset($_GET['filter']) ? (string) $_GET['filter'] : 'all';
$adminListFilterAllowed = ['all', 'current_month', 'prev_month'];
if (!in_array($adminListFilter, $adminListFilterAllowed, true)) {
    $adminListFilter = 'all';
}

/**
 * @param list<array<string, mixed>> $source
 * @return list<array<string, mixed>>
 */
function admin_filter_news_list_items(array $source, string $filter): array
{
    if ($filter === 'all') {
        return $source;
    }

    $now = new DateTimeImmutable('now');
    if ($filter === 'current_month') {
        $rangeStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $rangeEnd = $now->modify('last day of this month')->setTime(23, 59, 59);
    } else {
        $rangeStart = $now->modify('first day of previous month')->setTime(0, 0, 0);
        $rangeEnd = $now->modify('last day of previous month')->setTime(23, 59, 59);
    }

    $filtered = [];
    foreach ($source as $row) {
        if (!is_array($row)) {
            continue;
        }
        $dateRaw = (string) ($row['date'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if ($parsed === false) {
            continue;
        }
        $parsed = $parsed->setTime(0, 0, 0);
        if ($parsed >= $rangeStart && $parsed <= $rangeEnd) {
            $filtered[] = $row;
        }
    }

    return $filtered;
}

function admin_render_news_list_row(array $row, string $listFilter): void
{
    $rowSlug = (string) ($row['slug'] ?? '');
    if ($rowSlug === '') {
        return;
    }
    $rowPublished = news_item_is_published($row);
    $previewUrl = '../news/article.php?' . http_build_query(['slug' => $rowSlug, 'preview' => '1']);
    $editQuery = ['edit' => $rowSlug];
    if ($listFilter !== 'all') {
        $editQuery['filter'] = $listFilter;
    }
    $editHref = 'index.php?' . http_build_query($editQuery);
    ?>
                <tr class="<?= $rowPublished ? 'admin-row--published' : 'admin-row--draft' ?>">
                  <td class="admin-col-date"><?= htmlspecialchars((string) ($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-col-title"><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-col-slug"><code><?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?></code></td>
                  <td class="admin-col-status">
<?php if ($rowPublished): ?>
                    <span class="admin-badge admin-badge-published" title="Visible on the public site">Published</span>
<?php else: ?>
                    <span class="admin-badge admin-badge-draft" title="Draft — preview only">Draft</span>
<?php endif; ?>
                  </td>
                  <td class="admin-col-actions">
                    <div class="admin-action-groups">
                      <div class="admin-action-group admin-action-group--primary">
                        <a class="btn btn-sm btn-edit" href="<?= htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                        <a
                          class="btn btn-sm btn-preview"
                          href="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"
                          target="_blank"
                          rel="noopener noreferrer"
                        >Preview</a>
                      </div>
                      <div class="admin-action-group admin-action-group--publish">
                        <form method="post" action="save.php">
                          <input type="hidden" name="action" value="toggle_published" />
                          <input type="hidden" name="slug" value="<?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?>" />
                          <button type="submit" class="btn btn-sm <?= $rowPublished ? 'btn-unpublish' : 'btn-publish' ?>">
<?= $rowPublished ? 'Unpublish' : 'Publish' ?>
                          </button>
                        </form>
                      </div>
                      <div class="admin-action-group admin-action-group--danger">
                        <form method="post" action="save.php" class="admin-delete-form" data-action="delete">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="slug" value="<?= htmlspecialchars($rowSlug, ENT_QUOTES, 'UTF-8') ?>" />
                          <button type="submit" class="btn btn-sm btn-danger" data-action="delete">Delete</button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
<?php
}

$adminListItems = admin_filter_news_list_items($items, $adminListFilter);
$adminListRecent = array_slice($adminListItems, 0, 5);
$adminListOlder = array_slice($adminListItems, 5);

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

$adminSlugPreviewText = 'URL will be generated automatically';
if ($isEdit && $formSlug !== '') {
    $adminSlugPreviewText = 'URL will be: ' . $formSlug;
} elseif (!$isEdit && trim($formTitle) !== '' && admin_validate_date($formDate)) {
    $generatedPreviewSlug = news_generate_slug_for_create(trim($formTitle), $formDate);
    if ($generatedPreviewSlug !== null) {
        $adminSlugPreviewText = 'URL will be: ' . $generatedPreviewSlug;
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="icon" type="image/png" href="../img/favicon-16x16.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title>Адмін — новини — ГО «Сила інтелекту»</title>
    <script src="../js/theme-boot.js"></script>
    <link rel="stylesheet" href="../css/main.css" />
    <link rel="stylesheet" href="../css/admin.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <header class="site-header">
      <div class="header-inner">
        <a href="../index.php" class="logo-link" aria-label="Перейти на головну сторінку">
          <div class="logo-block">
            <img src="../img/logo.png" alt="Логотип громадської організації Сила інтелекту" class="logo-img" />
            <img src="../img/logo-text.png" alt="Логотип громадської організації Сила інтелекту" class="logo-text-img" />
          </div>
        </a>
        <nav class="nav">
          <ul>
            <li><a href="../index.php">Головна</a></li>
            <li><a href="../news.php">Новини</a></li>
            <li><a href="index.php" aria-current="page">Адмін</a></li>
            <li><a href="change-password.php">Змінити пароль</a></li>
            <li><a href="logout.php">Вийти</a></li>
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
        <p class="news-archive__lead admin-dashboard-intro">Редагування записів у <code>data/news.json</code>. Публічні посилання: <code>news/article.php?slug=</code>.</p>

<?php if ($message !== ''): ?>
        <p class="admin-msg <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

        <div class="admin-section admin-migration-tools">
          <h2 class="section-title">Legacy HTML migration</h2>
          <p class="admin-hint admin-migration-warning">Use only if something went wrong.</p>
          <div class="admin-migration-actions">
            <form method="post" action="import-legacy.php" data-confirm="Import all legacy HTML news into news.json? A backup will be created first.">
              <button type="submit" class="btn btn-secondary">Import legacy HTML news</button>
            </form>
            <form method="post" action="rollback-migration.php" data-confirm="Restore news.json from the latest backup? Current JSON will be replaced.">
              <button type="submit" class="btn btn-danger">Rollback last migration</button>
            </form>
          </div>
          <p class="admin-hint">Backups: <code>data/news.json.bak-{timestamp}</code>. Log: <code>data/migration-log.json</code>. Articles: <code>news/article.php?slug=</code> from JSON only.</p>
        </div>

        <div class="admin-section">
          <h2 class="section-title">Усі записи</h2>
          <p class="admin-hint admin-list-hint">Статус, перегляд і публікація — без відкриття форми редагування.</p>
<?php
    $adminFilterHref = static function (string $filterKey) use ($adminListFilter): string {
        if ($filterKey === 'all') {
            return 'index.php';
        }

        return 'index.php?' . http_build_query(['filter' => $filterKey]);
    };
?>
          <div class="admin-list-filters" role="navigation" aria-label="Фільтр за періодом">
            <span class="admin-list-filters__label">Період:</span>
            <a class="admin-filter-pill<?= $adminListFilter === 'all' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($adminFilterHref('all'), ENT_QUOTES, 'UTF-8') ?>">Всі</a>
            <a class="admin-filter-pill<?= $adminListFilter === 'current_month' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($adminFilterHref('current_month'), ENT_QUOTES, 'UTF-8') ?>">Цей місяць</a>
            <a class="admin-filter-pill<?= $adminListFilter === 'prev_month' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($adminFilterHref('prev_month'), ENT_QUOTES, 'UTF-8') ?>">Попередній місяць</a>
          </div>
<?php if ($items === []): ?>
          <p class="admin-hint">Записів немає.</p>
<?php elseif ($adminListItems === []): ?>
          <p class="admin-hint">За обраним періодом записів немає.</p>
<?php else: ?>
          <p class="admin-list-count">Показано <?= count($adminListRecent) ?> з <?= count($adminListItems) ?> (найновіші спочатку).</p>
          <div class="admin-list-card">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Дата</th>
                  <th>Заголовок</th>
                  <th>Slug</th>
                  <th class="admin-col-status">Статус</th>
                  <th class="admin-col-actions">Дії</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($adminListRecent as $row): ?>
<?php
    if (!is_array($row)) {
        continue;
    }
    admin_render_news_list_row($row, $adminListFilter);
?>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php if ($adminListOlder !== []): ?>
          <details class="admin-list-older">
            <summary>Показати старіші новини</summary>
            <div class="admin-list-card">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Дата</th>
                    <th>Заголовок</th>
                    <th>Slug</th>
                    <th class="admin-col-status">Статус</th>
                    <th class="admin-col-actions">Дії</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($adminListOlder as $row): ?>
<?php
    if (!is_array($row)) {
        continue;
    }
    admin_render_news_list_row($row, $adminListFilter);
?>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
<?php endif; ?>
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

            <label for="date">Дата</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($formDate, ENT_QUOTES, 'UTF-8') ?>" required />
            <p
              id="admin-slug-preview"
              class="admin-slug-preview"
              aria-live="polite"
              data-edit-slug="<?= $isEdit && $formSlug !== '' ? htmlspecialchars($formSlug, ENT_QUOTES, 'UTF-8') : '' ?>"
            ><?= htmlspecialchars($adminSlugPreviewText, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="admin-hint"><?= $isEdit
                ? 'Посилання статті (slug) фіксоване після створення — зміна заголовка або дати URL не змінює.'
                : 'Посилання (slug) буде згенеровано один раз із заголовка та дати при створенні.' ?></p>

            <label for="excerpt">Короткий опис (excerpt)</label>
            <textarea id="excerpt" name="excerpt" required><?= htmlspecialchars($formExcerpt, ENT_QUOTES, 'UTF-8') ?></textarea>

            <label for="content-editor">Контент</label>
            <div class="admin-wysiwyg">
              <div class="admin-wysiwyg__toolbar" data-admin-content-toolbar role="toolbar" aria-label="Форматування тексту">
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="bold" title="Жирний">B</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="italic" title="Курсив"><em>I</em></button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="h2" title="Заголовок 2">H2</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="h3" title="Заголовок 3">H3</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="link" title="Посилання">Link</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="unlink" title="Прибрати посилання">Unlink</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="ul" title="Маркований список">• List</button>
                <button type="button" class="admin-wysiwyg__btn" data-editor-cmd="ol" title="Нумерований список">1. List</button>
              </div>
              <div
                id="content-editor"
                class="admin-wysiwyg__editor news-article__content"
                contenteditable="true"
                role="textbox"
                aria-multiline="true"
                aria-labelledby="content-editor-label"
                data-placeholder="Текст статті…"
              ></div>
              <span id="content-editor-label" class="admin-content-fallback">Контент</span>
              <textarea id="content" name="content" class="admin-content-fallback" aria-hidden="true"></textarea>
            </div>
            <script type="application/json" id="admin-content-initial"><?= json_encode($formContent, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <p class="admin-hint">Виділіть текст і натисніть Link для посилання (відкривається в новій вкладці). Enter — новий абзац. Збереження лишає HTML у форматі статті.</p>

            <label for="cover">Обкладинка</label>
            <div class="admin-media-block">
              <label for="cover_file" class="admin-hint">Завантажити файл (перекриває шлях нижче)</label>
              <input type="file" id="cover_file" name="cover_file" accept="image/jpeg,image/png,image/gif,image/webp" />

              <label for="cover" class="admin-hint" style="display:block;margin-top:var(--space-2);">Або шлях вручну</label>
              <input type="text" id="cover" name="cover" value="<?= htmlspecialchars($formCover, ENT_QUOTES, 'UTF-8') ?>" placeholder="img/news/…/cover.jpg" />

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
        const titleEl = document.getElementById('title');
        const dateEl = document.getElementById('date');
        const previewEl = document.getElementById('admin-slug-preview');
        if (!titleEl || !dateEl || !previewEl) {
          return;
        }

        const placeholder = 'URL will be generated automatically';
        const prefix = 'URL will be: ';
        const editSlug = previewEl.getAttribute('data-edit-slug') || '';
        let previewTimer = null;

        function setPreviewText(text) {
          previewEl.textContent = text;
        }

        function refreshCreateSlugPreview() {
          const title = titleEl.value.trim();
          const date = dateEl.value.trim();
          if (title === '' || date === '') {
            setPreviewText(placeholder);
            return;
          }

          const url = new URL(window.location.href);
          url.search = '';
          url.searchParams.set('slug_preview', '1');
          url.searchParams.set('title', title);
          url.searchParams.set('date', date);

          fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          })
            .then(function (response) {
              return response.json();
            })
            .then(function (data) {
              if (data && data.ready && data.slug) {
                setPreviewText(prefix + data.slug);
              } else {
                setPreviewText(placeholder);
              }
            })
            .catch(function () {
              setPreviewText(placeholder);
            });
        }

        function scheduleCreateSlugPreview() {
          if (previewTimer !== null) {
            clearTimeout(previewTimer);
          }
          previewTimer = setTimeout(refreshCreateSlugPreview, 200);
        }

        if (editSlug !== '') {
          setPreviewText(prefix + editSlug);
          return;
        }

        titleEl.addEventListener('input', scheduleCreateSlugPreview);
        dateEl.addEventListener('input', scheduleCreateSlugPreview);
        dateEl.addEventListener('change', scheduleCreateSlugPreview);
      })();
    </script>
    <script src="js/admin-content-editor.js"></script>
    <script>
      (function () {
        const deleteConfirmMessage = 'Delete this news item? This cannot be undone.';

        document.querySelectorAll('form[data-action="delete"]').forEach(function (form) {
          form.addEventListener('submit', function (e) {
            if (!window.confirm(deleteConfirmMessage)) {
              e.preventDefault();
            }
          });
        });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
          form.addEventListener('submit', function (e) {
            const message = form.getAttribute('data-confirm') || 'Continue?';
            if (!window.confirm(message)) {
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
