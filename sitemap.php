<?php

declare(strict_types=1);

require __DIR__ . '/includes/news-data.php';

header('Content-Type: application/xml; charset=UTF-8');

$jsonPath = news_data_json_path();
$items = load_published_news($jsonPath);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    ? 'https'
    : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$siteOrigin = $scheme . '://' . $host;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($items as $index => $item): ?>
<?php
    if (!is_array($item)) {
        continue;
    }
    $slug = (string) ($item['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $loc = $siteOrigin . '/news/article.php?slug=' . rawurlencode($slug);
    $dateRaw = (string) ($item['date'] ?? '');
    $lastmod = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) === 1 ? $dateRaw : '';
    $priority = $index < 3 ? '1.0' : '0.7';
?>
  <url>
    <loc><?= htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
<?php if ($lastmod !== ''): ?>
    <lastmod><?= htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></lastmod>
<?php endif; ?>
    <priority><?= $priority ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
