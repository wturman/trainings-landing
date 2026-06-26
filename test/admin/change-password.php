<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
admin_require_auth();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = (string) ($_POST['old_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if ($oldPassword === '' || $newPassword === '') {
        $error = 'Заповніть усі поля.';
    } else {
        $changeError = admin_change_password($oldPassword, $newPassword);
        if ($changeError === null) {
            $success = true;
        } else {
            $error = $changeError;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="icon" type="image/png" href="../img/favicon-16x16.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title>Зміна пароля — адмін</title>
    <script src="../js/theme-boot.js"></script>
    <link rel="stylesheet" href="../css/main.css" />
    <link rel="stylesheet" href="../css/admin.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <header class="site-header">
      <div class="header-inner">
        <a href="../index.php" class="logo-link" aria-label="Перейти на головну сторінку">
          <div class="logo-block">
            <img src="../img/logo.png" alt="Логотип громадської організації Сила інтелекту" class="logo-img" />
            <img src="../img/logo-text.png" alt="Логотип громадської організації Сила інтелекту" class="logo-text-img" />
          </div>
        </a>
        <nav class="nav">
          <ul>
            <li><a href="../index.php">Головна</a></li>
            <li><a href="../news.php">Новини</a></li>
            <li><a href="index.php">Адмін</a></li>
            <li><a href="change-password.php" aria-current="page">Пароль</a></li>
            <li><a href="logout.php">Вийти</a></li>
          </ul>
        </nav>
        <button class="burger" aria-label="Відкрити меню" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </header>

    <main class="news-archive-page admin-page admin-page--compact">
      <section class="news-archive admin-panel" aria-labelledby="change-password-title">
        <p class="news-archive__back"><a href="index.php">← До керування новинами</a></p>
        <h1 id="change-password-title">Зміна пароля</h1>
        <p class="news-archive__lead">Обліковий запис: <strong><?= htmlspecialchars(admin_current_username(), ENT_QUOTES, 'UTF-8') ?></strong></p>

<?php if ($success): ?>
        <p class="admin-msg ok" role="status">Пароль успішно змінено.</p>
<?php endif; ?>
<?php if ($error !== ''): ?>
        <p class="admin-msg error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

        <form class="admin-form" method="post" action="change-password.php">
          <label for="old_password">Поточний пароль</label>
          <input type="password" id="old_password" name="old_password" autocomplete="current-password" required />

          <label for="new_password">Новий пароль</label>
          <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="6" required />
          <p class="admin-hint">Мінімум 6 символів.</p>

          <div class="admin-form-actions">
            <button type="submit" class="btn">Зберегти новий пароль</button>
            <a href="index.php" class="btn-cancel">Скасувати</a>
          </div>
        </form>
      </section>
    </main>

    <footer id="contacts" class="footer">
      <h2>Контакти</h2>
      <p>Email: <a href="mailto:sintelektu@gmail.com">sintelektu@gmail.com</a></p>
      <p>Телефон: <a href="tel:+380958896063">+38 0958896063</a></p>
    </footer>

    <script type="module" src="../js/main.js"></script>
  </body>
</html>
