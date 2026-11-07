<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Validator;
use App\Services\Settings;
use App\Services\UpdateService;

final class InstallController extends Controller
{
    private function guard(): void
    {
        if (hostflow_installed()) {
            abort(404);
        }
    }

    public static function requirements(): array
    {
        $writable = fn ($p) => is_dir($p) ? is_writable($p) : is_writable(dirname($p));
        return [
            ['PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION, true],
            ['PDO MySQL extension', extension_loaded('pdo_mysql'), '', true],
            ['cURL extension', extension_loaded('curl'), '', true],
            ['OpenSSL extension', extension_loaded('openssl'), '', true],
            ['mbstring extension', extension_loaded('mbstring'), '', true],
            ['DOM / GD (PDF invoices)', extension_loaded('dom') && extension_loaded('gd'), '', false],
            ['storage/ is writable', $writable(BASE_PATH . '/storage'), '', true],
            ['Application root writable (to create .env)', is_writable(BASE_PATH) || (is_file(BASE_PATH . '/.env') && is_writable(BASE_PATH . '/.env')), '', true],
        ];
    }

    public function show(): string
    {
        $this->guard();
        $scheme = Request::isHttps() ? 'https' : 'http';
        $suggestedUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . Request::basePath();
        return $this->view('install.index', ['title' => 'Install HostFlow', 'requirements' => self::requirements(), 'suggestedUrl' => $suggestedUrl, 'wide' => true], 'layouts.auth');
    }

    public function install(): never
    {
        $this->guard();
        foreach (self::requirements() as [$label, $ok, , $required]) {
            if ($required && !$ok) {
                back_with_errors(['requirements' => "Requirement not met: {$label}"], '/install');
            }
        }
        $data = Request::only(['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'app_url', 'business_name', 'business_email', 'admin_name', 'admin_email', 'admin_password', 'admin_password_confirmation']);
        $data['db_pass'] = (string) ($_POST['db_pass'] ?? '');
        $data['admin_password'] = (string) ($_POST['admin_password'] ?? '');
        $data['admin_password_confirmation'] = (string) ($_POST['admin_password_confirmation'] ?? '');
        $errors = Validator::make($data, [
            'db_host' => 'required|max:190',
            'db_port' => 'required|integer',
            'db_name' => 'required|max:64|regex:/^[A-Za-z0-9_\-]+$/',
            'db_user' => 'required|max:80',
            'app_url' => 'required|url',
            'business_name' => 'required|max:150',
            'business_email' => 'required|email',
            'admin_name' => 'required|max:150',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:10|confirmed',
        ], ['db_host' => 'Database host', 'db_name' => 'Database name', 'db_user' => 'Database username', 'app_url' => 'Application URL', 'admin_password' => 'Super Admin password']);
        if ($errors) {
            back_with_errors($errors, '/install');
        }
        $dbConfig = ['host' => $data['db_host'], 'port' => (int) $data['db_port'], 'database' => $data['db_name'], 'username' => $data['db_user'], 'password' => $data['db_pass']];
        try {
            $db = new Database($dbConfig);
        } catch (\PDOException $e) {
            back_with_errors(['db_host' => 'Could not connect to the database: ' . $e->getMessage()], '/install');
        }
        if ($db->fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','settings')")) {
            back_with_errors(['db_name' => 'This database already contains HostFlow tables. Use an empty database (or restore the existing installation\'s .env and storage/installed.lock).'], '/install');
        }

        $appUrl = rtrim($data['app_url'], '/');
        $env = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_KEY' => Crypto::generateKey(),
            'APP_TIMEZONE' => 'Africa/Harare',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) (int) $data['db_port'],
            'DB_DATABASE' => $data['db_name'],
            'DB_USERNAME' => $data['db_user'],
            'DB_PASSWORD' => $data['db_pass'],
            'CRON_WEB_TOKEN' => '',
        ];
        try {
            foreach ($env as $k => $v) {
                Env::set($k, $v);
            }
            Database::setInstance($db);
            Request::setBasePath(null);
            UpdateService::migrate('installer', backup: false);
            Settings::flush();
            Settings::setMany([
                'business_name' => $data['business_name'],
                'business_email' => strtolower($data['business_email']),
                'mail_from_name' => $data['business_name'],
                'mail_from_address' => strtolower($data['business_email']),
                'installed_at' => now(),
            ]);
            $userId = $db->insert('users', [
                'role' => Auth::SUPER_ADMIN,
                'name' => $data['admin_name'],
                'email' => strtolower($data['admin_email']),
                'password_hash' => password_hash($data['admin_password'], PASSWORD_DEFAULT),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->seedPlans($db);
            Env::write(BASE_PATH . '/.env', $env);
            @mkdir(BASE_PATH . '/storage', 0775, true);
            file_put_contents(BASE_PATH . '/storage/installed.lock', 'Installed ' . date('c') . ' (v' . app_version() . ")\n");
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Installation failed: ' . $e->getMessage());
            back_with_errors(['install' => 'Installation failed: ' . $e->getMessage()], '/install');
        }
        $user = $db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        Auth::login($user);
        audit('system.installed', null, null, ['version' => app_version()]);
        flash('success', 'HostFlow is installed. Next: add your hosting server under Integrations, map plans to packages, and configure SMTP and Paynow in Settings.');
        redirect('/dashboard');
    }

    /** Starter plans from the original mockup, created hidden until packages are mapped. */
    private function seedPlans(Database $db): void
    {
        $plans = [
            ['web', 'Starter', 'One website · 5 GB storage', 6, 5, 50, 1, 5, 2],
            ['web', 'Business', 'Multiple websites · 20 GB storage', 12, 20, 100, 5, 20, 10],
            ['web', 'Professional', 'Larger sites · 50 GB storage', 22, 50, 250, 15, 50, 25],
            ['email', 'Business Email', 'Custom-domain email hosting', 8, 10, 50, 1, 10, 0],
        ];
        foreach ($plans as $i => [$type, $name, $desc, $price, $storage, $bw, $domains, $emails, $dbs]) {
            $db->insert('hosting_plans', [
                'type' => $type, 'name' => $name, 'description' => $desc,
                'price_monthly' => $price, 'price_annual' => $price * 12, 'default_cycle' => 'monthly',
                'storage_gb' => $storage, 'bandwidth_gb' => $bw, 'domains_allowed' => $domains, 'email_accounts' => $emails, 'databases_allowed' => $dbs,
                'status' => 'hidden', 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
