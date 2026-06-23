<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/news-data.php';

function admin_json_path(): string
{
    return __DIR__ . '/../data/news.json';
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function admin_load_data(): array
{
    $raw = file_get_contents(admin_json_path());
    if ($raw === false) {
        return ['items' => []];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['items' => []];
    }

    if (!isset($data['items']) || !is_array($data['items'])) {
        $data['items'] = [];
    }

    return $data;
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function admin_load_data_or_error(): array
{
    $raw = file_get_contents(admin_json_path());
    if ($raw === false) {
        admin_redirect_error('Не вдалося прочитати news.json.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        admin_redirect_error('news.json має некоректний формат.');
    }

    if (!isset($data['items']) || !is_array($data['items'])) {
        $data['items'] = [];
    }

    return $data;
}

function admin_redirect_error(string $message): never
{
    header('Location: index.php?' . http_build_query(['error' => $message]));
    exit;
}

function admin_redirect_index(array $query): never
{
    header('Location: index.php?' . http_build_query($query));
    exit;
}

function admin_persist_data(array $data): void
{
    $encoded = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($encoded === false) {
        admin_redirect_error('Не вдалося сформувати JSON.');
    }

    $written = file_put_contents(admin_json_path(), $encoded . "\n", LOCK_EX);
    if ($written === false) {
        admin_redirect_error('Не вдалося записати news.json.');
    }
}

function admin_suggest_slug_from_title(string $title, string $date): string
{
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $ascii = is_string($converted) ? $converted : $title;
    $base = strtolower($ascii);
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'news';
    }

    return $base . '-' . $date;
}

function admin_parse_gallery(string $raw): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $paths = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $paths[] = $line;
        }
    }

    return $paths;
}

function admin_parse_tags(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }

    $parts = explode(',', $raw);
    $tags = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $tags[] = $part;
        }
    }

    return $tags;
}

function admin_validate_date(string $date): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return false;
    }

    [$y, $m, $d] = array_map('intval', explode('-', $date));

    return checkdate($m, $d, $y);
}

function admin_parse_published_from_post(): bool
{
    if (!isset($_POST['published'])) {
        return false;
    }

    $value = $_POST['published'];
    if (is_array($value)) {
        foreach ($value as $part) {
            if (filter_var($part, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
                return true;
            }
        }

        return false;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
}

/**
 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function admin_normalize_uploaded_files(?array $files): array
{
    if ($files === null || !isset($files['error'])) {
        return [];
    }

    if (!is_array($files['error'])) {
        return [$files];
    }

    $normalized = [];
    foreach ($files['error'] as $index => $error) {
        $normalized[] = [
            'name' => (string) ($files['name'][$index] ?? ''),
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) $error,
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $normalized;
}

/**
 * @param array{items: list<array<string, mixed>>} $data
 */
function admin_slug_exists(array $data, string $slug, ?string $exceptSlug = null): bool
{
    foreach ($data['items'] as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        $existingSlug = (string) ($existing['slug'] ?? '');
        if ($existingSlug === $slug && $existingSlug !== (string) $exceptSlug) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{items: list<array<string, mixed>>} $data
 * @return array<string, mixed>|null
 */
function admin_find_item_by_slug(array $data, string $slug): ?array
{
    foreach ($data['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['slug'] ?? '') === $slug) {
            return $item;
        }
    }

    return null;
}

function admin_gallery_to_text(array $gallery): string
{
    $lines = [];
    foreach ($gallery as $path) {
        if (is_string($path) && $path !== '') {
            $lines[] = $path;
        }
    }

    return implode("\n", $lines);
}

function admin_tags_to_text(array $tags): string
{
    $parts = [];
    foreach ($tags as $tag) {
        if (is_string($tag) && $tag !== '') {
            $parts[] = $tag;
        }
    }

    return implode(', ', $parts);
}

function admin_test_root(): string
{
    return dirname(__DIR__);
}

function admin_news_img_dir_abs(string $slug): string
{
    return admin_test_root() . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR . $slug;
}

function admin_relative_cover_path(string $slug, string $ext): string
{
    return 'img/news/' . $slug . '/cover.' . $ext;
}

function admin_relative_gallery_path(string $slug, string $filename): string
{
    return 'img/news/' . $slug . '/' . $filename;
}

function admin_is_safe_news_asset_path(string $path): bool
{
    if (preg_match('#^img/news/[a-z0-9]+(?:-[a-z0-9]+)*/[a-z0-9][a-z0-9._-]*$#', $path) !== 1) {
        return false;
    }

    $abs = admin_test_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $realRoot = realpath(admin_test_root() . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'news');
    $realFile = realpath($abs);

    if ($realRoot === false) {
        return is_file($abs) || is_dir(dirname($abs));
    }

    if ($realFile === false) {
        return str_starts_with(str_replace('\\', '/', $abs), str_replace('\\', '/', $realRoot . DIRECTORY_SEPARATOR));
    }

    return str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR);
}

function admin_ensure_news_img_dir(string $slug): void
{
    $dir = admin_news_img_dir_abs($slug);
    if (is_dir($dir)) {
        return;
    }

    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        admin_redirect_error('Не вдалося створити папку для зображень.');
    }
}

function admin_rename_news_img_dir(string $oldSlug, string $newSlug): void
{
    if ($oldSlug === $newSlug) {
        return;
    }

    $oldDir = admin_news_img_dir_abs($oldSlug);
    $newDir = admin_news_img_dir_abs($newSlug);

    if (!is_dir($oldDir)) {
        return;
    }

    if (is_dir($newDir)) {
        return;
    }

    rename($oldDir, $newDir);
}

function admin_rewrite_asset_path_for_slug(string $path, string $oldSlug, string $newSlug): string
{
    if ($oldSlug === $newSlug || $path === '') {
        return $path;
    }

    return str_replace('img/news/' . $oldSlug . '/', 'img/news/' . $newSlug . '/', $path);
}

/**
 * @return list<string>
 */
function admin_parse_gallery_keep(?string $oldSlug, ?string $newSlug): array
{
    $raw = $_POST['gallery_keep'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $paths = [];
    foreach ($raw as $path) {
        $path = trim((string) $path);
        if ($path === '') {
            continue;
        }
        if ($oldSlug !== null && $newSlug !== null) {
            $path = admin_rewrite_asset_path_for_slug($path, $oldSlug, $newSlug);
        }
        if (!admin_is_safe_news_asset_path($path)) {
            continue;
        }
        $paths[] = $path;
    }

    return array_values(array_unique($paths));
}

function admin_image_ext_from_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed, true)) {
        $normalized = $ext === 'jpeg' ? 'jpg' : $ext;

        return $normalized;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    return $map[$mime] ?? null;
}

function admin_save_upload_to_dir(array $file, string $destDir, string $basename): ?string
{
    $ext = admin_image_ext_from_upload($file);
    if ($ext === null) {
        return null;
    }

    $safeBase = preg_replace('/[^a-z0-9._-]+/', '-', strtolower($basename)) ?? 'file';
    $safeBase = trim($safeBase, '-');
    if ($safeBase === '') {
        $safeBase = 'file';
    }

    if (!str_contains($safeBase, '.')) {
        $filename = $safeBase . '.' . $ext;
    } else {
        $filename = $safeBase;
    }

    $dest = $destDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        admin_redirect_error('Не вдалося зберегти завантажене зображення.');
    }

    return $filename;
}

function admin_resolve_cover_path(string $slug, string $manualCover, ?array $coverFile, ?string $oldSlug, ?string $newSlug): string
{
    $manualCover = trim($manualCover);
    if ($oldSlug !== null && $newSlug !== null) {
        $manualCover = admin_rewrite_asset_path_for_slug($manualCover, $oldSlug, $newSlug);
    }

    if ($coverFile !== null && admin_image_ext_from_upload($coverFile) !== null) {
        admin_ensure_news_img_dir($slug);
        $ext = admin_image_ext_from_upload($coverFile);
        $destDir = admin_news_img_dir_abs($slug);
        $tmp = (string) $coverFile['tmp_name'];
        $dest = $destDir . DIRECTORY_SEPARATOR . 'cover.' . $ext;
        if (!move_uploaded_file($tmp, $dest)) {
            admin_redirect_error('Не вдалося зберегти обкладинку.');
        }

        return admin_relative_cover_path($slug, $ext);
    }

    return $manualCover;
}

/**
 * @param list<string> $keptPaths
 * @return list<string>
 */
function admin_process_gallery_uploads(string $slug, array $keptPaths, ?array $galleryFiles): array
{
    $gallery = $keptPaths;

    $uploads = admin_normalize_uploaded_files($galleryFiles);
    if ($uploads === []) {
        return $gallery;
    }

    admin_ensure_news_img_dir($slug);
    $destDir = admin_news_img_dir_abs($slug);

    $index = 1;
    foreach ($gallery as $path) {
        if (preg_match('/-(\d+)\.(?:jpe?g|png|gif|webp)$/i', $path, $m)) {
            $index = max($index, (int) $m[1] + 1);
        }
    }

    foreach ($uploads as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            admin_redirect_error('Помилка завантаження файлу галереї.');
        }

        $ext = admin_image_ext_from_upload($file);
        if ($ext === null) {
            admin_redirect_error('Недопустимий тип файлу галереї.');
        }

        $filename = sprintf('%s-%02d.%s', $slug, $index, $ext);
        $index++;
        $dest = $destDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            admin_redirect_error('Не вдалося зберегти зображення галереї.');
        }

        $gallery[] = admin_relative_gallery_path($slug, $filename);
    }

    return array_values($gallery);
}

/**
 * @param list<string> $gallery
 */
function admin_build_item_from_post(
    string $title,
    string $slug,
    string $date,
    string $excerpt,
    string $content,
    string $cover,
    array $gallery,
    string $tagsRaw,
    bool $published
): array {
    return [
        'id' => $slug,
        'slug' => $slug,
        'title' => $title,
        'date' => $date,
        'excerpt' => $excerpt,
        'content' => $content,
        'cover' => $cover,
        'gallery' => $gallery,
        'tags' => admin_parse_tags($tagsRaw),
        'published' => $published,
    ];
}

function admin_public_asset_href(string $path): string
{
    return '../' . ltrim($path, '/');
}
