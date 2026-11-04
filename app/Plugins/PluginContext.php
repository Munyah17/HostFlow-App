<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Core\Auth;
use App\Core\Navigation;
use App\Core\View;
use App\Services\Hosting\ProviderRegistry;
use App\Services\Notifications\NotificationChannelInterface;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\GatewayRegistry;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Settings;

/**
 * The controlled API through which a plugin extends HostFlow.
 */
final class PluginContext
{
    public array $routes = [];
    public array $tasks = [];
    public array $settingsFields = [];

    public function __construct(public readonly string $id, public readonly string $path, public readonly array $manifest)
    {
        if (is_dir($path . '/views')) {
            View::addNamespace('plugin-' . $id, $path . '/views');
        }
    }

    /**
     * Register a route. $roles must list who may access it: super_admin, admin, client.
     * Client handlers must check customer ownership (use $ctx->customerId()).
     */
    public function route(string $method, string $path, callable $handler, array $roles): void
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new \InvalidArgumentException("Plugin {$this->id}: unsupported HTTP method {$method}.");
        }
        $roles = array_values(array_intersect($roles, array_keys(Auth::ROLE_LABELS)));
        if (!$roles) {
            throw new \InvalidArgumentException("Plugin {$this->id}: route {$path} must declare at least one valid role.");
        }
        $this->routes[] = ['method' => $method, 'path' => '/' . trim($path, '/'), 'handler' => $handler, 'roles' => $roles];
    }

    public function navigation(array $item): void
    {
        Navigation::register($this->id, $item);
    }

    /** Register a scheduled task executed by the HostFlow cron runner. */
    public function schedule(string $name, callable $callback, int $intervalMinutes = 5): void
    {
        if (!preg_match('/^[a-z0-9_-]{2,60}$/', $name)) {
            throw new \InvalidArgumentException("Plugin {$this->id}: invalid task name {$name}.");
        }
        $this->tasks[$name] = ['name' => $name, 'callback' => $callback, 'interval' => max(1, $intervalMinutes)];
    }

    /**
     * Declare configuration fields for the plugin's settings page.
     * Field: ['key' => 'api_key', 'label' => 'API key', 'type' => 'text|password|textarea|checkbox|select', 'options' => [...], 'help' => '']
     * Fields of type "password" are stored encrypted.
     */
    public function settings(array $fields): void
    {
        foreach ($fields as $f) {
            if (empty($f['key']) || !preg_match('/^[a-z0-9_]{1,60}$/', $f['key'])) {
                throw new \InvalidArgumentException("Plugin {$this->id}: invalid settings field key.");
            }
        }
        $this->settingsFields = $fields;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $secret = Settings::get('secret.plugin.' . $this->id . '.' . $key);
        if ($secret !== null && $secret !== '') {
            return $secret;
        }
        return Settings::get('plugin.' . $this->id . '.' . $key, $default);
    }

    public function hostingProvider(string $type, string $label, callable $factory, int $defaultPort = 443): void
    {
        ProviderRegistry::register($type, $label, $factory, $defaultPort);
    }

    public function paymentGateway(PaymentGatewayInterface $gateway): void
    {
        GatewayRegistry::register($gateway);
    }

    public function notificationChannel(NotificationChannelInterface $channel): void
    {
        NotificationService::registerChannel($channel);
    }

    /** Render a plugin view ("views/{name}.php") inside the HostFlow layout. */
    public function render(string $template, array $data = [], bool $withLayout = true): string
    {
        return View::render('plugin-' . $this->id . '::' . $template, $data, $withLayout ? 'layouts.app' : null);
    }

    public function asset(string $file): string
    {
        return url('/plugin-assets/' . $this->id . '/' . ltrim($file, '/'));
    }

    public function customerId(): ?int
    {
        return Auth::customerId();
    }

    public function log(string $action, string $status, string $message): void
    {
        \App\Services\AutomationLog::record('plugin:' . $this->id, $action, $status, $message);
    }
}
