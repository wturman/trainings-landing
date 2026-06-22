<?php

declare(strict_types=1);

/**
 * @return list<array<string, mixed>>
 */
function load_published_news(string $jsonPath): array
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
        static fn($item): bool => is_array($item) && ($item['published'] ?? false) === true
    ));

    usort(
        $items,
        static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''))
    );

    return $items;
}

/**
 * @return array<string, mixed>|null
 */
function load_published_news_item_by_slug(string $jsonPath, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    foreach (load_published_news($jsonPath) as $item) {
        if ((string) ($item['slug'] ?? '') === $slug) {
            return $item;
        }
    }

    return null;
}
