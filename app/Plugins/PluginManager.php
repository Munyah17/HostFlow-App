<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Router;
use App\Services\Settings;

/**
 * Discovers, installs, enables, updates and loads plugins.
 * Plugins live in plugins/ (bundled with the code) or storage/plugins/ (uploaded,
 * persistent across deployments). Only enabled plugins are loaded.
 */
final class PluginManager
{
    private static ?self $instance = null;
    private bool $booted = false;
    /** @var array<string, array{plugin: BasePlugin, ctx: PluginContext}> */
    private array $loaded = [];
    private array $loadErrors = [];

    public const ID_PATTERN = '/^[a-z0-9][a-z0-9-]{1,63}$/';
    public const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    // ------------------------------------------------------------ discovery

    /** Validate a decoded plugin.json. Returns list of errors. */
    public static function validateManifest(mixed $m): array
    {
        if (!is_array($m)) {
            return ['plugin.json is not valid JSON.'];
        }
        $errors = [];
        foreach (['id', 'name', 'version', 'description', 'author', 'minimum_hostflow_version'] as $field) {
            if (!isset($m[$field]) || !is_string($m[$field]) || trim($m[$field]) === '') {
                $errors[] = "Manifest field '{$field}' is required.";
            }
        }
        if (isset($m['id']) && is_string($m['id']) && !preg_match(self::ID_PATTERN, $m['id'])) {
            $errors[] = 'Plugin id must be 2-64 lowercase letters, digits or dashes.';
        }
        foreach (['version', 'minimum_hostflow_version'] as $f) {
            if (isset($m[$f]) && is_string($m[$f]) && !preg_match(self::VERSION_PATTERN, $m[$f])) {
                $errors[] = "Manifest field '{$f}' must be a version like 1.0.0.";
            }
        }
        $entry = $m['entry'] ?? 'Plugin.php';
        if (!is_string($entry) || !preg_match('/^[A-Za-z0-9_\-\/]+\.php$/', $entry) || str_contains($entry, '..')) {
            $errors[] = 'Manifest entry must be a relative .php file path.';
        }
        return $errors;
    }

    public static function isCompatible(array $manifest): bool
    {
        return version_compare(app_version(), (string) $manifest['minimum_hostflow_version'], '>=');
    }

    /** Read a manifest without executing any plugin code. */
    public static function readManifest(string $dir): ?array
    {
        $file = $dir . '/plugin.json';
        if (!is_file($file)) {
            return null;
        }
        $m = json_decode((string) file_get_contents($file), true);
        return self::validateManifest($m) ? null : $m;
    }

    /**
     * All plugins found on disk merged with database registrations.
     * Returns id => ['manifest', 'path', 'source', 'record' (db row|null), 'compatible', 'update_available', 'manifest_error']
     */
    public function discover(): array
    {
        $found = [];
        foreach (config('plugin_paths') as $source => $base) {
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $raw = is_file($dir . '/plugin.json') ? json_decode((string) file_get_contents($dir . '/plugin.json'), true) : null;
                $errors = self::validateManifest($raw);
                $id = is_array($raw) && isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : basename($dir);
                if (!$errors && $raw['id'] !== basename($dir)) {
                    $errors[] = 'Plugin directory name must match the manifest id.';
                }
                // Uploaded copies take precedence over bundled ones with the same id.
                if (isset($found[$id]) && $source === 'bundled') {
                    continue;
                }
                $found[$id] = ['manifest' => $errors ? null : $raw, 'path' => $dir, 'source' => $source, 'manifest_error' => $errors ? implode(' ', $errors) : null];
            }
        }
        $records = [];
        foreach (db()->fetchAll('SELECT * FROM plugins') as $row) {
            $records[$row['plugin_id']] = $row;
        }
        foreach ($records as $id => $row) {
            $found[$id] ??= ['manifest' => null, 'path' => null, 'source' => $row['source'], 'manifest_error' => 'Plugin files are missing.'];
        }
        foreach ($found as $id => &$p) {
            $p['id'] = $id;
            $p['record'] = $records[$id] ?? null;
            $p['compatible'] = $p['manifest'] ? self::isCompatible($p['manifest']) : false;
            $p['update_available'] = $p['manifest'] && $p['record'] && version_compare($p['manifest']['version'], $p['record']['version'], '>');
        }
        unset($p);
        ksort($found);
        return $found;
    }

    public function find(string $id): ?array
    {
        return $this->discover()[$id] ?? null;
    }

    // --------------------------------------------------------------- loading

    /** Load all enabled plugins (once per request / cron run). Failures never break the core. */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        try {
            $rows = db()->fetchAll("SELECT * FROM plugins WHERE status = 'enabled'");
        } catch (\PDOException) {
            return;
        }
        if (!$rows) {
            return;
        }
        $all = $this->discover();
        foreach ($rows as $row) {
            $id = $row['plugin_id'];
            $info = $all[$id] ?? null;
            try {
                if (!$info || !$info['manifest']) {
                    throw new \RuntimeException($info['manifest_error'] ?? 'Plugin files not found.');
                }
                if (!$info['compatible']) {
                    throw new \RuntimeException('Requires HostFlow ' . $info['manifest']['minimum_hostflow_version'] . ' or newer.');
                }
                $this->load($id, $info);
            } catch (\Throwable $e) {
                $this->loadErrors[$id] = $e->getMessage();
                Logger::error("Plugin {$id} failed to load: " . $e->getMessage(), [], 'plugins');
                db()->update('plugins', ['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()], 'plugin_id = :id', ['id' => $id]);
            }
        }
    }

    /** Instantiate a plugin and call register(). */
    private function load(string $id, array $info, bool $register = true): array
    {
        if (isset($this->loaded[$id])) {
            return $this->loaded[$id];
        }
        $plugin = $this->instantiate($info);
        $ctx = new PluginContext($id, $info['path'], $info['manifest']);
        if ($register) {
            $plugin->register($ctx);
        }
        return $this->loaded[$id] = ['plugin' => $plugin, 'ctx' => $ctx];
    }

    private function instantiate(array $info): BasePlugin
    {
        $entry = realpath($info['path'] . '/' . ($info['manifest']['entry'] ?? 'Plugin.php'));
        $root = realpath($info['path']);
        if (!$entry || !$root || !str_starts_with($entry, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Plugin entry file not found.');
        }
        $plugin = (static fn ($__file) => require $__file)($entry);
        if (!$plugin instanceof BasePlugin) {
            throw new \RuntimeException('Plugin entry file must return an instance of App\Plugins\BasePlugin.');
        }
        return $plugin;
    }

    /** Context for a plugin regardless of status (used for lifecycle hooks and settings pages). */
    public function context(string $id): array
    {
        $info = $this->find($id);
        if (!$info || !$info['manifest']) {
            throw new \RuntimeException('Plugin not found or manifest invalid.');
        }
        if (isset($this->loaded[$id])) {
            return $this->loaded[$id];
        }
        // Load without registering routes/navigation, but collect declared settings.
        $plugin = $this->instantiate($info);
        $ctx = new PluginContext($id, $info['path'], $info['manifest']);
        return ['plugin' => $plugin, 'ctx' => $ctx];
    }

    /** Declared settings fields for a plugin (calls register() on an isolated context). */
    public function settingsFields(string $id): array
    {
        if (isset($this->loaded[$id])) {
            return $this->loaded[$id]['ctx']->settingsFields;
        }
        // Run register() on an isolated context to collect declared fields; navigation side effects are discarded.
        ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
        $snapshot = \App\Core\Navigation::snapshot();
        try {
            $plugin->register($ctx);
        } catch (\Throwable $e) {
            Logger::error("Plugin {$id} register() failed while reading settings: " . $e->getMessage(), [], 'plugins');
        } finally {
            \App\Core\Navigation::restore($snapshot);
        }
        return $ctx->settingsFields;
    }

    public function registerRoutes(Router $router): void
    {
        foreach ($this->loaded as $id => $l) {
            foreach ($l['ctx']->routes as $route) {
                if ($router->has($route['method'], $route['path'])) {
                    $this->loadErrors[$id] = "Route {$route['method']} {$route['path']} conflicts with an existing route and was not registered.";
                    Logger::error("Plugin {$id}: " . $this->loadErrors[$id], [], 'plugins');
                    continue;
                }
                $router->add($route['method'], $route['path'], $route['handler'], ['role:' . implode(',', $route['roles'])], false);
            }
        }
    }

    /** Scheduled tasks of enabled plugins that are due. */
    public function scheduledTasks(): array
    {
        $this->boot();
        $tasks = [];
        foreach ($this->loaded as $id => $l) {
            foreach ($l['ctx']->tasks as $name => $task) {
                $key = "plugin.{$id}.task.{$name}.last_run";
                $last = (int) Settings::get($key, '0');
                if (time() - $last < $task['interval'] * 60) {
                    continue;
                }
                $tasks["{$id}:{$name}"] = [
                    'plugin' => $id,
                    'name' => $name,
                    'callback' => function () use ($task, $key) {
                        Settings::set($key, (string) time());
                        ($task['callback'])();
                    },
                ];
            }
        }
        return $tasks;
    }

    public function loadErrors(): array
    {
        return $this->loadErrors;
    }

    public function isLoaded(string $id): bool
    {
        return isset($this->loaded[$id]);
    }

    // ------------------------------------------------------------- lifecycle

    public function install(string $id): void
    {
        $info = $this->find($id);
        if (!$info || !$info['manifest']) {
            throw new \RuntimeException($info['manifest_error'] ?? 'Plugin not found.');
        }
        if ($info['record']) {
            throw new \RuntimeException('Plugin is already installed.');
        }
        if (!$info['compatible']) {
            throw new \RuntimeException('This plugin requires HostFlow ' . $info['manifest']['minimum_hostflow_version'] . ' or newer.');
        }
        $this->runMigrations($id, $info['path']);
        db()->insert('plugins', [
            'plugin_id' => $id,
            'name' => $info['manifest']['name'],
            'version' => $info['manifest']['version'],
            'source' => $info['source'],
            'status' => 'installed',
            'installed_at' => now(),
            'updated_at' => now(),
        ]);
        ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
        $plugin->onInstall($ctx);
        audit('plugin.installed', 'plugin', null, ['plugin' => $id, 'version' => $info['manifest']['version']]);
    }

    public function enable(string $id): void
    {
        $info = $this->find($id);
        if (!$info || !$info['record']) {
            throw new \RuntimeException('Plugin is not installed.');
        }
        if (!$info['manifest'] || !$info['compatible']) {
            throw new \RuntimeException($info['manifest_error'] ?? 'Plugin is not compatible with this HostFlow version.');
        }
        if ($info['update_available']) {
            throw new \RuntimeException('Newer plugin files are present. Run the update first.');
        }
        $pending = (new Migrator(db()))->pending($info['path'] . '/migrations', 'plugin:' . $id);
        if ($pending) {
            throw new \RuntimeException('Plugin has pending database migrations; it cannot be enabled until they succeed.');
        }
        ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
        // Dry-run register() so broken plugins are rejected before activation.
        $plugin->register($ctx);
        $plugin->onEnable($ctx);
        db()->update('plugins', ['status' => 'enabled', 'enabled_at' => now(), 'last_error' => null, 'updated_at' => now()], 'plugin_id = :id', ['id' => $id]);
        audit('plugin.enabled', 'plugin', null, ['plugin' => $id]);
    }

    public function disable(string $id): void
    {
        $info = $this->find($id);
        if (!$info || !$info['record']) {
            throw new \RuntimeException('Plugin is not installed.');
        }
        try {
            if ($info['manifest']) {
                ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
                $plugin->onDisable($ctx);
            }
        } catch (\Throwable $e) {
            Logger::error("Plugin {$id} onDisable failed: " . $e->getMessage(), [], 'plugins');
        }
        db()->update('plugins', ['status' => 'disabled', 'updated_at' => now()], 'plugin_id = :id', ['id' => $id]);
        audit('plugin.disabled', 'plugin', null, ['plugin' => $id]);
    }

    /** Apply a newer version already present on disk (after upload or deployment). */
    public function update(string $id): void
    {
        $info = $this->find($id);
        if (!$info || !$info['record'] || !$info['manifest']) {
            throw new \RuntimeException('Plugin is not installed or its files are invalid.');
        }
        if (!$info['compatible']) {
            throw new \RuntimeException('The new version requires HostFlow ' . $info['manifest']['minimum_hostflow_version'] . ' or newer.');
        }
        $from = $info['record']['version'];
        try {
            $this->runMigrations($id, $info['path']);
        } catch (\Throwable $e) {
            db()->update('plugins', ['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()], 'plugin_id = :id', ['id' => $id]);
            throw $e;
        }
        db()->update('plugins', ['version' => $info['manifest']['version'], 'name' => $info['manifest']['name'], 'last_error' => null, 'updated_at' => now(), 'status' => $info['record']['status'] === 'error' ? 'disabled' : $info['record']['status']], 'plugin_id = :id', ['id' => $id]);
        ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
        $plugin->onUpdate($ctx, $from);
        audit('plugin.updated', 'plugin', null, ['plugin' => $id, 'from' => $from, 'to' => $info['manifest']['version']]);
    }

    /**
     * Remove a plugin's registration. Data is preserved unless $deleteData is true.
     * Uploaded plugin files are removed only when $deleteFiles is true.
     */
    public function uninstall(string $id, bool $deleteData = false, bool $deleteFiles = false): void
    {
        $info = $this->find($id);
        if (!$info || !$info['record']) {
            throw new \RuntimeException('Plugin is not installed.');
        }
        if ($info['record']['status'] === 'enabled') {
            $this->disable($id);
        }
        if ($info['manifest']) {
            try {
                ['plugin' => $plugin, 'ctx' => $ctx] = $this->context($id);
                $plugin->onUninstall($ctx, $deleteData);
            } catch (\Throwable $e) {
                if ($deleteData) {
                    throw new \RuntimeException('Plugin data removal failed: ' . $e->getMessage(), 0, $e);
                }
            }
        }
        if ($deleteData) {
            db()->query('DELETE FROM migrations WHERE scope = ?', ['plugin:' . $id]);
            db()->query("DELETE FROM settings WHERE `key` LIKE ? OR `key` LIKE ?", ['plugin.' . $id . '.%', 'secret.plugin.' . $id . '.%']);
            Settings::flush();
        }
        db()->query('DELETE FROM plugins WHERE plugin_id = ?', [$id]);
        if ($deleteFiles && $info['source'] === 'uploaded' && $info['path']) {
            PluginInstaller::removeDirectory($info['path']);
        }
        audit('plugin.uninstalled', 'plugin', null, ['plugin' => $id, 'delete_data' => $deleteData, 'delete_files' => $deleteFiles]);
    }

    /** Delete the files of an uploaded plugin that is not installed. */
    public function deleteFiles(string $id): void
    {
        $info = $this->find($id);
        if (!$info || $info['record'] || $info['source'] !== 'uploaded' || !$info['path']) {
            throw new \RuntimeException('Only uploaded plugins that are not installed can be deleted.');
        }
        PluginInstaller::removeDirectory($info['path']);
        audit('plugin.files_deleted', 'plugin', null, ['plugin' => $id]);
    }

    public function saveSettings(string $id, array $input): void
    {
        foreach ($this->settingsFields($id) as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $value = $type === 'checkbox' ? (!empty($input[$key]) ? '1' : '0') : trim((string) ($input[$key] ?? ''));
            if ($type === 'select' && !array_key_exists($value, $field['options'] ?? [])) {
                continue;
            }
            if ($type === 'password') {
                if ($value !== '') {
                    Settings::set('secret.plugin.' . $id . '.' . $key, $value);
                }
                continue;
            }
            Settings::set('plugin.' . $id . '.' . $key, $value);
        }
        audit('plugin.configured', 'plugin', null, ['plugin' => $id]);
    }

    private function runMigrations(string $id, string $path): array
    {
        if (!is_dir($path . '/migrations')) {
            return [];
        }
        return (new Migrator(db()))->migrate($path . '/migrations', 'plugin:' . $id);
    }
}
