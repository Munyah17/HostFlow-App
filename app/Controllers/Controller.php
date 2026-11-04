<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts.app'): string
    {
        return view($template, $data, $layout);
    }

    /** Validate POST input; on failure redirect back with errors. Returns the validated subset. */
    protected function validate(array $rules, array $labels = [], ?array $data = null): array
    {
        $data ??= Request::only(array_keys($rules));
        $errors = Validator::make($data, $rules, $labels);
        if ($errors) {
            back_with_errors($errors);
        }
        return $data;
    }

    /** Load a row or 404. */
    protected function findOrFail(string $table, int $id): array
    {
        $row = db()->fetch("SELECT * FROM `{$table}` WHERE id = ?", [$id]);
        if (!$row) {
            abort(404);
        }
        return $row;
    }

    /**
     * Load a row that must belong to the logged-in client's customer account.
     * Returns 404 (not 403) so other customers' record IDs are not disclosed.
     */
    protected function findOwnedOrFail(string $table, int $id): array
    {
        $customerId = Auth::customerId();
        $row = $customerId ? db()->fetch("SELECT * FROM `{$table}` WHERE id = ? AND customer_id = ?", [$id, $customerId]) : null;
        if (!$row) {
            abort(404);
        }
        return $row;
    }
}
