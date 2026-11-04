<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;

final class StaffController extends Controller
{
    public function index(): string
    {
        return $this->view('admin.staff.index', [
            'title' => 'Administrators',
            'staff' => db()->fetchAll("SELECT * FROM users WHERE role IN ('super_admin','admin') ORDER BY role, name"),
        ]);
    }

    /** Create a staff account. */
    public function store(): never
    {
        $data = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|max:190|unique:users,email',
            'role' => 'required|in:admin,super_admin',
            'password' => 'required|min:' . \App\Services\Settings::int('min_password_length') . '|confirmed',
        ]);
        db()->insert('users', [
            'role' => $data['role'],
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        audit('staff.created', 'user', null, ['email' => $data['email'], 'role' => $data['role']]);
        flash('success', 'Administrator account created.');
        redirect('/staff');
    }

    /** Update role / status or set a new password. */
    public function update(int $id): never
    {
        $user = db()->fetch("SELECT * FROM users WHERE id = ? AND role IN ('super_admin','admin')", [$id]);
        if (!$user) {
            abort(404);
        }
        $me = Auth::id() === (int) $user['id'];
        $supers = (int) db()->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active'");
        $data = $this->validate([
            'role' => 'required|in:admin,super_admin',
            'status' => 'required|in:active,disabled',
            'password' => 'min:' . \App\Services\Settings::int('min_password_length') . '|confirmed',
        ]);
        if ($data['role'] === 'admin' && $user['role'] === 'super_admin' && $supers <= 1) {
            back_with_errors(['role' => 'There must be at least one active Super Admin.'], '/staff');
        }
        if ($data['status'] === 'disabled' && ($me || ($user['role'] === 'super_admin' && $supers <= 1))) {
            back_with_errors(['status' => $me ? 'You cannot disable your own account.' : 'There must be at least one active Super Admin.'], '/staff');
        }
        $update = ['role' => $data['role'], 'status' => $data['status'], 'updated_at' => now()];
        if ($data['password'] !== '') {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        db()->update('users', $update, 'id = :id', ['id' => $id]);
        audit('staff.updated', 'user', $id, ['role' => [$user['role'], $data['role']], 'status' => [$user['status'], $data['status']], 'password_reset' => $data['password'] !== '']);
        flash('success', 'Account updated.');
        redirect('/staff');
    }
}
