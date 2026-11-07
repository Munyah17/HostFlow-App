<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Versioned migration runner.
 *
 * Migrations are files in a directory, applied in filename order:
 *   - *.sql : one or more SQL statements separated by ";" at end of line
 *   - *.php : must return callable(Database $db): void
 *
 * Applied migrations are recorded in the `migrations` table with a scope
 * ("core") so they are never re-run.
 */
final class Migrator
{
    public function __construct(private Database $db)
    {
    }

    public function ensureTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scope VARCHAR(100) NOT NULL DEFAULT \'core\',
                migration VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL,
                UNIQUE KEY uq_migration (scope, migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function files(string $dir): array
    {
        $files = array_merge(glob($dir . '/*.sql') ?: [], glob($dir . '/*.php') ?: []);
        $map = [];
        foreach ($files as $file) {
            $map[basename($file)] = $file;
        }
        ksort($map, SORT_STRING);
        return $map;
    }

    public function applied(string $scope = 'core'): array
    {
        $this->ensureTable();
        return array_column($this->db->fetchAll('SELECT migration FROM migrations WHERE scope = ? ORDER BY id', [$scope]), 'migration');
    }

    public function pending(string $dir, string $scope = 'core'): array
    {
        $applied = array_flip($this->applied($scope));
        return array_filter($this->files($dir), fn ($name) => !isset($applied[$name]), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Apply pending migrations. Returns list of applied migration names.
     * Throws on the first failure (earlier successful migrations remain recorded).
     */
    public function migrate(string $dir, string $scope = 'core'): array
    {
        $done = [];
        foreach ($this->pending($dir, $scope) as $name => $file) {
            try {
                if (str_ends_with($name, '.php')) {
                    $callable = require $file;
                    if (!is_callable($callable)) {
                        throw new \RuntimeException('PHP migration must return a callable.');
                    }
                    $callable($this->db);
                } else {
                    foreach (self::splitSql((string) file_get_contents($file)) as $statement) {
                        $this->db->pdo()->exec($statement);
                    }
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migration {$scope}/{$name} failed: " . $e->getMessage(), 0, $e);
            }
            $this->db->insert('migrations', ['scope' => $scope, 'migration' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
            $done[] = $name;
        }
        return $done;
    }

    public static function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*(\r?\n|$)/', $sql);
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }
}
