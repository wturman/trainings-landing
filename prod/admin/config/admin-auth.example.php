<?php

declare(strict_types=1);

/**
 * Copy to site root: config/admin-auth.php (not under admin/).
 *
 * Generate hash (on a machine with PHP CLI):
 * php -r "echo password_hash('YOUR_SECURE_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
 */
return [
    'username' => 'admin',
    'password_hash' => 'PASTE_BCRYPT_HASH_HERE',
];
