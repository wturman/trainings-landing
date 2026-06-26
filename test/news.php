<?php

declare(strict_types=1);

require __DIR__ . '/includes/news-data.php';
require __DIR__ . '/includes/news-render.php';

$newsItems = load_published_news(news_data_json_path());
?>
<!DOCTYPE html>
<html lang="uk">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/png" href="img/favicon-16x16.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title>Новини — ГО «Сила інтелекту»</title>
    <script src="js/theme-boot.js"></script>
    <link rel="stylesheet" href="css/main.css" />
    <script type="module" src="js/main.js"></script>
    <link
      href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <header class="site-header">
      <div class="header-inner">
        <a href="index.php" class="logo-link" aria-label="Перейти на головну сторінку">
          <div class="logo-block">
            <img
              src="img/logo.png"
              alt="Логотип громадської організації Сила інтелекту"
              class="logo-img"
            />
            <img
              src="img/logo-text.png"
              alt="Логотип громадської організації Сила інтелекту"
              class="logo-text-img"
            />
          </div>
        </a>
        <nav class="nav">
          <ul>
            <li><a href="index.php">Головна</a></li>
            <li><a href="index.php#about">Про нас</a></li>
            <li><a href="index.php#directions">Напрями</a></li>
            <li><a href="index.php#services">Послуги</a></li>
            <li><a href="news.php">Новини</a></li>
          </ul>
        </nav>
        <button class="burger" aria-label="Відкрити меню" aria-expanded="false">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </header>

    <main class="news-archive-page">
      <section class="news-archive" aria-labelledby="news-archive-title">
        <p class="news-archive__back"><a href="index.php">← Повернутися на головну</a></p>
        <h1 id="news-archive-title">Новини</h1>
        <p class="news-archive__lead">Архів подій та оновлень організації «Сила інтелекту».</p>

        <div class="news-feed">
<?php foreach ($newsItems as $item): ?>
<?php render_news_feed_item($item); ?>
<?php endforeach; ?>
        </div>
      </section>
    </main>

    <footer id="contacts" class="footer">
      <h2>Контакти</h2>
      <p>Email: <a href="mailto:sintelektu@gmail.com">sintelektu@gmail.com</a></p>
      <p>Телефон: <a href="tel:+380958896063">+38 0958896063</a></p>
      <div class="social-links">
        <a
          href="https://www.facebook.com/share/18ganyaLtY/"
          class="social-icon facebook"
          aria-label="Facebook"
          target="_blank"
          rel="noopener noreferrer"
        >
          <i class="fab fa-facebook-f"></i>
        </a>
      </div>
    </footer>

    <button class="back-to-top" aria-label="Вгору">&#8593;</button>
  </body>
</html>
