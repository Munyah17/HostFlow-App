<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\Settings;

final class ProfileController extends Controller
{
    public function index(): string
    {
        $cid = Auth::customerId();
        return $this->view('client.profile.index', [
            'title' => 'My profile',
            'customer' => db()->fetch('SELECT * FROM customers WHERE id = ?', [$cid]),
            'user' => db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$cid]),
        ]);
    }

    /** Update contact details. Email changes sync the login account too. */
    public function update(): never
    {
        $customer = db()->fetch('SELECT * FROM customers WHERE id = ?', [Auth::customerId()]);
        if (!$customer) {
            abort(404);
        }
        $data = $this->validate([
            'full_name' => 'required|max:150',
            'business_name' => 'max:150',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
            'address' => 'max:255',
            'city' => 'max:100',
            'country' => 'required|max:100',
        ]);
        $data['email'] = strtolower($data['email']);
        $cid = (int) $customer['id'];
        if (db()->fetchColumn('SELECT COUNT(*) FROM customers WHERE email = ? AND id <> ?', [$data['email'], $cid])) {
            back_with_errors(['email' => 'This email address is already in use.'], '/client/profile');
        }
        $user = db()->fetch('SELECT * FROM users WHERE customer_id = ?', [$cid]);
        if ($user && $data['email'] !== $user['email'] && db()->fetchColumn('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], $user['id']])) {
            back_with_errors(['email' => 'This email address is already in use.'], '/client/profile');
        }
        db()->transaction(function ($db) use ($cid, $data, $user) {
            $db->update('customers', array_map(fn ($v) => $v === '' ? null : $v, $data) + ['updated_at' => now()], 'id = :id', ['id' => $cid]);
            if ($user) {
                $db->update('users', ['email' => $data['email'], 'name' => $data['full_name'], 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
            }
        });
        audit('client.profile_updated', 'customer', $cid);
        flash('success', 'Profile saved.');
        redirect('/client/profile');
    }

    /** Change the portal password (requires the current one). */
    public function password(): never
    {
        $user = db()->fetch('SELECT * FROM users WHERE customer_id = ?', [Auth::customerId()]);
        if (!$user) {
            abort(404);
        }
        $data = $this->validate([
            'current_password' => 'required',
            'password' => 'required|min:' . Settings::int('min_password_length') . '|confirmed',
        ], ['current_password' => 'Current password', 'password' => 'New password']);
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            back_with_errors(['current_password' => 'The current password is incorrect.'], '/client/profile');
        }
        db()->update('users', ['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT), 'updated_at' => now()], 'id = :id', ['id' => $user['id']]);
        audit('client.password_changed', 'user', (int) $user['id']);
        flash('success', 'Password changed.');
        redirect('/client/profile');
    }
}
