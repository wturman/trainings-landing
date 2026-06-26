<?php

declare(strict_types=1);

/**
 * One-time: set id/slug from title+date via news_generate_slug_for_create().
 * CLI: php test/normalize-news-json.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require __DIR__ . '/includes/news-data.php';

$jsonPath = __DIR__ . '/data/news.json';
$raw = file_get_contents($jsonPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read news.json\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$changes = [];
$seen = [];

foreach ($data['items'] as $index => $item) {
    if (!is_array($item)) {
        continue;
    }
    $title = trim((string) ($item['title'] ?? ''));
    $date = trim((string) ($item['date'] ?? ''));
    $oldSlug = (string) ($item['slug'] ?? '');
    $oldId = (string) ($item['id'] ?? '');

    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        fwrite(STDERR, "Item #{$index}: missing title or date\n");
        exit(1);
    }

    $newSlug = news_generate_slug_for_create($title, $date);
    if ($newSlug === null) {
        fwrite(STDERR, "Item #{$index}: could not generate slug for title/date\n");
        exit(1);
    }

    if (isset($seen[$newSlug])) {
        fwrite(STDERR, "Duplicate slug after normalization: {$newSlug}\n");
        exit(1);
    }
    $seen[$newSlug] = true;

    if ($oldSlug !== $newSlug || $oldId !== $newSlug) {
        $changes[] = ['old' => $oldSlug, 'new' => $newSlug, 'title' => $title];
    }

    $data['items'][$index]['id'] = $newSlug;
    $data['items'][$index]['slug'] = $newSlug;
}

$backupPath = $jsonPath . '.bak-' . date('Y-m-d-His');
if (!copy($jsonPath, $backupPath)) {
    fwrite(STDERR, "Backup failed\n");
    exit(1);
}

$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "Encode failed\n");
    exit(1);
}
$encoded .= "\n";

$temp = $jsonPath . '.tmp-' . getmypid();
if (file_put_contents($temp, $encoded, LOCK_EX) === false) {
    fwrite(STDERR, "Write failed\n");
    exit(1);
}
if (!rename($temp, $jsonPath)) {
    @unlink($temp);
    fwrite(STDERR, "Rename failed\n");
    exit(1);
}

echo "Backup: {$backupPath}\n";
echo 'Items updated: ' . count($data['items']) . "\n";
echo 'Slug/id changes: ' . count($changes) . "\n";
foreach ($changes as $row) {
    echo "  {$row['old']} -> {$row['new']}\n";
}

exit(0);
