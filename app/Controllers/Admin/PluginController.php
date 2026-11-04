<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Plugins\PluginInstaller;
use App\Plugins\PluginManager;

final class PluginController extends Controller
{
    public function index(): string
    {
        $manager = PluginManager::instance();
        return $this->view('admin.plugins.index', [
            'title' => 'Plugins',
            'plugins' => $manager->discover(),
            'loadErrors' => $manager->loadErrors(),
        ]);
    }

    /** Upload a plugin zip, stage it and install/upgrade it. */
    public function upload(): never
    {
        $file = Request::file('plugin');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            back_with_errors(['plugin' => 'Choose a plugin .zip file to upload.'], '/settings/plugins');
        }
        try {
            $staged = PluginInstaller::stage($file['tmp_name'], (string) $file['name']);
            $id = PluginInstaller::commit($staged);
            if ($id === null) {
                throw new \RuntimeException('No valid plugin manifest found in the archive.');
            }
            PluginManager::instance()->install($id);
            audit('plugin.installed', 'plugin', null, ['plugin' => $id]);
            flash('success', "Plugin '{$id}' installed. Enable it to start using it.");
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/settings/plugins');
    }

    public function configure(int $id): string
    {
        $plugin = $this->pluginByRow($id);
        $manager = PluginManager::instance();
        return $this->view('admin.plugins.configure', [
            'title' => 'Configure · ' . ($plugin['manifest']['name'] ?? $plugin['plugin_id']),
            'plugin' => $plugin,
            'fields' => $manager->settingsFields($plugin['plugin_id']),
            'values' => $this->currentValues($plugin['plugin_id'], $manager),
        ]);
    }

    public function saveConfig(int $id): never
    {
        $plugin = $this->pluginByRow($id);
        PluginManager::instance()->saveSettings($plugin['plugin_id'], $_POST);
        flash('success', 'Plugin settings saved.');
        redirect('/settings/plugins/' . $id . '/configure');
    }

    /** Enable / disable / uninstall a plugin. */
    public function action(int $id, string $action): never
    {
        $plugin = $this->pluginByRow($id);
        $manager = PluginManager::instance();
        $pid = $plugin['plugin_id'];
        try {
            match ($action) {
                'install' => $manager->install($pid),
                'enable' => $manager->enable($pid),
                'disable' => $manager->disable($pid),
                'update' => $manager->update($pid),
                'uninstall' => $manager->uninstall($pid, deleteData: !empty($_POST['delete_data'])),
                'delete' => $manager->deleteFiles($pid),
                default => throw new \InvalidArgumentException('Unknown action.'),
            };
            audit('plugin.' . $action, 'plugin', null, ['plugin' => $pid]);
            flash('success', "Plugin '{$pid}': " . status_label($action) . ' complete.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/settings/plugins');
    }

    private function pluginByRow(int $id): array
    {
        $row = db()->fetch('SELECT * FROM plugins WHERE id = ?', [$id]);
        if (!$row) {
            abort(404);
        }
        $info = PluginManager::instance()->find($row['plugin_id']) ?? [];
        return $info + $row + ['manifest' => $info['manifest'] ?? null, 'plugin_id' => $row['plugin_id']];
    }

    private function currentValues(string $pluginId, PluginManager $manager): array
    {
        $values = [];
        foreach ($manager->settingsFields($pluginId) as $field) {
            $key = $field['key'];
            $secret = \App\Services\Settings::get('secret.plugin.' . $pluginId . '.' . $key);
            $values[$key] = $secret !== null && $secret !== ''
                ? $secret
                : \App\Services\Settings::get('plugin.' . $pluginId . '.' . $key, $field['default'] ?? '');
        }
        return $values;
    }
}
