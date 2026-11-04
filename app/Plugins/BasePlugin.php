<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Base class for HostFlow plugins.
 *
 * A plugin's entry file (default Plugin.php) must RETURN an instance, e.g.
 *
 *   return new class extends \App\Plugins\BasePlugin {
 *       public function register(\App\Plugins\PluginContext $ctx): void {
 *           $ctx->route('GET', '/admin/plugins/hello', fn () => $ctx->render('index'), ['super_admin', 'admin']);
 *           $ctx->navigation(['label' => 'Hello', 'url' => '/admin/plugins/hello', 'roles' => ['super_admin']]);
 *       }
 *   };
 *
 * Plugins run trusted server-side PHP inside HostFlow — they are NOT sandboxed.
 */
abstract class BasePlugin
{
    /** Register routes, navigation, providers, tasks and settings. Called on every request while enabled. */
    abstract public function register(PluginContext $ctx): void;

    /** Called once after the plugin's migrations ran during installation. */
    public function onInstall(PluginContext $ctx): void
    {
    }

    public function onEnable(PluginContext $ctx): void
    {
    }

    public function onDisable(PluginContext $ctx): void
    {
    }

    /** Called after an update's migrations ran. */
    public function onUpdate(PluginContext $ctx, string $fromVersion): void
    {
    }

    /**
     * Called on uninstall. Only delete plugin-owned data when $deleteData is true
     * (explicitly requested by the Super Admin). Never touch core records.
     */
    public function onUninstall(PluginContext $ctx, bool $deleteData): void
    {
    }
}
