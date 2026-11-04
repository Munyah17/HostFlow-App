<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Services\Notifications\NotificationService;
use App\Services\Settings;

final class AuthController extends Controller
{
    public function showLogin(): string
    {
        return $this->view('auth.login', ['title' => 'Sign in'], 'layouts.auth');
    }

    public function login(): never
    {
        $email = (string) Request::input('email', '');
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            back_with_errors(['email' => 'Enter your email address and password.'], '/login');
        }
        [$user, $error] = Auth::attempt($email, $password);
        if (!$user) {
            Session::withInput(['email' => $email]);
            flash('error', $error);
            redirect('/login');
        }
        Auth::login($user);
        audit('auth.login', 'user', (int) $user['id']);
        $intended = (string) Session::get('_intended', '');
        Session::forget('_intended');
        $home = Auth::home();
        // Only follow the intended URL if it belongs to the user's portal.
        if ($intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            $isClientPath = str_starts_with($intended, '/client');
            if ($isClientPath === Auth::isClient()) {
                header('Location: ' . url($intended));
                exit;
            }
        }
        redirect($home);
    }

    public function logout(): never
    {
        if (Auth::check()) {
            audit('auth.logout', 'user', Auth::id());
        }
        Auth::logout();
        Session::start();
        flash('success', 'You have been signed out.');
        redirect('/login');
    }

    public function showRegister(): string
    {
        if (!Settings::bool('allow_registration')) {
            flash('warning', 'Online registration is currently disabled. Please contact us to open an account.');
            redirect('/login');
        }
        return $this->view('auth.register', ['title' => 'Create account'], 'layouts.auth');
    }

    public function register(): never
    {
        if (!Settings::bool('allow_registration')) {
            abort(403);
        }
        $min = max(8, Settings::int('min_password_length'));
        $data = Request::only(['full_name', 'business_name', 'email', 'phone', 'address', 'city', 'country']);
        $data['email'] = strtolower((string) $data['email']);
        $data['password'] = (string) ($_POST['password'] ?? '');
        $data['password_confirmation'] = (string) ($_POST['password_confirmation'] ?? '');
        $this->validate([
            'full_name' => 'required|max:150',
            'business_name' => 'max:150',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|max:40|regex:/^[0-9+()\-\s]{7,40}$/',
            'address' => 'max:255',
            'city' => 'max:100',
            'country' => 'required|max:100',
            'password' => "required|min:{$min}|confirmed",
        ], [], $data);
        if (db()->fetchColumn('SELECT id FROM customers WHERE email = ?', [$data['email']])) {
            back_with_errors(['email' => 'An account with this email already exists. Please sign in or reset your password.'], '/register');
        }
        $userId = db()->transaction(function ($db) use ($data) {
            $customerId = $db->insert('customers', [
                'full_name' => $data['full_name'],
                'business_name' => $data['business_name'] ?: null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?: null,
                'city' => $data['city'] ?: null,
                'country' => $data['country'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $db->insert('users', [
                'role' => Auth::CLIENT,
                'customer_id' => $customerId,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $user = db()->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        Auth::login($user);
        audit('customer.registered', 'customer', (int) $user['customer_id']);
        NotificationService::notifyCustomer((int) $user['customer_id'], 'welcome', [], 'welcome:' . $user['customer_id']);
        flash('success', 'Welcome! Your account has been created. You can now order hosting.');
        redirect('/client');
    }

    public function showForgot(): string
    {
        return $this->view('auth.forgot', ['title' => 'Forgot password'], 'layouts.auth');
    }

    public function forgot(): never
    {
        $email = strtolower((string) Request::input('email', ''));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? db()->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
        $recent = $user ? (int) db()->fetchColumn('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [$user['id']]) : 0;
        if ($user && $recent < 3) {
            $token = bin2hex(random_bytes(32));
            db()->insert('password_resets', [
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => now(),
            ]);
            NotificationService::notifyEmail($user['email'], 'password_reset', ['name' => $user['name'], 'reset_url' => absolute_url('/reset-password/' . $token)], null, true, $user['customer_id'] ? (int) $user['customer_id'] : null);
            audit('auth.password_reset_requested', 'user', (int) $user['id']);
        }
        // Same response whether or not the account exists (prevents account enumeration).
        flash('success', 'If an account exists for that email address, a password reset link has been sent.');
        redirect('/login');
    }

    private function resetRow(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return db()->fetch('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)]);
    }

    public function showReset(string|int $token): string
    {
        if (!$this->resetRow((string) $token)) {
            flash('error', 'This password reset link is invalid or has expired.');
            redirect('/forgot-password');
        }
        return $this->view('auth.reset', ['title' => 'Reset password', 'token' => (string) $token], 'layouts.auth');
    }

    public function reset(string|int $token): never
    {
        $token = (string) $token;
        $row = $this->resetRow($token);
        if (!$row) {
            flash('error', 'This password reset link is invalid or has expired.');
            redirect('/forgot-password');
        }
        $min = max(8, Settings::int('min_password_length'));
        $data = ['password' => (string) ($_POST['password'] ?? ''), 'password_confirmation' => (string) ($_POST['password_confirmation'] ?? '')];
        $this->validate(['password' => "required|min:{$min}|confirmed"], [], $data);
        db()->transaction(function ($db) use ($row, $data) {
            $db->update('users', ['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT), 'updated_at' => now()], 'id = :id', ['id' => $row['user_id']]);
            $db->update('password_resets', ['used_at' => now()], 'user_id = :uid AND used_at IS NULL', ['uid' => $row['user_id']]);
        });
        audit('auth.password_reset', 'user', (int) $row['user_id']);
        flash('success', 'Your password has been reset. Please sign in.');
        redirect('/login');
    }
}
