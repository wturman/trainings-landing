<?php

declare(strict_types=1);

require __DIR__ . '/../includes/news-data.php';
require __DIR__ . '/../includes/news-render.php';

$slugRaw = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$slug = news_normalize_article_slug($slugRaw);
$preview = isset($_GET['preview']) && (string) $_GET['preview'] === '1';
$jsonPath = __DIR__ . '/../data/news.json';

if ($slug !== null) {
    $item = $preview
        ? load_news_item_by_slug($jsonPath, $slug)
        : load_published_news_item_by_slug($jsonPath, $slug);
} else {
    $item = null;
}

if ($item === null && $slug !== null && !$preview) {
    $legacyFile = news_legacy_article_file(__DIR__, $slug);
    if ($legacyFile !== null) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($legacyFile);
        exit;
    }
}

$notFound = $item === null;

if ($notFound) {
    http_response_code(404);
}

$siteBrand = 'Сила інтелекту';
$siteOrigin = 'https://silaintellect.org';

if ($notFound) {
    $metaTitle = 'Новина не знайдена | ' . $siteBrand;
    $metaDescription = 'Запис відсутній';
    $ogTitle = '';
    $ogDescription = '';
    $ogType = 'article';
    $ogImage = '';
    $ogUrl = $slug !== null
        ? $siteOrigin . '/news/article.php?slug=' . rawurlencode($slug)
        : '';
} else {
    $articleTitle = (string) ($item['title'] ?? '');
    $articleExcerpt = (string) ($item['excerpt'] ?? '');
    $articleSlug = (string) ($item['slug'] ?? $slug ?? '');
    $coverPath = ltrim((string) ($item['cover'] ?? ''), '/');

    $metaTitle = $articleTitle !== '' ? $articleTitle . ' | ' . $siteBrand : $siteBrand;
    $metaDescription = $articleExcerpt;
    $ogTitle = $articleTitle;
    $ogDescription = $articleExcerpt;
    $ogType = 'article';
    $ogImage = $coverPath !== '' ? $siteOrigin . '/' . $coverPath : '';
    $ogUrl = $articleSlug !== ''
        ? $siteOrigin . '/news/article.php?slug=' . rawurlencode($articleSlug)
        : '';
}

$pageTitle = htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8');
$metaDescriptionEsc = htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8');
$ogTitleEsc = htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8');
$ogDescriptionEsc = htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8');
$ogTypeEsc = htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8');
$ogImageEsc = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
$ogUrlEsc = htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?= $metaDescriptionEsc ?>" />
<?php if (!$notFound): ?>
    <meta property="og:title" content="<?= $ogTitleEsc ?>" />
    <meta property="og:description" content="<?= $ogDescriptionEsc ?>" />
    <meta property="og:type" content="<?= $ogTypeEsc ?>" />
<?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= $ogImageEsc ?>" />
<?php endif; ?>
<?php if ($ogUrl !== ''): ?>
    <meta property="og:url" content="<?= $ogUrlEsc ?>" />
<?php endif; ?>
<?php endif; ?>
    <link rel="icon" type="image/png" href="../img/favicon-16x16.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="../css/main.css" />
<?php if (!$notFound && is_array($item['gallery'] ?? null) && $item['gallery'] !== []): ?>
    <link rel="stylesheet" href="../css/gallery.css" />
<?php endif; ?>
    <script type="module" src="../js/main.js"></script>
<?php if (!$notFound && is_array($item['gallery'] ?? null) && $item['gallery'] !== []): ?>
    <script type="module" src="../js/gallery.js"></script>
<?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap" rel="stylesheet" />
  </head>
  <body>
    <header class="site-header">
      <div class="header-inner">
        <a href="../index.html" class="logo-link" aria-label="Перейти на головну сторінку">
          <div class="logo-block">
            <img
              src="../img/logo.png"
              alt="Логотип громадської організації Сила інтелекту"
              class="logo-img"
            />
            <img
              src="../img/logo-text.png"
              alt="Логотип громадської організації Сила інтелекту"
              class="logo-text-img"
            />
          </div>
        </a>
        <nav class="nav">
          <ul>
            <li><a href="../index.html">Головна</a></li>
            <li><a href="../index.html#about">Про нас</a></li>
            <li><a href="../index.html#directions">Напрями</a></li>
            <li><a href="../index.html#services">Послуги</a></li>
            <li><a href="../news.html">Новини</a></li>
          </ul>
        </nav>
        <button class="burger" aria-label="Відкрити меню" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </header>

    <main class="news-article-page">
      <article class="news-article">
<?php if ($notFound): ?>
<?php render_news_article_not_found(); ?>
<?php else: ?>
<?php render_news_article($item); ?>
<?php endif; ?>
      </article>
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
  </body>
</html>
