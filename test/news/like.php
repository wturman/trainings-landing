<?php

declare(strict_types=1);

require __DIR__ . '/../includes/news-data.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$slugRaw = isset($_POST['slug']) ? (string) $_POST['slug'] : '';
$slug = news_normalize_article_slug($slugRaw);
if ($slug === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_slug'], JSON_UNESCAPED_UNICODE);
    exit;
}

$jsonPath = __DIR__ . '/../data/news.json';
$result = news_record_article_like($jsonPath, $slug);

if ($result === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(
    [
        'ok' => true,
        'views' => $result['views'],
        'likes' => $result['likes'],
        'already' => $result['already'],
    ],
    JSON_UNESCAPED_UNICODE
);
