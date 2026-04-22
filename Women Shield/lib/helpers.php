<?php

declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    if (!isset($GLOBALS['app_runtime_config']) || !is_array($GLOBALS['app_runtime_config'])) {
        $GLOBALS['app_runtime_config'] = require __DIR__ . '/../config/app.php';
    }

    $config = $GLOBALS['app_runtime_config'];

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function set_app_config(array $config): void
{
    $GLOBALS['app_runtime_config'] = $config;
}

function infer_base_url(): string
{
    $configured = trim((string) app_config('base_url', ''));

    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFilename = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $basePath = str_replace('\\', '/', (string) app_config('base_path'));

    if ($scriptName !== '' && $scriptFilename !== '' && $basePath !== '' && str_starts_with($scriptFilename, $basePath)) {
        $relativeScript = ltrim(substr($scriptFilename, strlen($basePath)), '/');

        if ($relativeScript !== '' && str_ends_with($scriptName, '/' . $relativeScript)) {
            $baseUrl = substr($scriptName, 0, -strlen('/' . $relativeScript));
            return $baseUrl === '' ? '' : rtrim($baseUrl, '/');
        }
    }

    return '';
}

function route_url(string $path = ''): string
{
    $base = infer_base_url();
    $normalized = ltrim($path, '/');

    if ($normalized === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $normalized;
}

function asset_url(string $path): string
{
    return route_url($path);
}

function redirect_to(string $path): never
{
    header('Location: ' . route_url($path));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function current_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = infer_base_url();

    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    return $uri === '' ? '/' : $uri;
}

function is_active_route(string $path): bool
{
    $current = strtok(current_uri(), '?') ?: '/';
    $target = '/' . ltrim($path, '/');

    return $current === $target;
}

function flash_message(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');

    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function format_datetime(?string $value, string $fallback = 'N/A'): string
{
    if (!$value) {
        return $fallback;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('M d, Y h:i A', $timestamp);
}

function datetime_local_value(?string $value = null): string
{
    if (!$value) {
        return date('Y-m-d\TH:i');
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return date('Y-m-d\TH:i');
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function parse_json_array(?string $value): array
{
    if (!$value) {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function badge_class_for_score(int $score): string
{
    return match (true) {
        $score >= 85 => 'badge badge-danger',
        $score >= 65 => 'badge badge-warn',
        $score >= 40 => 'badge badge-caution',
        default => 'badge badge-safe',
    };
}

function badge_class_for_status(string $status): string
{
    return match ($status) {
        'resolved' => 'badge badge-safe',
        'in_review' => 'badge badge-warn',
        'rejected' => 'badge badge-muted',
        default => 'badge badge-danger',
    };
}

function selected_option(string $value, string $current): string
{
    return $value === $current ? 'selected' : '';
}

function checked_option(string $value, string $current): string
{
    return $value === $current ? 'checked' : '';
}

function truncate_text(string $text, int $limit = 140): string
{
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 1) . '…';
}
