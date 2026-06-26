<?php

declare(strict_types=1);

/**
 * Likes feature removed — endpoint kept for old bookmarks only.
 */
require __DIR__ . '/../includes/news-data.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);
echo json_encode(['ok' => false, 'error' => 'likes_disabled'], JSON_UNESCAPED_UNICODE);
