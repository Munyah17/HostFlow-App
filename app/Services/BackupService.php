<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Plain-PHP MySQL dump (gzip) for pre-migration safety backups.
 * Suitable for HostFlow-sized databases on shared hosting where mysqldump may be unavailable.
 */
final class BackupService
{
    public static function dir(): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Create a backup and return its file path. */
    public static function create(string $label = 'manual'): string
    {
        $pdo = db()->pdo();
        $file = self::dir() . '/hostflow-' . preg_replace('/[^a-z0-9_-]/i', '', $label) . '-' . date('Ymd-His') . '.sql.gz';
        $gz = gzopen($file, 'wb6');
        if (!$gz) {
            throw new \RuntimeException('Cannot write backup file in storage/backups.');
        }
        gzwrite($gz, "-- HostFlow backup " . date('c') . " (v" . app_version() . ")\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
            $stmt = $pdo->query("SELECT * FROM `{$table}`", \PDO::FETCH_ASSOC);
            $batch = [];
            foreach ($stmt as $row) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                $batch[] = '(' . implode(',', $values) . ')';
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($file, 0600);
        self::prune();
        return $file;
    }

    /** Keep the 15 most recent backups. */
    private static function prune(): void
    {
        $files = glob(self::dir() . '/hostflow-*.sql.gz') ?: [];
        rsort($files);
        foreach (array_slice($files, 15) as $old) {
            @unlink($old);
        }
    }

    public static function list(): array
    {
        $files = glob(self::dir() . '/hostflow-*.sql.gz') ?: [];
        rsort($files);
        return array_map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], $files);
    }
}
