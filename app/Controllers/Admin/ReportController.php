<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function index(): string
    {
        $days = max(7, min(120, (int) Request::query('days', 30)));
        return $this->view('admin.reports.index', [
            'title' => 'Reports',
            'days' => $days,
            'c' => ReportService::counts(),
            'expenses' => ReportService::expenses(),
            'mrr' => ['total' => ReportService::mrr(), 'web' => ReportService::mrr('web'), 'email' => ReportService::mrr('email')],
            'revenue' => ReportService::monthlyRevenue(12),
            'newCustomers' => ReportService::monthlyCustomers(12),
            'byProduct' => ReportService::revenueByProduct(),
            'renewals' => ReportService::upcomingRenewals($days),
            'overdue' => ReportService::overdueBalances(),
        ]);
    }
}
