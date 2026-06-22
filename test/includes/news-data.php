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

/**
 * @return list<array<string, mixed>>
 */
function load_published_news(string $jsonPath): array
{
    return array_values(array_filter(
        load_all_news($jsonPath),
        static fn(array $item): bool => ($item['published'] ?? false) === true
    ));
}

/**
 * @return array<string, mixed>|null
 */
function load_news_item_by_slug(string $jsonPath, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    foreach (load_all_news($jsonPath) as $item) {
        if ((string) ($item['slug'] ?? '') === $slug) {
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
    if ($item === null || ($item['published'] ?? false) !== true) {
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
