<?php

declare(strict_types=1);

require_once __DIR__ . '/news-data.php';

/**
 * @return list<string>
 */
function migrate_legacy_news_ignore_basenames(): array
{
    return ['article.php'];
}

function migrate_legacy_normalize_asset_path(string $src): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }

    if (str_starts_with($src, '../')) {
        $src = substr($src, 3);
    }

    return ltrim($src, '/');
}

/**
 * @return list<string>
 */
function migrate_legacy_parse_hashtag_tags(string $text): array
{
    if (preg_match_all('/#(\S+)/u', $text, $matches) < 1) {
        return [];
    }

    $tags = [];
    foreach ($matches[1] ?? [] as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }

    return array_values(array_unique($tags));
}

function migrate_legacy_dom_inner_html(DOMNode $node): string
{
    $document = $node->ownerDocument;
    if (!$document instanceof DOMDocument) {
        return '';
    }

    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $document->saveHTML($child);
    }

    return $html;
}

/**
 * @return array{ok: true, item: array<string, mixed>}|array{ok: false, reason: string}
 */
function migrate_legacy_parse_article_html(string $html, string $slug): array
{
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded === false) {
        return ['ok' => false, 'reason' => 'invalid HTML'];
    }

    $xpath = new DOMXPath($dom);

    $titleNodes = $xpath->query('//article[contains(@class,"news-article")]//h1 | //main//h1');
    $title = '';
    if ($titleNodes !== false && $titleNodes->length > 0) {
        $title = trim((string) $titleNodes->item(0)?->textContent);
    }
    if ($title === '') {
        return ['ok' => false, 'reason' => 'missing <h1> title'];
    }

    $timeNodes = $xpath->query('//time[@datetime]');
    $date = '';
    if ($timeNodes !== false && $timeNodes->length > 0) {
        $date = trim((string) $timeNodes->item(0)?->getAttribute('datetime'));
    }
    if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return ['ok' => false, 'reason' => 'missing or invalid <time datetime>'];
    }

    $contentNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' news-article__content ')]");
    if ($contentNodes === false || $contentNodes->length === 0) {
        return ['ok' => false, 'reason' => 'missing .news-article__content'];
    }

    $contentNode = $contentNodes->item(0);
    if (!$contentNode instanceof DOMElement) {
        return ['ok' => false, 'reason' => 'missing .news-article__content'];
    }

    $contentHtml = migrate_legacy_dom_inner_html($contentNode);

    $tagNodes = $xpath->query("//p[contains(concat(' ', normalize-space(@class), ' '), ' news-article__tags ')]");
    $tags = [];
    if ($tagNodes !== false && $tagNodes->length > 0) {
        $tags = migrate_legacy_parse_hashtag_tags((string) $tagNodes->item(0)?->textContent);
    }

    $cover = '';
    $coverNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' news-article__cover ')]//img[@src]");
    if ($coverNodes !== false && $coverNodes->length > 0) {
        $cover = migrate_legacy_normalize_asset_path((string) $coverNodes->item(0)?->getAttribute('src'));
    }

    $gallery = [];
    $galleryImgNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' news-gallery ')]//img[@src]");
    if ($galleryImgNodes !== false) {
        foreach ($galleryImgNodes as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            $path = migrate_legacy_normalize_asset_path($img->getAttribute('src'));
            if ($path !== '') {
                $gallery[] = $path;
            }
        }
    }
    $gallery = array_values(array_unique($gallery));

    $excerpt = migrate_legacy_build_excerpt_from_content($contentHtml);
    if ($excerpt === '') {
        $excerpt = $title;
    }

    $item = [
        'id' => $slug,
        'slug' => $slug,
        'title' => $title,
        'date' => $date,
        'excerpt' => $excerpt,
        'content' => $contentHtml,
        'cover' => $cover,
        'gallery' => $gallery,
        'tags' => $tags,
        'published' => true,
    ];

    return ['ok' => true, 'item' => $item];
}

function migrate_legacy_build_excerpt_from_content(string $contentHtml): string
{
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="wrap">' . $contentHtml . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($dom);
    $paragraphs = $xpath->query('//div[@id="wrap"]//p');
    if ($paragraphs === false) {
        return '';
    }

    foreach ($paragraphs as $paragraph) {
        if (!$paragraph instanceof DOMElement) {
            continue;
        }
        if (str_contains((string) $paragraph->getAttribute('class'), 'news-article__tags')) {
            continue;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $paragraph->textContent) ?? '');
        if ($text !== '') {
            if (mb_strlen($text, 'UTF-8') > 320) {
                return rtrim(mb_substr($text, 0, 317, 'UTF-8')) . '…';
            }

            return $text;
        }
    }

    return '';
}

/**
 * @param list<array<string, mixed>> $items
 */
function migrate_legacy_slug_exists(array $items, string $slug): bool
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['slug'] ?? '') === $slug || (string) ($item['id'] ?? '') === $slug) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function migrate_legacy_sort_items_by_date_desc(array $items): array
{
    usort(
        $items,
        static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''))
    );

    return $items;
}
