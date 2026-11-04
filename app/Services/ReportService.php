<?php

declare(strict_types=1);

namespace App\Services;

/** Business metrics used by dashboards and reports. */
final class ReportService
{
    public static function mrr(?string $type = null): float
    {
        $sql = "SELECT price, billing_cycle FROM services WHERE status = 'active'" . ($type ? ' AND type = ?' : '');
        $total = 0.0;
        foreach (db()->fetchAll($sql, $type ? [$type] : []) as $s) {
            $total += BillingCycle::monthlyEquivalent((float) $s['price'], $s['billing_cycle']);
        }
        return round($total, 2);
    }

    public static function counts(): array
    {
        $row = db()->fetch(
            "SELECT
               (SELECT COUNT(*) FROM customers) AS customers,
               (SELECT COUNT(*) FROM customers WHERE status = 'active') AS active_customers,
               (SELECT COUNT(DISTINCT customer_id) FROM services WHERE status IN ('active','suspended')) AS customers_with_services,
               (SELECT COUNT(*) FROM customers WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS new_customers_month,
               (SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_customers_30,
               (SELECT COUNT(*) FROM services WHERE type = 'web' AND status = 'active') AS web_active,
               (SELECT COUNT(*) FROM services WHERE type = 'email' AND status = 'active') AS email_active,
               (SELECT COUNT(*) FROM services WHERE status = 'suspended') AS suspended,
               (SELECT COUNT(*) FROM services WHERE status = 'provisioning_failed') AS provisioning_failed,
               (SELECT COUNT(*) FROM services WHERE status = 'provisioning') AS provisioning,
               (SELECT COUNT(*) FROM services WHERE status = 'pending_payment') AS pending_orders,
               (SELECT COUNT(*) FROM services WHERE sync_status IN ('mismatch','missing') AND status NOT IN ('terminated','cancelled')) AS sync_issues,
               (SELECT COUNT(*) FROM services WHERE termination_flagged_at IS NOT NULL AND status = 'suspended') AS termination_flags,
               (SELECT COUNT(*) FROM invoices WHERE status = 'pending') AS invoices_pending,
               (SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status = 'pending') AS pending_amount,
               (SELECT COUNT(*) FROM invoices WHERE status = 'overdue') AS invoices_overdue,
               (SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status = 'overdue') AS overdue_amount,
               (SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'overdue') AS overdue_customers,
               (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'completed' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS revenue_month,
               (SELECT COUNT(*) FROM payments WHERE status = 'completed' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS payments_month,
               (SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending')) AS tickets_open,
               (SELECT COUNT(*) FROM support_tickets WHERE status = 'open' AND (last_reply_by IS NULL OR last_reply_by = 'client')) AS tickets_awaiting,
               (SELECT COUNT(*) FROM automation_logs WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS automation_failures,
               (SELECT COUNT(*) FROM notifications WHERE status = 'failed') AS notifications_failed"
        );
        return array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $row ?: []);
    }

    /** Payments collected per month for the last N months: [['label' => 'Jan', 'value' => 12.0, 'month' => '2026-01'], ...] */
    public static function monthlyRevenue(int $months = 12): array
    {
        $rows = db()->fetchAll(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, SUM(amount) AS total FROM payments
             WHERE status = 'completed' AND paid_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
             GROUP BY m",
            [$months - 1]
        );
        $map = array_column($rows, 'total', 'm');
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime(date('Y-m-01') . " -{$i} months");
            $key = date('Y-m', $ts);
            $out[] = ['label' => date('M', $ts), 'month' => $key, 'value' => (float) ($map[$key] ?? 0)];
        }
        return $out;
    }

    /** New customers per month. */
    public static function monthlyCustomers(int $months = 12): array
    {
        $rows = db()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM customers
             WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH) GROUP BY m",
            [$months - 1]
        );
        $map = array_column($rows, 'n', 'm');
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime(date('Y-m-01') . " -{$i} months");
            $key = date('Y-m', $ts);
            $n = (int) ($map[$key] ?? 0);
            $out[] = ['label' => date('M', $ts), 'month' => $key, 'value' => $n, 'display' => (string) $n];
        }
        return $out;
    }

    public static function revenueByProduct(): array
    {
        $rows = db()->fetchAll(
            "SELECT p.id, p.name, p.type, COUNT(s.id) AS active_services, GROUP_CONCAT(CONCAT(s.price, ':', s.billing_cycle)) AS prices
             FROM hosting_plans p LEFT JOIN services s ON s.plan_id = p.id AND s.status = 'active'
             GROUP BY p.id, p.name, p.type ORDER BY p.type, p.sort_order, p.name"
        );
        foreach ($rows as &$r) {
            $mrr = 0.0;
            foreach (array_filter(explode(',', (string) $r['prices'])) as $pair) {
                [$price, $cycle] = explode(':', $pair);
                $mrr += BillingCycle::monthlyEquivalent((float) $price, $cycle);
            }
            $r['mrr'] = round($mrr, 2);
        }
        return $rows;
    }

    public static function upcomingRenewals(int $days = 30, int $limit = 50): array
    {
        return db()->fetchAll(
            "SELECT s.*, c.full_name, c.business_name, p.name AS plan_name FROM services s
             JOIN customers c ON c.id = s.customer_id JOIN hosting_plans p ON p.id = s.plan_id
             WHERE s.status IN ('active','suspended') AND s.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY s.next_due_date LIMIT " . (int) $limit,
            [$days]
        );
    }

    public static function overdueBalances(int $limit = 50): array
    {
        return db()->fetchAll(
            "SELECT c.id, c.full_name, c.business_name, c.email, COUNT(i.id) AS invoices, SUM(i.total - i.amount_paid) AS balance, MIN(i.due_date) AS oldest_due
             FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.status = 'overdue'
             GROUP BY c.id, c.full_name, c.business_name, c.email ORDER BY balance DESC LIMIT " . (int) $limit
        );
    }

    public static function recentPayments(int $limit = 6): array
    {
        return db()->fetchAll(
            "SELECT p.*, c.full_name, c.business_name, i.number FROM payments p JOIN customers c ON c.id = p.customer_id JOIN invoices i ON i.id = p.invoice_id
             WHERE p.status = 'completed' ORDER BY p.paid_at DESC LIMIT " . (int) $limit
        );
    }

    public static function lastCronRun(): ?array
    {
        return db()->fetch('SELECT * FROM cron_runs ORDER BY id DESC LIMIT 1');
    }
}
