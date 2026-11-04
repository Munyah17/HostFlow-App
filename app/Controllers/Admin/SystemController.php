<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\BackupService;
use App\Services\UpdateService;

final class SystemController extends Controller
{
    public function index(): string
    {
        return $this->view('admin.system.index', [
            'title' => 'System & updates',
            'version' => app_version(),
            'lastVersion' => UpdateService::lastVersion(),
            'pending' => UpdateService::pending(),
            'incompatible' => UpdateService::incompatiblePlugins(),
            'history' => db()->fetchAll('SELECT * FROM update_history ORDER BY id DESC LIMIT 20'),
            'backups' => BackupService::list(),
            'php' => PHP_VERSION,
            'cronUrl' => absolute_url('/cron/run'),
            'cronToken' => (string) \App\Core\Env::get('CRON_WEB_TOKEN', ''),
        ]);
    }

    /** Apply pending database migrations (with an automatic backup first). */
    public function migrate(): never
    {
        try {
            $result = UpdateService::migrate('web', backup: true);
            audit('system.migrated', 'system', null, $result);
            flash(empty($result['applied']) ? 'info' : 'success', empty($result['applied']) ? 'Database is already up to date.' : 'Applied ' . count($result['applied']) . ' migration(s).');
        } catch (\Throwable $e) {
            flash('error', 'Migration failed: ' . $e->getMessage());
        }
        redirect('/settings/system');
    }

    /** Create a database backup into storage/backups. */
    public function backup(): never
    {
        try {
            $file = BackupService::create('manual');
            audit('system.backup', 'system', null, ['file' => $file]);
            flash('success', 'Backup created: ' . basename($file));
        } catch (\Throwable $e) {
            flash('error', 'Backup failed: ' . $e->getMessage());
        }
        redirect('/settings/system');
    }
}
