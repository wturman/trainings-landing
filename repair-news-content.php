<?php

declare(strict_types=1);

/**
 * One-time: restore content from legacy HTML (selected slugs) + normalize all item content.
 * CLI: php repair-news-content.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require __DIR__ . '/includes/migrate-legacy-news.php';

$jsonPath = news_data_json_path();
$newsDir = __DIR__ . '/news';

$restoreFromHtml = [
    'nove-obladnannia-dlia-tuteshnikh-2026-06-20',
    'film-pro-chornobyl-2026-04-26',
];

/**
 * @return string|null
 */
function repair_extract_legacy_content_html(string $filePath): ?string
{
    $html = file_get_contents($filePath);
    if ($html === false) {
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded === false) {
        return null;
    }

    $xpath = new DOMXPath($dom);
    $contentNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' news-article__content ')]");
    if ($contentNodes === false || $contentNodes->length === 0) {
        return null;
    }

    $contentNode = $contentNodes->item(0);
    if (!$contentNode instanceof DOMElement) {
        return null;
    }

    return migrate_legacy_dom_inner_html($contentNode);
}

function repair_collapse_inline_whitespace(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function repair_normalize_element(DOMDocument $dom, DOMElement $element): string
{
    $tag = strtolower($element->tagName);
    $allowed = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote'];
    if (!in_array($tag, $allowed, true)) {
        return repair_normalize_content_html(migrate_legacy_dom_inner_html($element));
    }

    $inner = '';
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMText) {
            $inner .= repair_collapse_inline_whitespace($child->textContent);
            continue;
        }
        if ($child instanceof DOMElement) {
            $inner .= $dom->saveHTML($child);
        }
    }

    $class = trim($element->getAttribute('class'));
    if ($class !== '') {
        return '<' . $tag . ' class="' . htmlspecialchars($class, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' . $inner . '</' . $tag . '>';
    }

    return '<' . $tag . '>' . $inner . '</' . $tag . '>';
}

function repair_normalize_paragraph_outer(DOMDocument $dom, DOMElement $element): string
{
    $tag = strtolower($element->tagName);
    if ($tag !== 'p') {
        return repair_normalize_element($dom, $element);
    }

    $class = trim($element->getAttribute('class'));
    $inner = '';
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMText) {
            $inner .= repair_collapse_inline_whitespace($child->textContent);
            continue;
        }
        if ($child instanceof DOMElement) {
            $fragment = $dom->saveHTML($child);
            $fragment = preg_replace('/\s+/u', ' ', $fragment) ?? '';
            $fragment = preg_replace('/>\s+</', '><', $fragment) ?? '';
            $inner .= trim($fragment);
        }
    }
    $inner = trim($inner);

    if ($class !== '') {
        return '<p class="' . htmlspecialchars($class, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' . $inner . '</p>';
    }

    return '<p>' . $inner . '</p>';
}

function repair_normalize_content_html(string $html): string
{
    $html = preg_replace('/<!--.*?-->/su', '', $html) ?? '';
    $html = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $html);
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="repair-root">' . $html . '</div>',
        LIBXML_NOWARNING | LIBXML_NOERROR
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//div[@id="repair-root"]')->item(0);
    if (!$root instanceof DOMElement) {
        return repair_collapse_inline_whitespace(strip_tags($html));
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        if ($child instanceof DOMText) {
            $text = repair_collapse_inline_whitespace($child->textContent);
            if ($text !== '') {
                $out .= '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
            }
            continue;
        }
        if ($child instanceof DOMElement) {
            $out .= repair_normalize_paragraph_outer($dom, $child);
        }
    }

    return $out;
}

$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read news.json\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    fwrite(STDERR, "Invalid news.json\n");
    exit(1);
}

$restored = [];
foreach ($restoreFromHtml as $slug) {
    $file = $newsDir . DIRECTORY_SEPARATOR . $slug . '.html';
    if (!is_file($file)) {
        fwrite(STDERR, "Missing HTML for {$slug}\n");
        exit(1);
    }
    $extracted = repair_extract_legacy_content_html($file);
    if ($extracted === null || trim($extracted) === '') {
        fwrite(STDERR, "Failed to extract content for {$slug}\n");
        exit(1);
    }
    $restored[$slug] = repair_normalize_content_html($extracted);
}

foreach ($data['items'] as $index => $item) {
    if (!is_array($item)) {
        continue;
    }
    $slug = (string) ($item['slug'] ?? '');
    if (isset($restored[$slug])) {
        $data['items'][$index]['content'] = $restored[$slug];
        continue;
    }
    $content = (string) ($item['content'] ?? '');
    $data['items'][$index]['content'] = repair_normalize_content_html($content);
}

$backupPath = $jsonPath . '.bak-' . date('Y-m-d-His');
if (!copy($jsonPath, $backupPath)) {
    fwrite(STDERR, "Backup failed\n");
    exit(1);
}

$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}

$encoded .= "\n";
$tempPath = $jsonPath . '.tmp-' . getmypid();
if (file_put_contents($tempPath, $encoded, LOCK_EX) === false) {
    fwrite(STDERR, "Temp write failed\n");
    exit(1);
}

if (!rename($tempPath, $jsonPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "Atomic rename failed\n");
    exit(1);
}

echo "Backup: {$backupPath}\n";
echo "Restored from HTML: " . implode(', ', array_keys($restored)) . "\n";
echo "Normalized content for all " . count($data['items']) . " items.\n";

exit(0);
