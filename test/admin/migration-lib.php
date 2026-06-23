<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/../includes/migrate-legacy-news.php';

function admin_migration_data_dir(): string
{
    return dirname(admin_json_path());
}

function admin_migration_log_path(): string
{
    return admin_migration_data_dir() . DIRECTORY_SEPARATOR . 'migration-log.json';
}

/**
 * @return list<string>
 */
function admin_migration_list_backup_files(): array
{
    $pattern = admin_json_path() . '.bak-*';
    $files = glob($pattern) ?: [];
    sort($files);

    return $files;
}

function admin_migration_latest_backup_path(): ?string
{
    $files = admin_migration_list_backup_files();
    if ($files === []) {
        return null;
    }

    return $files[array_key_last($files)];
}

/**
 * @return array{runs: list<array<string, mixed>>}
 */
function admin_migration_load_log(): array
{
    $path = admin_migration_log_path();
    if (!is_file($path)) {
        return ['runs' => []];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['runs' => []];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['runs']) || !is_array($data['runs'])) {
        return ['runs' => []];
    }

    return ['runs' => array_values($data['runs'])];
}

/**
 * @param array<string, mixed> $run
 */
function admin_migration_append_log_run(array $run): void
{
    $log = admin_migration_load_log();
    $log['runs'][] = $run;

    $encoded = json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Не вдалося сформувати migration-log.json.');
    }

    $encoded .= "\n";
    if (file_put_contents(admin_migration_log_path(), $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Не вдалося записати migration-log.json.');
    }
}

/**
 * @return array{
 *   ok: bool,
 *   error?: string,
 *   html_files_found: int,
 *   imported: int,
 *   skipped: int,
 *   backup_basename?: string,
 *   log_entries: list<array{slug: string, title: string, status: string, reason?: string}>
 * }
 */
function admin_migration_run_legacy_import(): array
{
    $jsonPath = admin_json_path();
    $newsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'news';
    $ignore = migrate_legacy_news_ignore_basenames();
    $htmlFiles = glob($newsDir . DIRECTORY_SEPARATOR . '*.html') ?: [];
    sort($htmlFiles);

    $found = 0;
    $imported = 0;
    $skipped = 0;
    /** @var list<array{slug: string, title: string, status: string, reason?: string}> */
    $logEntries = [];

    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        return [
            'ok' => false,
            'error' => 'Не вдалося прочитати news.json.',
            'html_files_found' => 0,
            'imported' => 0,
            'skipped' => 0,
            'log_entries' => [],
        ];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        $data = ['items' => []];
    }

    $items = array_values(array_filter($data['items'], static fn($item): bool => is_array($item)));

    $backupBasename = null;
    $timestamp = date('Y-m-d-His');
    $backupPath = $jsonPath . '.bak-' . $timestamp;

    if (!copy($jsonPath, $backupPath)) {
        return [
            'ok' => false,
            'error' => 'Не вдалося створити резервну копію news.json.',
            'html_files_found' => 0,
            'imported' => 0,
            'skipped' => 0,
            'log_entries' => [],
        ];
    }
    $backupBasename = basename($backupPath);

    foreach ($htmlFiles as $filePath) {
        $basename = basename($filePath);
        if (in_array($basename, $ignore, true)) {
            continue;
        }

        $found++;
        $filenameSlug = pathinfo($basename, PATHINFO_FILENAME);
        $slug = news_normalize_article_slug($filenameSlug);
        if ($slug === null) {
            $skipped++;
            $logEntries[] = [
                'slug' => $filenameSlug,
                'title' => '',
                'status' => 'skipped',
                'reason' => 'invalid slug from filename',
            ];
            continue;
        }

        if (migrate_legacy_slug_exists($items, $slug)) {
            $skipped++;
            $titleGuess = '';
            $htmlPeek = file_get_contents($filePath);
            if (is_string($htmlPeek)) {
                $peek = migrate_legacy_parse_article_html($htmlPeek, $slug);
                if ($peek['ok'] === true) {
                    $titleGuess = (string) ($peek['item']['title'] ?? '');
                }
            }
            $logEntries[] = [
                'slug' => $slug,
                'title' => $titleGuess,
                'status' => 'skipped',
                'reason' => 'already in news.json',
            ];
            continue;
        }

        $html = file_get_contents($filePath);
        if ($html === false) {
            $skipped++;
            $logEntries[] = [
                'slug' => $slug,
                'title' => '',
                'status' => 'skipped',
                'reason' => 'cannot read HTML file',
            ];
            continue;
        }

        $parsed = migrate_legacy_parse_article_html($html, $slug);
        if ($parsed['ok'] !== true) {
            $skipped++;
            $logEntries[] = [
                'slug' => $slug,
                'title' => '',
                'status' => 'skipped',
                'reason' => $parsed['reason'],
            ];
            continue;
        }

        $item = $parsed['item'];
        $items[] = $item;
        $imported++;
        $logEntries[] = [
            'slug' => $slug,
            'title' => (string) ($item['title'] ?? ''),
            'status' => 'imported',
        ];
    }

    $data['items'] = migrate_legacy_sort_items_by_date_desc($items);

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return [
            'ok' => false,
            'error' => 'Не вдалося сформувати JSON після міграції.',
            'html_files_found' => $found,
            'imported' => $imported,
            'skipped' => $skipped,
            'backup_basename' => $backupBasename,
            'log_entries' => $logEntries,
        ];
    }

    $encoded .= "\n";
    if (file_put_contents($jsonPath, $encoded, LOCK_EX) === false) {
        return [
            'ok' => false,
            'error' => 'Не вдалося записати news.json.',
            'html_files_found' => $found,
            'imported' => $imported,
            'skipped' => $skipped,
            'backup_basename' => $backupBasename,
            'log_entries' => $logEntries,
        ];
    }

    try {
        admin_migration_append_log_run([
            'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'backup_file' => $backupBasename,
            'html_files_found' => $found,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'items' => $logEntries,
        ]);
    } catch (RuntimeException $exception) {
        return [
            'ok' => false,
            'error' => $exception->getMessage(),
            'html_files_found' => $found,
            'imported' => $imported,
            'skipped' => $skipped,
            'backup_basename' => $backupBasename,
            'log_entries' => $logEntries,
        ];
    }

    return [
        'ok' => true,
        'html_files_found' => $found,
        'imported' => $imported,
        'skipped' => $skipped,
        'backup_basename' => $backupBasename,
        'log_entries' => $logEntries,
    ];
}

/**
 * @return array{ok: bool, error?: string, backup_basename?: string}
 */
function admin_migration_rollback_latest_backup(): array
{
    $backupPath = admin_migration_latest_backup_path();
    if ($backupPath === null || !is_readable($backupPath)) {
        return ['ok' => false, 'error' => 'Резервну копію news.json не знайдено.'];
    }

    $jsonPath = admin_json_path();
    if (!copy($backupPath, $jsonPath)) {
        return ['ok' => false, 'error' => 'Не вдалося відновити news.json з резервної копії.'];
    }

    return ['ok' => true, 'backup_basename' => basename($backupPath)];
}
