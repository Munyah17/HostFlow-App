<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Services\Settings;

/**
 * Simple business expense ledger — reseller fees, domain purchases, software,
 * salaries and other operating costs. Deliberately a ledger, not an ERP.
 */
final class ExpenseController extends Controller
{
    public const CATEGORIES = [
        'Reseller hosting',
        'Domain registration',
        'Software & licenses',
        'Payment gateway fees',
        'Marketing',
        'Staff & contractors',
        'Office & utilities',
        'Other',
    ];

    public function index(): string
    {
        $month = (string) Request::query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        try {
            $expenses = db()->fetchAll("SELECT e.*, u.name AS created_by_name FROM expenses e LEFT JOIN users u ON u.id = e.created_by WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = ? ORDER BY e.expense_date DESC, e.id DESC", [$month]);
            $recurring = (float) db()->fetchColumn('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE recurring = 1');
            $last30 = (float) db()->fetchColumn('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
        } catch (\PDOException) {
            flash('error', 'The expenses table is missing — run the database update under Settings → System.');
            redirect('/settings/system');
        }
        $total = 0.0;
        $byCategory = [];
        foreach ($expenses as $e) {
            $total += (float) $e['amount'];
            $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + (float) $e['amount'];
        }
        arsort($byCategory);
        return $this->view('admin.expenses.index', [
            'title' => 'Expenses',
            'month' => $month,
            'expenses' => $expenses,
            'total' => $total,
            'last30' => $last30,
            'recurring' => $recurring,
            'byCategory' => $byCategory,
            'categories' => self::CATEGORIES,
            'currency' => (string) Settings::get('currency', 'USD'),
            'canDelete' => Auth::role() === Auth::SUPER_ADMIN,
        ]);
    }

    public function store(): never
    {
        $data = $this->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'vendor' => 'max:150',
            'description' => 'required|max:255',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'notes' => 'max:1000',
        ]);
        $id = db()->insert('expenses', [
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'vendor' => trim((string) $data['vendor']) !== '' ? trim((string) $data['vendor']) : null,
            'description' => trim((string) $data['description']),
            'amount' => round((float) $data['amount'], 2),
            'currency' => (string) Settings::get('currency', 'USD'),
            'recurring' => !empty($_POST['recurring']) ? 1 : 0,
            'notes' => trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        audit('expense.created', 'expense', $id, ['category' => $data['category'], 'amount' => $data['amount']]);
        flash('success', 'Expense recorded.');
        redirect('/expenses?month=' . substr((string) $data['expense_date'], 0, 7));
    }

    public function delete(int $id): never
    {
        $row = $this->findOrFail('expenses', $id);
        db()->query('DELETE FROM expenses WHERE id = ?', [$id]);
        audit('expense.deleted', 'expense', $id, ['description' => $row['description'], 'amount' => $row['amount']]);
        flash('success', 'Expense deleted.');
        redirect('/expenses?month=' . substr((string) $row['expense_date'], 0, 7));
    }
}
