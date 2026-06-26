<?php

declare(strict_types=1);

function admin_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $cookiePath = dirname($script);
    if ($cookiePath === '/' || $cookiePath === '.' || $cookiePath === '') {
        $cookiePath = '/';
    } else {
        $cookiePath = rtrim($cookiePath, '/') . '/';
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);

    session_start();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    admin_start_session();
}

function admin_current_return_path(): string
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if ($script === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $script)) {
        $script = 'index.php';
    }

    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($query === '') {
        return $script;
    }

    return $script . '?' . $query;
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
        $tail = ltrim($tail, '/');
        if ($tail !== '' && !str_contains($tail, '..') && admin_is_safe_admin_relative_path($tail)) {
            return $tail;
        }
    }

    if ($return[0] === '/') {
        return 'index.php';
    }

    if (!admin_is_safe_admin_relative_path($return)) {
        return 'index.php';
    }

    return $return;
}

function admin_is_safe_admin_relative_path(string $path): bool
{
    $pathOnly = explode('?', $path, 2)[0];
    if ($pathOnly === '' || str_contains($pathOnly, '\\')) {
        return false;
    }

    if (preg_match('#^(index|login|logout|save|change-password|news|import-legacy|rollback-migration)\\.php$#', $pathOnly)) {
        return true;
    }

    if (preg_match('#^news/article\\.php$#', $pathOnly)) {
        return true;
    }

    return false;
}

/**
 * Build absolute path URL for redirects (same host), from admin-relative path.
 */
function admin_resolve_url(string $relative): string
{
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '') {
        $relative = 'index.php';
    }

    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $base = dirname($script);
    if ($base === '/' || $base === '.' || $base === '') {
        return '/' . ltrim($relative, '/');
    }

    return rtrim($base, '/') . '/' . ltrim($relative, '/');
}

function admin_redirect_after_login(string $return): void
{
    $path = admin_safe_return_url($return);
    session_write_close();
    header('Location: ' . admin_resolve_url($path), true, 303);
    exit;
}

function admin_redirect_to_login(string $returnPath): void
{
    $safeReturn = admin_safe_return_url($returnPath);
    $query = http_build_query(['return' => $safeReturn]);
    session_write_close();
    header('Location: ' . admin_resolve_url('login.php?' . $query), true, 303);
    exit;
}

function admin_require_auth(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $return = admin_current_return_path();
    admin_redirect_to_login($return);
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
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/logout.php'));
        $path = dirname($script);
        if ($path !== '/' && $path !== '.' && $path !== '') {
            $path = rtrim($path, '/') . '/';
        } else {
            $path = $params['path'] ?: '/';
        }

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $path,
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
