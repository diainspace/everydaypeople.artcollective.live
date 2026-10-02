<?php
declare(strict_types=1);

require_once __DIR__ . '/validation.php';

function field(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $default;
    if (!is_string($value)) throw new InvalidArgumentException('Invalid form field: ' . $name);
    return $value;
}

function formDate(string $value, string $timezone, bool $optional = false): ?string
{
    if ($value === '' && $optional) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone($timezone));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i') !== $value) {
        throw new InvalidArgumentException('Enter a valid publication or expiration date.');
    }
    return $date->format(DATE_ATOM);
}

function editDate(?string $value, string $timezone): string
{
    return $value === null ? '' : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i');
}

function storyUploadError(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected image is larger than the 10 MB limit.',
        UPLOAD_ERR_PARTIAL => 'The image upload did not finish. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save the uploaded image.',
        default => 'The image could not be uploaded.',
    };
}

function orientUploadedJpeg(GdImage $image, string $path): GdImage
{
    if (!function_exists('exif_read_data')) return $image;
    $exif = @exif_read_data($path);
    $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    $oriented = match ($orientation) {
        3 => imagerotate($image, 180, 0),
        6 => imagerotate($image, -90, 0),
        8 => imagerotate($image, 90, 0),
        default => $image,
    };
    if (!$oriented instanceof GdImage) throw new RuntimeException('Unable to orient the uploaded image.');
    if ($oriented !== $image) imagedestroy($image);
    return $oriented;
}

function saveStoryUpload(array $upload, string $slug, string $role, int $targetWidth, int $targetHeight): string
{
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error)) throw new InvalidArgumentException('Invalid image upload.');
    if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException(storyUploadError($error));
    $size = $upload['size'] ?? null;
    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_int($size) || $size < 1 || $size > 10 * 1024 * 1024 || !is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Upload a JPEG, PNG, or WebP image no larger than 10 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $dimensions = @getimagesize($temporaryPath);
    if (!is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 12000 || $dimensions[1] > 12000
        || $dimensions[0] * $dimensions[1] > 20_000_000) {
        throw new InvalidArgumentException('The uploaded image has invalid or unsupported dimensions.');
    }
    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($temporaryPath),
        'image/png' => @imagecreatefrompng($temporaryPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($temporaryPath) : false,
        default => false,
    };
    if (!$source instanceof GdImage) throw new InvalidArgumentException('Upload a valid JPEG, PNG, or WebP image.');
    if ($mime === 'image/jpeg') $source = orientUploadedJpeg($source, $temporaryPath);

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $sourceRatio = $sourceWidth / $sourceHeight;
    $targetRatio = $targetWidth / $targetHeight;
    if ($sourceRatio > $targetRatio) {
        $cropHeight = $sourceHeight;
        $cropWidth = (int) round($sourceHeight * $targetRatio);
        $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
        $sourceY = 0;
    } else {
        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $sourceX = 0;
        $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
    }

    $output = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$output instanceof GdImage) {
        imagedestroy($source);
        throw new RuntimeException('Unable to prepare the uploaded image.');
    }
    $background = imagecolorallocate($output, 245, 243, 238);
    imagefill($output, 0, 0, $background);
    if (!imagecopyresampled($output, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight)) {
        imagedestroy($source); imagedestroy($output);
        throw new RuntimeException('Unable to resize the uploaded image.');
    }

    $year = gmdate('Y');
    $relativeDirectory = '/assets/uploads/stories/' . $year;
    $directory = dirname(__DIR__, 2) . $relativeDirectory;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        imagedestroy($source); imagedestroy($output);
        throw new RuntimeException('Unable to create the story image directory.');
    }
    $safeSlug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)) ?: 'story';
    $filename = $safeSlug . '-' . $role . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.webp';
    $destination = $directory . '/' . $filename;
    $saved = function_exists('imagewebp') && imagewebp($output, $destination, 85);
    imagedestroy($source); imagedestroy($output);
    if (!$saved) throw new RuntimeException('Unable to save the optimized story image.');
    chmod($destination, 0644);
    return $relativeDirectory . '/' . $filename;
}

function photoFromForm(string $name, string $slug, string $role, int $targetWidth, int $targetHeight): ?array
{
    $src = trim(field($name . '_src'));
    $upload = $_FILES[$name . '_upload'] ?? null;
    $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $alt = trim(field($name . '_alt'));
    if (($src !== '' || $hasUpload) && $alt === '') {
        throw new InvalidArgumentException('Alternative text is required for each story image.');
    }
    if ($hasUpload) {
        $src = saveStoryUpload($upload, $slug, $role, $targetWidth, $targetHeight);
        $_POST[$name . '_src'] = $src;
    }
    if ($src === '') return null;
    if (!preg_match('~^/(?!/)[^\s<>]+$~D', $src) && !(filter_var($src, FILTER_VALIDATE_URL) && str_starts_with($src, 'https://'))) {
        throw new InvalidArgumentException('Image paths must start with / or use an HTTPS URL.');
    }
    return ['src' => $src, 'alt' => $alt, 'caption' => trim(field($name . '_caption'))];
}

function postFromForm(array $account): array
{
    $id = field('id');
    $slug = trim(field('permaLink'));
    if ($slug === '') $slug = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', field('title'))), '-');
    $post = [
        'id' => $id ?: 'post-' . bin2hex(random_bytes(12)), 'authorId' => $account['admin']['id'],
        'status' => field('status'), 'date' => formDate(field('date'), $account['site']['timeZone']),
        'title' => trim(field('title')), 'description' => trim(field('description')), 'content' => field('content'),
        'coverPhoto' => photoFromForm('coverPhoto', $slug, 'cover', 1600, 1200),
        'blogPhoto' => photoFromForm('blogPhoto', $slug, 'blog', 1400, 1400),
        'permaLink' => $slug, 'expDate' => formDate(field('expDate'), $account['site']['timeZone'], true),
        'catName' => trim(field('catName')), 'catDesc' => '',
        'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
    validateDocument('easy-blog', ['posts' => [$post]]);
    return $post;
}

function categoryFromForm(): array
{
    return [
        'id' => field('category_id'),
        'name' => trim(field('category_name')),
        'description' => trim(field('category_description')),
    ];
}

function websiteContentFromForm(): array
{
    $content = $_POST['content'] ?? null;
    if (!is_array($content)) throw new InvalidArgumentException('Website content is missing.');
    $values = [];
    foreach ($content as $key => $value) {
        if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1 || !is_string($value)) {
            throw new InvalidArgumentException('Invalid website content field.');
        }
        $values[$key] = $value;
    }
    return $values;
}
