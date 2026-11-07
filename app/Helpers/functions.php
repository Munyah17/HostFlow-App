<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Services\Settings;

function db(): Database
{
    return Database::instance();
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require BASE_PATH . '/config/app.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_version(): string
{
    static $version = null;
    return $version ??= trim((string) @file_get_contents(BASE_PATH . '/VERSION')) ?: '0.0.0';
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $url = Request::basePath() . '/' . ltrim($path, '/');
    if ($url !== '/' && str_ends_with($url, '/') && $path !== '/') {
        $url = rtrim($url, '/');
    }
    return $query ? $url . '?' . http_build_query($query) : ($url === '' ? '/' : $url);
}

/** Absolute URL (used in emails, payment return/result URLs). */
function absolute_url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');
    if ($base === '') {
        $scheme = Request::isHttps() ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . Request::basePath();
    }
    $url = $base . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : app_version();
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 302);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $refPort = parse_url($ref, PHP_URL_PORT);
    $refAuthority = parse_url($ref, PHP_URL_HOST) . ($refPort ? ':' . $refPort : '');
    if ($ref !== '' && strcasecmp($refAuthority, $host) === 0) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

/** Redirect back with validation errors and old input. */
function back_with_errors(array $errors, string $fallback = '/'): never
{
    Session::withInput($_POST, $errors);
    Session::flash('error', reset($errors) ?: 'Please correct the errors below.');
    back($fallback);
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

function field_error(string $key): string
{
    $errors = Session::errors();
    return isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function money(mixed $amount, ?string $currency = null): string
{
    $currency ??= (string) setting('currency', 'USD');
    $symbol = $currency === 'USD' ? '$' : $currency . ' ';
    $amount = (float) $amount;
    return ($amount < 0 ? '-' : '') . $symbol . number_format(abs($amount), 2);
}

function date_fmt(?string $date, string $format = 'd M Y'): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function datetime_fmt(?string $date): string
{
    return date_fmt($date, 'd M Y, H:i');
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

/** Render a status badge using the mockup's badge classes. */
function badge(?string $status, ?string $label = null): string
{
    $status = (string) $status;
    $class = match ($status) {
        'active', 'paid', 'completed', 'resolved', 'success', 'enabled', 'in_sync', 'sent' => 'active',
        'suspended', 'overdue', 'failed', 'provisioning_failed', 'terminated', 'disabled', 'mismatch', 'missing', 'error', 'urgent', 'high' => 'suspended',
        'pending', 'pending_payment', 'provisioning', 'draft', 'cancellation_pending', 'queued', 'installed', 'running', 'skipped', 'completed_with_errors', 'unknown' => 'pending',
        'open', 'info', 'normal' => 'open',
        default => 'muted',
    };
    return '<span class="badge ' . $class . '">' . e($label ?? status_label($status)) . '</span>';
}

function cycle_label(?string $cycle): string
{
    return \App\Services\BillingCycle::LABELS[$cycle] ?? ucfirst((string) $cycle);
}

function auth_user(): ?array
{
    return Auth::user();
}

function is_active_path(string $prefix): bool
{
    $path = Request::path();
    return $prefix === $path || ($prefix !== '/' && str_starts_with($path, rtrim($prefix, '/') . '/'));
}

function audit(string $action, ?string $entityType = null, ?int $entityId = null, array|string|null $details = null): void
{
    try {
        db()->insert('audit_logs', [
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_SLASHES) : $details,
            'ip' => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
            'created_at' => now(),
        ]);
    } catch (\Throwable $e) {
        \App\Core\Logger::error('Audit log failed: ' . $e->getMessage());
    }
}

function view(string $template, array $data = [], ?string $layout = 'layouts.app'): string
{
    return \App\Core\View::render($template, $data, $layout);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Build simple pagination data for a count query. */
function paginate(int $total, int $perPage = 25): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

function ticket_categories(): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) setting('ticket_categories', 'General,Billing,Technical,Email,Domain')))));
}

function str_limit(?string $value, int $limit = 60): string
{
    $value = (string) $value;
    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
}
