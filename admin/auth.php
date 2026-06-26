<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @return array{username: string, password_hash: string}
 */
function admin_auth_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = admin_auth_config_path();
    if (!is_readable($path)) {
        http_response_code(500);
        echo 'Admin authentication is not configured (missing config/admin-auth.php).';
        exit;
    }

    if (!defined('ADMIN_AUTH_LOADING')) {
        define('ADMIN_AUTH_LOADING', true);
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        http_response_code(500);
        echo 'Invalid admin auth configuration.';
        exit;
    }

    $config = [
        'username' => (string) ($loaded['username'] ?? ''),
        'password_hash' => (string) ($loaded['password_hash'] ?? ''),
    ];

    return $config;
}

function admin_is_logged_in(): bool
{
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function admin_safe_return_url(?string $return): string
{
    $return = trim((string) $return);
    if ($return === '') {
        return 'index.php';
    }

    if (preg_match('#^https?://#i', $return) || str_contains($return, '..')) {
        return 'index.php';
    }

    if (str_contains($return, '/admin/')) {
        $tail = substr($return, (int) strrpos($return, '/admin/') + strlen('/admin/'));
        if ($tail !== '' && !str_contains($tail, '..')) {
            return $tail;
        }
    }

    if ($return[0] !== '/') {
        return $return;
    }

    return 'index.php';
}

function admin_require_auth(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $return = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?' . http_build_query(['return' => $return]));
    exit;
}

function admin_attempt_login(string $username, string $password): bool
{
    $config = admin_auth_config();
    if ($username === '' || $password === '') {
        return false;
    }

    if (!hash_equals($config['username'], $username)) {
        return false;
    }

    if (!password_verify($password, $config['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $config['username'];

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function admin_current_username(): string
{
    return (string) ($_SESSION['admin_username'] ?? '');
}

function admin_auth_config_path(): string
{
    return dirname(__DIR__) . '/config/admin-auth.php';
}

function admin_write_auth_config(string $username, string $passwordHash): bool
{
    $path = admin_auth_config_path();
    $content = "<?php\n\n";
    $content .= "declare(strict_types=1);\n\n";
    $content .= "if (!defined('ADMIN_AUTH_LOADING')) {\n";
    $content .= "    http_response_code(403);\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "/**\n";
    $content .= " * Admin credentials. Updated via admin Change Password UI or hash-password CLI.\n";
    $content .= " */\n";
    $content .= "return [\n";
    $content .= "    'username' => " . var_export($username, true) . ",\n";
    $content .= "    'password_hash' => " . var_export($passwordHash, true) . ",\n";
    $content .= "];\n";

    return file_put_contents($path, $content, LOCK_EX) !== false;
}

function admin_change_password(string $oldPassword, string $newPassword): ?string
{
    $config = admin_auth_config();

    if (!password_verify($oldPassword, $config['password_hash'])) {
        return 'Невірний поточний пароль.';
    }

    if (strlen($newPassword) < 6) {
        return 'Новий пароль має містити щонайменше 6 символів.';
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($newHash === false) {
        return 'Не вдалося згенерувати хеш пароля.';
    }

    if (!admin_write_auth_config($config['username'], $newHash)) {
        return 'Не вдалося зберегти новий пароль у конфігурації.';
    }

    return null;
}
