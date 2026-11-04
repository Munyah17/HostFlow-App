<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;

/**
 * Key/value application settings stored in the `settings` table.
 * Secret values are encrypted at rest with APP_KEY.
 */
final class Settings
{
    private static ?array $cache = null;

    /** Keys whose values are always stored encrypted. */
    public const SECRET_KEYS = ['smtp_password', 'paynow_integration_key'];

    public const DEFAULTS = [
        // General / business
        'business_name' => 'Global Space Web Hosting',
        'business_email' => 'support@globalspaceweb.co.zw',
        'business_phone' => '',
        'business_address' => '',
        'business_website' => 'https://globalspaceweb.co.zw',
        // Billing
        'currency' => 'USD',
        'invoice_prefix' => 'INV-',
        'invoice_next_number' => '1001',
        'invoice_due_days' => '7',
        'renewal_invoice_days' => '14',
        'invoice_footer' => 'Thank you for your business.',
        'bank_instructions' => '',
        // Automation
        'auto_invoice_enabled' => '1',
        'reminder_days_before' => '7,3,1',
        'reminder_on_due_date' => '1',
        'overdue_reminder_days' => '1,3',
        'auto_suspend_enabled' => '1',
        'suspend_grace_days' => '7',
        'auto_unsuspend_enabled' => '1',
        'auto_provision_enabled' => '1',
        'termination_flag_days' => '30',
        'sync_interval_minutes' => '60',
        'provision_max_attempts' => '5',
        // Domains
        'domain_orders_enabled' => '1',
        'domain_reminder_days' => '30,14,7,1',
        // Email / SMTP
        'mail_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'mail_from_address' => '',
        'mail_from_name' => 'Global Space Web Hosting',
        // Payments
        'paynow_enabled' => '0',
        'paynow_integration_id' => '',
        'paynow_integration_key' => '',
        'paynow_auth_email' => '',
        'paynow_mobile_enabled' => '0',
        // Security / portal
        'allow_registration' => '1',
        'min_password_length' => '10',
        'ticket_categories' => 'General,Billing,Technical,Email,Domain',
        'default_country' => 'Zimbabwe',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function bool(string $key): bool
    {
        return in_array((string) self::get($key), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /** Parse a comma-separated list of integers (e.g. reminder days). */
    public static function intList(string $key): array
    {
        $values = array_filter(array_map('trim', explode(',', (string) self::get($key, ''))), 'is_numeric');
        return array_values(array_unique(array_map('intval', $values)));
    }

    public static function set(string $key, mixed $value): void
    {
        $value = $value === null ? '' : (string) $value;
        $encrypted = in_array($key, self::SECRET_KEYS, true) || str_starts_with($key, 'secret.');
        $stored = $encrypted && $value !== '' ? Crypto::encrypt($value) : $value;
        Database::instance()->query(
            'INSERT INTO settings (`key`, `value`, is_encrypted, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), is_encrypted = VALUES(is_encrypted), updated_at = NOW()',
            [$key, $stored, $encrypted ? 1 : 0]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        if (!Database::isConnected()) {
            return;
        }
        try {
            foreach (Database::instance()->fetchAll('SELECT `key`, `value`, is_encrypted FROM settings') as $row) {
                $value = (string) $row['value'];
                if ($row['is_encrypted'] && $value !== '') {
                    try {
                        $value = Crypto::decrypt($value);
                    } catch (\Throwable) {
                        $value = '';
                    }
                }
                self::$cache[$row['key']] = $value;
            }
        } catch (\PDOException) {
            // settings table may not exist yet (during install)
        }
    }

    /**
     * Atomically reserve the next invoice number. Must run inside a transaction.
     */
    public static function nextInvoiceNumber(): string
    {
        $db = Database::instance();
        $db->query("INSERT IGNORE INTO settings (`key`, `value`, is_encrypted, updated_at) VALUES ('invoice_next_number', ?, 0, NOW())", [self::DEFAULTS['invoice_next_number']]);
        $next = (int) $db->fetchColumn("SELECT `value` FROM settings WHERE `key` = 'invoice_next_number' FOR UPDATE");
        $prefix = (string) self::get('invoice_prefix', 'INV-');
        do {
            $number = $prefix . $next;
            $next++;
        } while ((int) $db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE number = ?', [$number]) > 0);
        $db->query("UPDATE settings SET `value` = ?, updated_at = NOW() WHERE `key` = 'invoice_next_number'", [(string) $next]);
        if (self::$cache !== null) {
            self::$cache['invoice_next_number'] = (string) $next;
        }
        return $number;
    }
}
