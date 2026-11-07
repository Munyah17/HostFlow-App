<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Migrator;

/**
 * Core update procedure: back up the database, apply pending versioned migrations,
 * record the result in update_history. Used by `php bin/hostflow migrate`, the deployment
 * workflow and Settings → System. Code rollback does NOT reverse migrations; migrations
 * must therefore be additive/backward-compatible.
 */
final class UpdateService
{
    public static function migrationsDir(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    public static function pending(): array
    {
        return array_keys((new Migrator(db()))->pending(self::migrationsDir()));
    }

    public static function lastVersion(): ?string
    {
        try {
            return db()->fetchColumn("SELECT to_version FROM update_history WHERE status = 'success' ORDER BY id DESC LIMIT 1") ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /** Run pending migrations. Returns ['applied' => [...], 'backup' => ?string]. Throws on failure (after recording it). */
    public static function migrate(string $triggeredBy = 'cli', bool $backup = true): array
    {
        $migrator = new Migrator(db());
        $migrator->ensureTable();
        $pending = $migrator->pending(self::migrationsDir());
        $from = self::lastVersion();
        $to = app_version();
        if (!$pending) {
            if ($from !== $to) {
                self::record($from, $to, [], null, 'success', 'Version updated (no database changes).', $triggeredBy);
            }
            return ['applied' => [], 'backup' => null];
        }
        $backupFile = null;
        $hasTables = (int) db()->fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'settings'") > 0;
        if ($backup && $hasTables) {
            $backupFile = BackupService::create('pre-migrate');
        }
        try {
            $applied = $migrator->migrate(self::migrationsDir());
        } catch (\Throwable $e) {
            self::record($from, $to, [], $backupFile, 'failed', $e->getMessage(), $triggeredBy);
            throw $e;
        }
        self::record($from, $to, $applied, $backupFile, 'success', count($applied) . ' migration(s) applied.', $triggeredBy);
        return ['applied' => $applied, 'backup' => $backupFile];
    }

    private static function record(?string $from, string $to, array $migrations, ?string $backup, string $status, string $message, string $by): void
    {
        try {
            db()->insert('update_history', [
                'from_version' => $from,
                'to_version' => $to,
                'migrations' => $migrations ? implode("\n", $migrations) : null,
                'backup_file' => $backup ? basename($backup) : null,
                'status' => $status,
                'message' => mb_substr($message, 0, 5000),
                'triggered_by' => $by,
                'created_at' => now(),
            ]);
        } catch (\PDOException) {
            // update_history may not exist if the very first migration failed
        }
    }
}
