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
        header('Location: ' . admin_safe_return_url($return));
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
    <script src="../js/theme-boot.js"></script>
    <link rel="stylesheet" href="../css/main.css" />
    <link rel="stylesheet" href="../css/admin.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
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
