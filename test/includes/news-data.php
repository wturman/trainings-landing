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
