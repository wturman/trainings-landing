<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
admin_require_auth();

require __DIR__ . '/admin-lib.php';
require __DIR__ . '/migration-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$result = admin_migration_run_legacy_import();

if ($result['ok'] !== true) {
    $message = $result['error'] ?? 'Помилка міграції.';
    admin_redirect_index(['error' => $message]);
}

admin_redirect_index([
    'migration' => 'imported',
    'found' => (string) $result['html_files_found'],
    'imported' => (string) $result['imported'],
    'skipped' => (string) $result['skipped'],
]);
