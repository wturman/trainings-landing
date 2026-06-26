<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
admin_require_auth();

require __DIR__ . '/admin-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'delete') {
    $deleteSlug = news_normalize_article_slug((string) ($_POST['slug'] ?? ''));
    if ($deleteSlug === null) {
        admin_redirect_error('Некоректний slug для видалення.');
    }

    $data = admin_load_data_or_error();
    $nextItems = [];
    $removed = false;
    foreach ($data['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['slug'] ?? '') === $deleteSlug) {
            $removed = true;
            continue;
        }
        $nextItems[] = $item;
    }

    if (!$removed) {
        admin_redirect_error('Новину не знайдено: ' . $deleteSlug);
    }

    $data['items'] = array_values($nextItems);
    admin_persist_data($data);
    admin_redirect_index(['deleted' => '1']);
}

if ($action === 'toggle_published') {
    $toggleSlug = news_normalize_article_slug((string) ($_POST['slug'] ?? ''));
    if ($toggleSlug === null) {
        admin_redirect_error('Некоректний slug.');
    }

    $data = admin_load_data_or_error();
    $toggled = false;
    foreach ($data['items'] as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!news_item_matches_slug_key($item, $toggleSlug)) {
            continue;
        }
        $data['items'][$index]['published'] = !news_item_is_published($item);
        $toggled = true;
        break;
    }

    if (!$toggled) {
        admin_redirect_error('Новину не знайдено: ' . $toggleSlug);
    }

    admin_persist_data($data);
    admin_redirect_index(['toggled' => '1']);
}

$oldSlugRaw = trim((string) ($_POST['old_slug'] ?? ''));
$oldSlug = $oldSlugRaw !== '' ? news_normalize_article_slug($oldSlugRaw) : null;
$isEdit = $oldSlug !== null;

$title = trim((string) ($_POST['title'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$excerpt = trim((string) ($_POST['excerpt'] ?? ''));
$content = (string) ($_POST['content'] ?? '');
$coverManual = trim((string) ($_POST['cover'] ?? ''));
$tagsRaw = (string) ($_POST['tags'] ?? '');
$published = admin_parse_published_from_post();

if ($title === '') {
    admin_redirect_error('Заголовок обовʼязковий.');
}

if (!admin_validate_date($date)) {
    admin_redirect_error('Дата має бути у форматі YYYY-MM-DD.');
}

if ($excerpt === '') {
    admin_redirect_error('Короткий опис обовʼязковий.');
}

if (trim($content) === '') {
    admin_redirect_error('Контент обовʼязковий.');
}

$content = news_format_admin_content($content);

if (trim($content) === '') {
    admin_redirect_error('Контент обовʼязковий.');
}

$data = admin_load_data_or_error();

if ($isEdit) {
    $existingItem = null;
    foreach ($data['items'] as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if (news_item_matches_slug_key($existing, (string) $oldSlug)) {
            $existingItem = $existing;
            break;
        }
    }

    if ($existingItem === null) {
        admin_redirect_error('Новину для редагування не знайдено: ' . $oldSlug);
    }

    $slug = news_normalize_article_slug((string) ($existingItem['slug'] ?? ''));
    if ($slug === null) {
        admin_redirect_error('Запис має некоректний slug; зверніться до розробника.');
    }
} else {
    $slug = news_generate_slug_for_create($title, $date);

    if ($slug === null) {
        admin_redirect_error('Не вдалося згенерувати slug із заголовка та дати. Перевірте заголовок і дату.');
    }

    if (news_article_slug_is_taken($data['items'], $slug, null)) {
        admin_redirect_error('Новина з таким slug вже існує: ' . $slug);
    }
}

$coverFile = isset($_FILES['cover_file']) && is_array($_FILES['cover_file']) ? $_FILES['cover_file'] : null;
if ($coverFile !== null && ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $coverFile = null;
}

$galleryFiles = isset($_FILES['gallery_files']) && is_array($_FILES['gallery_files']) ? $_FILES['gallery_files'] : null;

$galleryKept = admin_parse_gallery_keep(null, null);
$cover = admin_resolve_cover_path($slug, $coverManual, $coverFile, null, null);
$gallery = admin_process_gallery_uploads($slug, $galleryKept, $galleryFiles);

$item = admin_build_item_from_post(
    $title,
    $slug,
    $date,
    $excerpt,
    $content,
    $cover,
    $gallery,
    $tagsRaw,
    $published
);

if ($isEdit) {
    $replaced = false;
    $nextItems = [];
    foreach ($data['items'] as $existing) {
        if (!is_array($existing)) {
            $nextItems[] = $existing;
            continue;
        }
        if (news_item_matches_slug_key($existing, (string) $oldSlug)) {
            $item['views'] = news_item_engagement_count($existing, 'views');
            $item['likes'] = news_item_engagement_count($existing, 'likes');
            $nextItems[] = $item;
            $replaced = true;
            continue;
        }
        $nextItems[] = $existing;
    }

    if (!$replaced) {
        admin_redirect_error('Новину для редагування не знайдено: ' . $oldSlug);
    }

    $data['items'] = $nextItems;
    admin_persist_data($data);
    admin_redirect_index(['updated' => '1']);
}

$data['items'][] = $item;
admin_persist_data($data);
admin_redirect_index(['created' => '1']);
