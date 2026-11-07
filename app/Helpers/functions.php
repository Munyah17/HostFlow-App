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
        'pending', 'pending_payment', 'provisioning', 'cancellation_pending', 'queued', 'installed', 'running', 'skipped', 'completed_with_errors', 'unknown' => 'pending',
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

/** Inline SVG icon (24x24 stroke) for navigation and UI. */
function icon(string $name): string
{
    static $icons = [
        'dashboard' => '<rect x="3" y="3" width="7.5" height="9" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="5" rx="1.5"/><rect x="13.5" y="12" width="7.5" height="9" rx="1.5"/><rect x="3" y="16" width="7.5" height="5" rx="1.5"/>',
        'customers' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5"/><path d="M15.5 4.8a3.5 3.5 0 0 1 0 6.4"/><path d="M17.5 14.2c2.1.8 3.5 2.7 3.5 5.8"/>',
        'support' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v10a1.5 1.5 0 0 1-1.5 1.5H9l-5 4z"/>',
        'hosting' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7.5 8.5 6 8.5-6"/>',
        'domains' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.4 2.5 3.7 5.6 3.7 9s-1.3 6.5-3.7 9c-2.4-2.5-3.7-5.6-3.7-9S9.6 5.5 12 3z"/>',
        'plans' => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M10 12h5M10 16h5"/>',
        'invoices' => '<path d="M6 3h12v18l-2-1.4-2 1.4-2-1.4-2 1.4-2-1.4L6 21z"/><path d="M9.5 8.5h5M9.5 12h5"/>',
        'payments' => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="M3 10h18"/><path d="M7 14.5h4"/>',
        'expenses' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'reports' => '<path d="M4 20h16"/><path d="M7 20v-6M12 20V6M17 20v-9"/>',
        'automation' => '<path d="M19.5 12a7.5 7.5 0 0 1-12.8 5.3"/><path d="M4.5 12a7.5 7.5 0 0 1 12.8-5.3"/><path d="M18 3.5V8h-4.5M6 20.5V16h4.5"/>',
        'integrations' => '<path d="M9.5 14.5a3.8 3.8 0 0 0 5.4.4l3-3a3.8 3.8 0 0 0-5.4-5.4l-1.4 1.4"/><path d="M14.5 9.5a3.8 3.8 0 0 0-5.4-.4l-3 3a3.8 3.8 0 0 0 5.4 5.4l1.4-1.4"/>',
        'staff' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
        'audit' => '<path d="M8.5 6h12M8.5 12h12M8.5 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5 5l2.1 2.1M16.9 16.9 19 19M19 5l-2.1 2.1M7.1 16.9 5 19"/>',
        'order' => '<path d="M12 5v14M5 12h14"/>',
        'profile' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
    ];
    $path = $icons[$name] ?? $icons['dashboard'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
