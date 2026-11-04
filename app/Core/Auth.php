<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const CLIENT = 'client';

    public const ROLE_LABELS = [
        self::SUPER_ADMIN => 'Super Admin',
        self::ADMIN => 'Admin',
        self::CLIENT => 'Client',
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if ($id) {
                $user = db()->fetch('SELECT * FROM users WHERE id = ?', [(int) $id]);
                if ($user && $user['status'] === 'active' && hash_equals((string) ($_SESSION['pw_sig'] ?? ''), self::passwordSignature($user))) {
                    if ($user['role'] === self::CLIENT) {
                        $customer = $user['customer_id'] ? db()->fetch('SELECT status FROM customers WHERE id = ?', [$user['customer_id']]) : null;
                        if (!$customer || $customer['status'] !== 'active') {
                            $user = null;
                        }
                    }
                    self::$user = $user ?: null;
                }
                if (!self::$user) {
                    unset($_SESSION['user_id'], $_SESSION['pw_sig']);
                }
            }
        }
        return self::$user;
    }

    /** Signature that invalidates sessions when the password changes. */
    private static function passwordSignature(array $user): string
    {
        return hash('sha256', $user['id'] . '|' . $user['password_hash']);
    }

    public static function setUser(?array $user): void
    {
        self::$user = $user;
        self::$loaded = true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::user()['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function isSuper(): bool
    {
        return self::role() === self::SUPER_ADMIN;
    }

    public static function isStaff(): bool
    {
        return in_array(self::role(), [self::SUPER_ADMIN, self::ADMIN], true);
    }

    public static function isClient(): bool
    {
        return self::role() === self::CLIENT;
    }

    public static function customerId(): ?int
    {
        $id = self::user()['customer_id'] ?? null;
        return $id ? (int) $id : null;
    }

    public static function home(): string
    {
        return self::isClient() ? '/client' : '/dashboard';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['pw_sig'] = self::passwordSignature($user);
        unset($_SESSION['_csrf']);
        db()->update('users', ['last_login_at' => now(), 'last_login_ip' => Request::ip()], 'id = :id', ['id' => $user['id']]);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }

    /**
     * Attempt login with throttling (5 failures per 15 minutes per email/IP).
     * Returns [user|null, error|null].
     */
    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Request::ip();
        $failures = (int) db()->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$email, $ip]
        );
        if ($failures >= 8) {
            return [null, 'Too many failed sign-in attempts. Please wait 15 minutes and try again.'];
        }
        $user = db()->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            db()->insert('login_attempts', ['email' => $email, 'ip' => $ip, 'attempted_at' => now()]);
            return [null, 'The email address or password is incorrect.'];
        }
        if ($user['status'] !== 'active') {
            return [null, 'This account has been disabled. Please contact support.'];
        }
        if ($user['role'] === self::CLIENT) {
            $status = $user['customer_id'] ? db()->fetchColumn('SELECT status FROM customers WHERE id = ?', [$user['customer_id']]) : null;
            if ($status !== 'active') {
                return [null, 'This customer account has been disabled. Please contact support.'];
            }
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            db()->update('users', ['password_hash' => $user['password_hash']], 'id = :id', ['id' => $user['id']]);
        }
        db()->query('DELETE FROM login_attempts WHERE email = ?', [$email]);
        return [$user, null];
    }
}
