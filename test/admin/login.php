<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$return = admin_safe_return_url($_GET['return'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $return = admin_safe_return_url($_POST['return'] ?? $return);

    if (admin_attempt_login($username, $password)) {
        header('Location: ' . $return);
        exit;
    }

    $error = 'Невірний логін або пароль.';
}
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="icon" type="image/png" href="../img/favicon-16x16.png" />
    <title>Вхід — адмін новин</title>
    <link rel="stylesheet" href="../css/main.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
    <style>
      .admin-login-page { min-height: 60vh; display: flex; align-items: center; justify-content: center; padding: var(--space-4) var(--space-2); }
      .admin-login-card { width: 100%; max-width: 24rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); padding: var(--space-4); }
      .admin-login-card h1 { color: var(--color-primary); font-size: 1.35rem; margin-bottom: var(--space-3); display: inline-block; }
      .admin-login-card h1::after { content: ""; display: block; width: 48px; height: 3px; background: var(--color-accent); border-radius: 2px; margin-top: var(--space-1); }
      .admin-login-card label { display: block; margin-top: var(--space-3); font-family: var(--font-heading); font-weight: 600; color: var(--color-primary); }
      .admin-login-card input { width: 100%; box-sizing: border-box; margin-top: var(--space-1); padding: var(--space-2); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-family: var(--font-body); }
      .admin-login-error { margin-top: var(--space-2); padding: var(--space-2); background: #ffebee; color: #b71c1c; border-radius: var(--radius-sm); font-size: 0.9rem; }
      .admin-login-card .btn { width: 100%; margin-top: var(--space-4); text-align: center; }
      .admin-login-back { margin-top: var(--space-3); text-align: center; font-size: 0.9rem; }
      .admin-login-back a { color: var(--color-primary); font-weight: 600; }
    </style>
  </head>
  <body>
    <main class="admin-login-page">
      <div class="admin-login-card">
        <h1>Вхід в адмін</h1>
        <p style="color: var(--color-text-muted); font-size: 0.9rem;">Керування новинами (JSON)</p>
<?php if ($error !== ''): ?>
        <p class="admin-login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
        <form method="post" action="login.php">
          <input type="hidden" name="return" value="<?= htmlspecialchars($return, ENT_QUOTES, 'UTF-8') ?>" />
          <label for="username">Логін</label>
          <input type="text" id="username" name="username" autocomplete="username" required autofocus />

          <label for="password">Пароль</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required />

          <button type="submit" class="btn">Увійти</button>
        </form>
        <p class="admin-login-back"><a href="../news.php">← На сайт</a></p>
      </div>
    </main>
  </body>
</html>
