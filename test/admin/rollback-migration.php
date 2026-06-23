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

$result = admin_migration_rollback_latest_backup();

if ($result['ok'] !== true) {
    admin_redirect_index(['error' => $result['error'] ?? 'Помилка відкату міграції.']);
}

$backup = (string) ($result['backup_basename'] ?? '');
admin_redirect_index([
    'migration' => 'rollback',
    'backup' => $backup,
]);
