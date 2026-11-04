<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Validates and extracts uploaded plugin ZIP archives.
 *
 * Safety rules: Super Admin only (enforced by the route), size limits, no path
 * traversal/absolute paths/symlinks, whitelisted file types, a single plugin root,
 * extraction into storage/tmp (outside the web root) before being moved into
 * storage/plugins. The plugin is never activated automatically.
 */
final class PluginInstaller
{
    private const MAX_FILES = 2000;
    private const MAX_UNCOMPRESSED = 50 * 1024 * 1024;
    private const ALLOWED_EXT = ['php', 'json', 'sql', 'html', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'md', 'txt', 'woff', 'woff2', 'ttf'];

    /**
     * Inspect and stage an uploaded ZIP. Returns
     * ['id', 'manifest', 'staged_path', 'sha256', 'existing' => discover() entry|null, 'action' => install|update].
     */
    public static function stage(string $zipPath, string $originalName): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to install plugins. Ask your host to enable it.');
        }
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new \RuntimeException('Please upload a .zip file.');
        }
        $maxBytes = (int) config('plugin_max_upload_mb', 10) * 1024 * 1024;
        if (!is_file($zipPath) || filesize($zipPath) > $maxBytes) {
            throw new \RuntimeException('The plugin archive is missing or larger than ' . config('plugin_max_upload_mb', 10) . ' MB.');
        }
        $fh = fopen($zipPath, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04") {
            throw new \RuntimeException('The uploaded file is not a valid ZIP archive.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('The ZIP archive could not be opened.');
        }
        try {
            $entries = self::inspect($zip);
            $staged = BASE_PATH . '/storage/tmp/plugin-' . bin2hex(random_bytes(6));
            if (!@mkdir($staged, 0775, true) && !is_dir($staged)) {
                throw new \RuntimeException('Could not create a temporary directory in storage/tmp.');
            }
            try {
                foreach ($entries as $index => $relative) {
                    $target = $staged . '/' . $relative;
                    if (str_ends_with($relative, '/')) {
                        @mkdir($target, 0775, true);
                        continue;
                    }
                    @mkdir(dirname($target), 0775, true);
                    $stream = $zip->getStream($zip->getNameIndex($index));
                    if (!$stream) {
                        throw new \RuntimeException("Could not read {$relative} from the archive.");
                    }
                    $out = fopen($target, 'wb');
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    fclose($stream);
                }
                $manifestRaw = is_file($staged . '/plugin.json') ? json_decode((string) file_get_contents($staged . '/plugin.json'), true) : null;
                $errors = PluginManager::validateManifest($manifestRaw);
                if ($errors) {
                    throw new \RuntimeException('Invalid plugin manifest: ' . implode(' ', $errors));
                }
                if (!is_file($staged . '/' . ($manifestRaw['entry'] ?? 'Plugin.php'))) {
                    throw new \RuntimeException('The plugin entry file declared in plugin.json is missing.');
                }
                if (!PluginManager::isCompatible($manifestRaw)) {
                    throw new \RuntimeException('This plugin requires HostFlow ' . $manifestRaw['minimum_hostflow_version'] . ' or newer (installed: ' . app_version() . ').');
                }
            } catch (\Throwable $e) {
                self::removeDirectory($staged);
                throw $e;
            }
        } finally {
            $zip->close();
        }
        $existing = PluginManager::instance()->find($manifestRaw['id']);
        $action = 'install';
        if ($existing && ($existing['record'] || $existing['manifest'])) {
            $current = $existing['record']['version'] ?? ($existing['manifest']['version'] ?? '0.0.0');
            if (!version_compare($manifestRaw['version'], $current, '>')) {
                self::removeDirectory($staged);
                throw new \RuntimeException("Plugin '{$manifestRaw['id']}' version {$current} is already present. Upload a newer version to update it.");
            }
            if ($existing['source'] === 'bundled' && $existing['record']) {
                self::removeDirectory($staged);
                throw new \RuntimeException("'{$manifestRaw['id']}' is bundled with HostFlow and is updated through core deployments.");
            }
            $action = 'update';
        }
        return ['id' => $manifestRaw['id'], 'manifest' => $manifestRaw, 'staged_path' => $staged, 'sha256' => hash_file('sha256', $zipPath), 'existing' => $existing, 'action' => $action];
    }

    /**
     * Validate every archive entry and map index => safe relative path
     * (with a single top-level folder stripped when present).
     */
    private static function inspect(\ZipArchive $zip): array
    {
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_FILES) {
            throw new \RuntimeException('The archive is empty or contains too many files.');
        }
        $names = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];
            if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[A-Za-z]:/', $name)) {
                throw new \RuntimeException('The archive contains an unsafe path: ' . $name);
            }
            $zip->getExternalAttributesIndex($i, $opsys, $attr);
            if ($opsys === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                throw new \RuntimeException('The archive contains a symbolic link, which is not allowed: ' . $name);
            }
            $total += (int) $stat['size'];
            if ($total > self::MAX_UNCOMPRESSED) {
                throw new \RuntimeException('The archive is too large when extracted.');
            }
            if (str_starts_with(basename($name), '__MACOSX') || str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store') {
                continue;
            }
            if (!str_ends_with($name, '/')) {
                $base = basename($name);
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (str_starts_with($base, '.') || !in_array($ext, self::ALLOWED_EXT, true)) {
                    throw new \RuntimeException('File type not allowed in plugins: ' . $name);
                }
            }
            $names[$i] = $name;
        }
        // Detect single top-level folder (e.g. "my-plugin/plugin.json").
        $tops = array_unique(array_map(fn ($n) => explode('/', $n)[0], $names));
        $prefix = '';
        if (count($tops) === 1 && !in_array('plugin.json', $names, true)) {
            $prefix = reset($tops) . '/';
        }
        $out = [];
        foreach ($names as $i => $name) {
            $rel = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
            if ($rel === '' || $rel === false) {
                continue;
            }
            $out[$i] = $rel;
        }
        if (!in_array('plugin.json', $out, true)) {
            throw new \RuntimeException('plugin.json was not found at the root of the plugin archive.');
        }
        return $out;
    }

    /**
     * Move a staged plugin into storage/plugins. For updates the previous files are kept
     * as a backup until the update succeeds. Returns the backup path (or null).
     */
    public static function commit(array $staged): ?string
    {
        $base = config('plugin_paths')['uploaded'];
        @mkdir($base, 0775, true);
        $target = $base . '/' . $staged['id'];
        $backup = null;
        if (is_dir($target)) {
            $backup = BASE_PATH . '/storage/tmp/plugin-backup-' . $staged['id'] . '-' . date('YmdHis');
            if (!rename($target, $backup)) {
                throw new \RuntimeException('Could not move the existing plugin files aside.');
            }
        }
        if (!rename($staged['staged_path'], $target)) {
            if ($backup) {
                rename($backup, $target);
            }
            throw new \RuntimeException('Could not move plugin files into storage/plugins.');
        }
        return $backup;
    }

    public static function restoreBackup(string $id, string $backup): void
    {
        $target = config('plugin_paths')['uploaded'] . '/' . $id;
        self::removeDirectory($target);
        rename($backup, $target);
    }

    public static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $real = realpath($dir);
        $storage = realpath(BASE_PATH . '/storage');
        if (!$real || !$storage || !str_starts_with($real, $storage . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Refusing to delete a directory outside storage/.');
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($real);
    }
}
