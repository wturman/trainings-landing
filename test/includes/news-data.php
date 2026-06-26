<?php

declare(strict_types=1);

/**
 * @return list<array<string, mixed>>
 */
function load_all_news(string $jsonPath): array
{
    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return [];
    }

    $items = array_values(array_filter(
        $data['items'],
        static fn($item): bool => is_array($item)
    ));

    usort(
        $items,
        static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''))
    );

    return $items;
}

function news_item_is_published(array $item): bool
{
    if (!array_key_exists('published', $item)) {
        return false;
    }

    $validated = filter_var($item['published'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    return $validated === true;
}

/**
 * @return list<array<string, mixed>>
 */
function load_published_news(string $jsonPath): array
{
    return array_values(array_filter(
        load_all_news($jsonPath),
        static fn(array $item): bool => news_item_is_published($item)
    ));
}

/**
 * @return array<string, mixed>|null
 */
function load_news_item_by_slug(string $jsonPath, string $slug): ?array
{
    $slug = news_normalize_article_slug(trim($slug)) ?? '';
    if ($slug === '') {
        return null;
    }

    foreach (load_all_news($jsonPath) as $item) {
        if ((string) ($item['slug'] ?? '') === $slug || (string) ($item['id'] ?? '') === $slug) {
            return $item;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function load_published_news_item_by_slug(string $jsonPath, string $slug): ?array
{
    $item = load_news_item_by_slug($jsonPath, $slug);
    if ($item === null || !news_item_is_published($item)) {
        return null;
    }

    return $item;
}

function news_normalize_article_slug(string $slug): ?string
{
    $slug = trim($slug);
    if ($slug === '' || strlen($slug) > 200) {
        return null;
    }
    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
        return null;
    }

    return $slug;
}

/**
 * Ukrainian Cyrillic → Latin (URL slug). Used instead of iconv, which often strips Cyrillic on Windows.
 *
 * @return array<string, string>
 */
function news_ukrainian_latin_map(): array
{
    return [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'h',
        'ґ' => 'g',
        'д' => 'd',
        'е' => 'e',
        'є' => 'ye',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'y',
        'і' => 'i',
        'ї' => 'yi',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'kh',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'shch',
        'ь' => '',
        'ю' => 'yu',
        'я' => 'ya',
        '’' => '',
        '\'' => '',
        'ʼ' => '',
    ];
}

function news_transliterate_for_slug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $map = news_ukrainian_latin_map();
    $length = mb_strlen($text, 'UTF-8');
    $out = '';

    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        if (isset($map[$char])) {
            $out .= $map[$char];
            continue;
        }
        if (preg_match('/[a-z0-9]/', $char) === 1) {
            $out .= $char;
            continue;
        }
        if (preg_match('/\s/u', $char) === 1) {
            $out .= ' ';
            continue;
        }
    }

    return $out;
}

function news_sanitize_slug_candidate(string $slug): string
{
    $slug = trim($slug);
    if ($slug === '') {
        return '';
    }

    $slug = news_transliterate_for_slug($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    $slug = preg_replace('/-+/', '-', $slug) ?? '';

    return $slug;
}

function news_slug_from_title_and_date(string $title, string $date): ?string
{
    $base = news_sanitize_slug_candidate($title);
    if ($base === '') {
        $base = 'news';
    }

    $suffix = '-' . $date;
    $maxBaseLen = 200 - strlen($suffix);
    if ($maxBaseLen < 1) {
        return null;
    }
    if (strlen($base) > $maxBaseLen) {
        $base = substr($base, 0, $maxBaseLen);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'news';
        }
    }

    return news_normalize_article_slug($base . $suffix);
}

/**
 * Create-only: build immutable article slug from title + date. Do not use on edit.
 */
function news_generate_slug_for_create(string $title, string $date): ?string
{
    return news_slug_from_title_and_date($title, $date);
}

/**
 * @param list<array<string, mixed>> $items
 */
function news_article_slug_is_taken(array $items, string $slug, ?string $exceptSlug = null): bool
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $existingSlug = (string) ($item['slug'] ?? '');
        $existingId = (string) ($item['id'] ?? '');

        if ($exceptSlug !== null && ($existingSlug === $exceptSlug || $existingId === $exceptSlug)) {
            continue;
        }

        if ($existingSlug === $slug || $existingId === $slug) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $items
 */
function news_item_matches_slug_key(array $item, string $slugKey): bool
{
    if (!is_array($item)) {
        return false;
    }

    $existingSlug = (string) ($item['slug'] ?? '');
    $existingId = (string) ($item['id'] ?? '');

    return $existingSlug === $slugKey || $existingId === $slugKey;
}

function news_content_looks_like_html(string $content): bool
{
    return preg_match('/<\s*[a-z][a-z0-9]*\b/i', $content) === 1;
}

/**
 * Plain text (blank-line paragraphs) → HTML <p>; existing HTML left unchanged.
 */
function news_format_admin_content(string $content): string
{
    if (news_content_looks_like_html($content)) {
        return $content;
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    $trimmed = trim($normalized);
    if ($trimmed === '') {
        return $content;
    }

    $blocks = preg_split("/\n\s*\n/", $trimmed) ?: [];
    $paragraphs = [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        $lines = explode("\n", $block);
        $escapedLines = array_map(
            static fn(string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $lines
        );
        $inner = implode("<br>\n", $escapedLines);
        $paragraphs[] = '<p>' . $inner . '</p>';
    }

    if ($paragraphs === []) {
        return $content;
    }

    return implode("\n", $paragraphs);
}

/**
 * Readable legacy article path under $newsDirectory, or null.
 */
function news_legacy_article_file(string $newsDirectory, string $slug): ?string
{
    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    $candidate = $newsDirectory . DIRECTORY_SEPARATOR . $slug . '.html';
    if (!is_readable($candidate)) {
        return null;
    }

    $realDir = realpath($newsDirectory);
    $realFile = realpath($candidate);
    if ($realDir === false || $realFile === false) {
        return null;
    }
    if (dirname($realFile) !== $realDir) {
        return null;
    }

    return $realFile;
}

function news_item_engagement_count(array $item, string $field): int
{
    if ($field !== 'views' && $field !== 'likes') {
        return 0;
    }

    if (!array_key_exists($field, $item)) {
        return 0;
    }

    $value = $item[$field];
    if (is_int($value)) {
        return max(0, $value);
    }

    if (is_float($value) || (is_string($value) && is_numeric($value))) {
        return max(0, (int) $value);
    }

    return 0;
}

function news_engagement_cookie_name(string $kind, string $slug): ?string
{
    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    if ($kind !== 'viewed' && $kind !== 'liked') {
        return null;
    }

    return $kind . '_' . $slug;
}

/**
 * @return array{items: list<array<string, mixed>>}|null
 */
function news_load_json_document(string $jsonPath): ?array
{
    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return null;
    }

    return $data;
}

/**
 * @param array{items: list<array<string, mixed>>} $data
 */
function news_save_json_document(string $jsonPath, array $data): bool
{
    $encoded = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($encoded === false) {
        return false;
    }

    $encoded .= "\n";
    $temp = $jsonPath . '.tmp-' . getmypid();
    if (file_put_contents($temp, $encoded, LOCK_EX) === false) {
        return false;
    }

    if (!rename($temp, $jsonPath)) {
        @unlink($temp);

        return false;
    }

    return true;
}

function news_set_engagement_cookie(string $cookieName, int $maxAgeSeconds): void
{
    if ($cookieName === '' || headers_sent()) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    setcookie($cookieName, '1', [
        'expires' => time() + $maxAgeSeconds,
        'path' => '/test/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function news_user_has_engagement_cookie(string $kind, string $slug): bool
{
    $name = news_engagement_cookie_name($kind, $slug);
    if ($name === null) {
        return false;
    }

    return isset($_COOKIE[$name]) && (string) $_COOKIE[$name] !== '';
}

/**
 * @return array{views: int, likes: int}|null
 */
function news_item_engagement_from_array(array $item): array
{
    return [
        'views' => news_item_engagement_count($item, 'views'),
        'likes' => news_item_engagement_count($item, 'likes'),
    ];
}

function news_is_valid_client_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function news_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || $ip === '') {
        return null;
    }

    if (!news_is_valid_client_ip($ip)) {
        return null;
    }

    return $ip;
}

/**
 * @return array{views_ips: array<string, int>, likes_ips: list<string>}
 */
function news_normalize_item_engagement_meta(mixed $raw): array
{
    $viewsIps = [];
    $likesIps = [];

    if (!is_array($raw)) {
        return ['views_ips' => $viewsIps, 'likes_ips' => $likesIps];
    }

    if (isset($raw['views_ips']) && is_array($raw['views_ips'])) {
        foreach ($raw['views_ips'] as $ip => $timestamp) {
            if (!is_string($ip) || !news_is_valid_client_ip($ip)) {
                continue;
            }
            $ts = is_int($timestamp) ? $timestamp : (is_numeric($timestamp) ? (int) $timestamp : 0);
            if ($ts > 0) {
                $viewsIps[$ip] = $ts;
            }
        }
    }

    if (isset($raw['likes_ips']) && is_array($raw['likes_ips'])) {
        foreach ($raw['likes_ips'] as $ip) {
            if (!is_string($ip) || !news_is_valid_client_ip($ip)) {
                continue;
            }
            if (!in_array($ip, $likesIps, true)) {
                $likesIps[] = $ip;
            }
        }
    }

    return ['views_ips' => $viewsIps, 'likes_ips' => $likesIps];
}

/**
 * @return array{views_ips: array<string, int>, likes_ips: list<string>}
 */
function news_item_engagement_meta(array $item): array
{
    return news_normalize_item_engagement_meta($item['engagement'] ?? null);
}

function news_view_ip_blocked_within_window(array $viewsIps, string $ip, int $now, int $windowSeconds = 1800): bool
{
    if (!isset($viewsIps[$ip])) {
        return false;
    }

    $seenAt = (int) $viewsIps[$ip];

    return $seenAt > 0 && ($now - $seenAt) < $windowSeconds;
}

function news_likes_ip_contains(array $likesIps, string $ip): bool
{
    return in_array($ip, $likesIps, true);
}

/**
 * @return array{views: int, likes: int}|null
 */
function news_increment_view_with_ip_tracking(string $jsonPath, string $slug, ?string $ip): ?array
{
    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    $now = time();
    $data = news_load_json_document($jsonPath);
    if ($data === null) {
        return null;
    }

    $updated = null;
    foreach ($data['items'] as $index => $item) {
        if (!is_array($item) || !news_item_matches_slug_key($item, $slug)) {
            continue;
        }

        $meta = news_item_engagement_meta($item);

        if ($ip !== null && news_view_ip_blocked_within_window($meta['views_ips'], $ip, $now)) {
            return news_item_engagement_from_array($item);
        }

        $item['views'] = news_item_engagement_count($item, 'views') + 1;
        $item['likes'] = news_item_engagement_count($item, 'likes');

        if ($ip !== null) {
            $meta['views_ips'][$ip] = $now;
        }

        $item['engagement'] = [
            'views_ips' => (object) $meta['views_ips'],
            'likes_ips' => $meta['likes_ips'],
        ];

        $data['items'][$index] = $item;
        $updated = news_item_engagement_from_array($item);
        break;
    }

    if ($updated === null) {
        return null;
    }

    if (!news_save_json_document($jsonPath, $data)) {
        return null;
    }

    return $updated;
}

/**
 * @return array{views: int, likes: int, blocked: bool}|null
 */
function news_increment_like_with_ip_tracking(string $jsonPath, string $slug, ?string $ip): ?array
{
    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    $data = news_load_json_document($jsonPath);
    if ($data === null) {
        return null;
    }

    $updated = null;
    foreach ($data['items'] as $index => $item) {
        if (!is_array($item) || !news_item_matches_slug_key($item, $slug)) {
            continue;
        }

        $meta = news_item_engagement_meta($item);
        $counts = news_item_engagement_from_array($item);

        if ($ip !== null && news_likes_ip_contains($meta['likes_ips'], $ip)) {
            return [
                'views' => $counts['views'],
                'likes' => $counts['likes'],
                'blocked' => true,
            ];
        }

        $item['views'] = $counts['views'];
        $item['likes'] = $counts['likes'] + 1;

        if ($ip !== null) {
            $meta['likes_ips'][] = $ip;
        }

        $item['engagement'] = [
            'views_ips' => (object) $meta['views_ips'],
            'likes_ips' => $meta['likes_ips'],
        ];

        $data['items'][$index] = $item;
        $updated = news_item_engagement_from_array($item);
        break;
    }

    if ($updated === null) {
        return null;
    }

    if (!news_save_json_document($jsonPath, $data)) {
        return null;
    }

    return [
        'views' => $updated['views'],
        'likes' => $updated['likes'],
        'blocked' => false,
    ];
}

/**
 * @return array{views: int, likes: int}|null
 */
function news_increment_item_engagement(string $jsonPath, string $slug, string $field): ?array
{
    if ($field !== 'views' && $field !== 'likes') {
        return null;
    }

    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    $data = news_load_json_document($jsonPath);
    if ($data === null) {
        return null;
    }

    $updated = null;
    foreach ($data['items'] as $index => $item) {
        if (!is_array($item) || !news_item_matches_slug_key($item, $slug)) {
            continue;
        }

        $item['views'] = news_item_engagement_count($item, 'views');
        $item['likes'] = news_item_engagement_count($item, 'likes');
        $item[$field] = $item[$field] + 1;
        $data['items'][$index] = $item;
        $updated = news_item_engagement_from_array($item);
        break;
    }

    if ($updated === null) {
        return null;
    }

    if (!news_save_json_document($jsonPath, $data)) {
        return null;
    }

    return $updated;
}

/**
 * Record one view per browser per article (30-minute cookie window).
 *
 * @return array{views: int, likes: int}
 */
function news_record_article_view(string $jsonPath, string $slug): array
{
    $item = load_news_item_by_slug($jsonPath, $slug);
    if ($item === null) {
        return ['views' => 0, 'likes' => 0];
    }

    $engagement = news_item_engagement_from_array($item);

    if (news_user_has_engagement_cookie('viewed', $slug)) {
        return $engagement;
    }

    $cookieName = news_engagement_cookie_name('viewed', $slug);
    if ($cookieName === null) {
        return $engagement;
    }

    $clientIp = news_client_ip();
    if ($clientIp !== null) {
        $meta = news_item_engagement_meta($item);
        if (news_view_ip_blocked_within_window($meta['views_ips'], $clientIp, time())) {
            return $engagement;
        }
    }

    $after = news_increment_view_with_ip_tracking($jsonPath, $slug, $clientIp);
    if ($after !== null) {
        news_set_engagement_cookie($cookieName, 30 * 60);
        $engagement = $after;
    }

    return $engagement;
}

/**
 * @return array{views: int, likes: int, already: bool}|null
 */
function news_record_article_like(string $jsonPath, string $slug): ?array
{
    $slug = news_normalize_article_slug($slug);
    if ($slug === null) {
        return null;
    }

    $item = load_published_news_item_by_slug($jsonPath, $slug);
    if ($item === null) {
        return null;
    }

    $engagement = news_item_engagement_from_array($item);

    if (news_user_has_engagement_cookie('liked', $slug)) {
        return [
            'views' => $engagement['views'],
            'likes' => $engagement['likes'],
            'already' => true,
        ];
    }

    $cookieName = news_engagement_cookie_name('liked', $slug);
    if ($cookieName === null) {
        return null;
    }

    $clientIp = news_client_ip();
    if ($clientIp !== null) {
        $meta = news_item_engagement_meta($item);
        if (news_likes_ip_contains($meta['likes_ips'], $clientIp)) {
            return [
                'views' => $engagement['views'],
                'likes' => $engagement['likes'],
                'already' => true,
            ];
        }
    }

    $after = news_increment_like_with_ip_tracking($jsonPath, $slug, $clientIp);
    if ($after === null) {
        return null;
    }

    if ($after['blocked']) {
        return [
            'views' => $after['views'],
            'likes' => $after['likes'],
            'already' => true,
        ];
    }

    news_set_engagement_cookie($cookieName, 365 * 24 * 60 * 60);

    return [
        'views' => $after['views'],
        'likes' => $after['likes'],
        'already' => false,
    ];
}
