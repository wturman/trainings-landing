<?php

declare(strict_types=1);

/**
 * One-time CLI: import legacy news/*.html into data/news.json.
 * Does not modify or delete HTML files.
 *
 * Usage: php migrate-legacy-news.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This migration runs from the command line only.\n");
    exit(1);
}

require __DIR__ . '/admin/migration-lib.php';

$dryRun = in_array('--dry-run', $argv, true);

if ($dryRun) {
    $jsonPath = news_data_json_path();
    $newsDir = __DIR__ . DIRECTORY_SEPARATOR . 'news';
    $ignore = migrate_legacy_news_ignore_basenames();
    $htmlFiles = glob($newsDir . DIRECTORY_SEPARATOR . '*.html') ?: [];
    sort($htmlFiles);

    $found = 0;
    $imported = 0;
    $skipped = 0;
    $items = load_all_news($jsonPath);

    foreach ($htmlFiles as $filePath) {
        $basename = basename($filePath);
        if (in_array($basename, $ignore, true)) {
            continue;
        }
        $found++;
        $slug = news_normalize_article_slug(pathinfo($basename, PATHINFO_FILENAME));
        if ($slug === null || migrate_legacy_slug_exists($items, $slug)) {
            $skipped++;
            continue;
        }
        $html = file_get_contents($filePath);
        if ($html === false || migrate_legacy_parse_article_html($html, $slug)['ok'] !== true) {
            $skipped++;
            continue;
        }
        $imported++;
    }

    echo "Dry run — news.json not modified.\n\n";
    echo "=== Legacy news migration summary ===\n";
    echo "HTML article files found: {$found}\n";
    echo "Would import: {$imported}\n";
    echo "Would skip: {$skipped}\n\n";
    exit(0);
}

$result = admin_migration_run_legacy_import();

if ($result['ok'] !== true) {
    fwrite(STDERR, ($result['error'] ?? 'Migration failed.') . "\n");
    exit(1);
}

echo 'Backup written: ' . ($result['backup_basename'] ?? '') . "\n";
echo "Migration log updated: migration-log.json\n\n";
echo "=== Legacy news migration summary ===\n";
echo "HTML article files found: {$result['html_files_found']}\n";
echo "Successfully imported: {$result['imported']}\n";
echo "Skipped: {$result['skipped']}\n\n";

foreach ($result['log_entries'] as $row) {
    if (($row['status'] ?? '') === 'skipped') {
        echo "  - {$row['slug']}: {$row['reason']}\n";
    }
}

exit(0);
