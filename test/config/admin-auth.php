<?php

declare(strict_types=1);

if (!defined('ADMIN_AUTH_LOADING')) {
    http_response_code(403);
    exit;
}

/**
 * Staging admin credentials (outside admin page logic).
 *
 * Default login: username `admin`, password `password` (staging — change immediately)
 * Change password: generate a new hash and replace password_hash below.
 *
 *   php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Or run: php test/admin/hash-password.php "YOUR_NEW_PASSWORD"
 */
return [
    'username' => 'admin',
    'password_hash' => '$2y$12$SFtafbHax6/8LhfBv73z4OzmqbZnYdzTASrgdlvMwCM.JXi3SifnW',
];
