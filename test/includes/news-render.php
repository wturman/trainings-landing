<?php

declare(strict_types=1);

function news_format_date_uk(string $isoDate): string
{
    $months = [
        1 => 'січня',
        2 => 'лютого',
        3 => 'березня',
        4 => 'квітня',
        5 => 'травня',
        6 => 'червня',
        7 => 'липня',
        8 => 'серпня',
        9 => 'вересня',
        10 => 'жовтня',
        11 => 'листопада',
        12 => 'грудня',
    ];

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $isoDate);
    if ($date === false) {
        return htmlspecialchars($isoDate, ENT_QUOTES, 'UTF-8');
    }

    $month = (int) $date->format('n');
    $label = $months[$month] ?? $date->format('m');

    return htmlspecialchars(
        $date->format('j') . ' ' . $label . ' ' . $date->format('Y'),
        ENT_QUOTES,
        'UTF-8'
    );
}

function news_article_href(array $item): string
{
    $slug = (string) ($item['slug'] ?? '');
    if ($slug === '') {
        return 'news/article.php';
    }

    return 'news/article.php?slug=' . rawurlencode($slug);
}

/**
 * Site-root asset path → URL relative to /test/news/*.php
 */
function news_article_asset_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

/**
 * @param list<string> $tags
 */
function news_format_tags_line(array $tags): string
{
    $parts = [];
    foreach ($tags as $tag) {
        $tag = trim((string) $tag);
        if ($tag !== '') {
            $parts[] = '#' . $tag;
        }
    }

    return htmlspecialchars(implode(' ', $parts), ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, mixed> $item
 */
function render_news_feed_item(array $item): void
{
    $href = htmlspecialchars(news_article_href($item), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $excerpt = htmlspecialchars((string) ($item['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cover = htmlspecialchars((string) ($item['cover'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateIso = htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateUk = news_format_date_uk((string) ($item['date'] ?? ''));
    $alt = $title;
    ?>
          <article class="news-feed__item">
            <a class="news-feed__media" href="<?= $href ?>">
              <div class="news-preview__frame">
                <img
                  src="<?= $cover ?>"
                  alt="<?= $alt ?>"
                  width="560"
                  height="350"
                  loading="lazy"
                />
              </div>
            </a>
            <div class="news-feed__body">
              <h2 class="news-feed__title">
                <a href="<?= $href ?>"><?= $title ?></a>
              </h2>
              <time class="news-feed__date" datetime="<?= $dateIso ?>"><?= $dateUk ?></time>
              <p class="news-feed__excerpt"><?= $excerpt ?></p>
              <a class="news-feed__more" href="<?= $href ?>">Читати далі</a>
            </div>
          </article>
    <?php
}

/**
 * @param array<string, mixed> $item
 */
function render_news_card(array $item): void
{
    $href = htmlspecialchars(news_article_href($item), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cover = htmlspecialchars((string) ($item['cover'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateIso = htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateUk = news_format_date_uk((string) ($item['date'] ?? ''));
    ?>
    <a class="news-card" href="<?= $href ?>">
      <div class="news-card__image">
        <img
          src="<?= $cover ?>"
          alt="<?= $title ?>"
          width="400"
          height="250"
          loading="lazy"
        />
      </div>
      <div class="news-card__body">
        <h3 class="news-card__title"><?= $title ?></h3>
        <time class="news-card__date" datetime="<?= $dateIso ?>"><?= $dateUk ?></time>
      </div>
    </a>
    <?php
}

/**
 * @param array<string, mixed> $item
 */
function render_news_article(array $item): void
{
    $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateIso = htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dateUk = news_format_date_uk((string) ($item['date'] ?? ''));
    $cover = htmlspecialchars(news_article_asset_path((string) ($item['cover'] ?? '')), ENT_QUOTES, 'UTF-8');
    $coverAlt = $title;
    $content = (string) ($item['content'] ?? '');
    $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
    $gallery = is_array($item['gallery'] ?? null) ? $item['gallery'] : [];
    ?>
        <p class="news-article__back"><a href="../news.html">← Усі новини</a></p>

        <time class="news-article__date" datetime="<?= $dateIso ?>"><?= $dateUk ?></time>

        <h1><?= $title ?></h1>

        <div class="news-article__cover">
          <div class="news-preview__frame">
            <img src="<?= $cover ?>" alt="<?= $coverAlt ?>" width="1200" height="630" />
          </div>
        </div>

        <div class="news-article__content">
          <?= $content ?>
          <p class="news-article__tags"><?= news_format_tags_line($tags) ?></p>
        </div>
    <?php if ($gallery !== []): ?>
        <section class="news-article__section news-article__gallery" aria-label="Галерея">
          <h2 class="news-article__section-title">Галерея</h2>
          <div class="news-gallery">
            <ul class="news-gallery__grid" role="list">
    <?php
    $index = 0;
    foreach ($gallery as $imagePath):
        $index++;
        $src = htmlspecialchars(news_article_asset_path((string) $imagePath), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars('Відкрити фото ' . $index, ENT_QUOTES, 'UTF-8');
        ?>
              <li class="news-gallery__item">
                <button type="button" class="news-gallery__thumb" aria-label="<?= $label ?>">
                  <span class="news-gallery__frame">
                    <img
                      src="<?= $src ?>"
                      alt=""
                      width="1200"
                      height="900"
                      loading="lazy"
                    />
                  </span>
                </button>
              </li>
    <?php endforeach; ?>
            </ul>
          </div>
        </section>
    <?php endif; ?>
        <p class="news-article__footer-nav">
          <a href="../news.html">← Повернутися до архіву новин</a>
        </p>
    <?php
}

function render_news_article_not_found(): void
{
    ?>
        <p class="news-article__back"><a href="../news.html">← Усі новини</a></p>
        <h1>Новину не знайдено</h1>
        <div class="news-article__content">
          <p>Запитану новину не знайдено або вона недоступна. Перевірте посилання або поверніться до архіву.</p>
        </div>
        <p class="news-article__footer-nav">
          <a href="../news.html">← Повернутися до архіву новин</a>
        </p>
    <?php
}
