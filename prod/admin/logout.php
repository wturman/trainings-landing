<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

admin_logout();

session_write_close();
header('Location: ' . admin_resolve_url('login.php'), true, 303);
exit;
