<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Services\AutomationService;
use App\Services\BillingCycle;
use App\Services\BillingService;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\GatewayRegistry;
use App\Services\PaymentService;
use App\Services\Settings;

final class PublicController extends Controller
{
    /** Public storefront: plan catalogue with guest checkout links. */
    public function home(): string
    {
        if (Auth::check()) {
            redirect(Auth::home());
        }
        if (!hostflow_installed() || !Database::isConnected()) {
            redirect('/install');
        }
        try {
            $plans = db()->fetchAll("SELECT * FROM hosting_plans WHERE status = 'active' ORDER BY type, sort_order, name");
        } catch (\PDOException) {
            $plans = [];
        }
        return $this->view('public.home', ['title' => 'Hosting plans', 'plans' => $plans], 'layouts.public');
    }

    /** Guest order page for a specific plan. Logged-in clients order from their portal instead. */
    public function showOrder(string|int $id): string
    {
        if (Auth::check()) {
            redirect(Auth::isClient() ? '/client/order' : '/dashboard');
        }
        if (!Settings::bool('allow_registration')) {
            flash('info', 'Online ordering is currently disabled. Please contact us to place an order.');
            redirect('/login');
        }
        return $this->view('public.order', ['title' => 'Order hosting', 'plan' => $this->plan($id)], 'layouts.public');
    }

    /**
     * Guest checkout: create the customer + portal account, the service order and its
     * invoice in one step, then sign the customer in and send them to pay.
     * The whole "choose plan → invoice → pay → cPanel" pipeline runs from here.
     */
    public function order(string|int $id): never
    {
        $plan = $this->plan($id);
        if (!Settings::bool('allow_registration')) {
            abort(403);
        }
        $back = '/order/' . (int) $plan['id'];
        $min = max(8, Settings::int('min_password_length'));
        $data = Request::only(['full_name', 'business_name', 'email', 'phone', 'country', 'domain', 'billing_cycle']);
        $data['email'] = strtolower((string) $data['email']);
        $data['domain'] = strtolower((string) $data['domain']);
        $data['password'] = (string) ($_POST['password'] ?? '');
        $data['password_confirmation'] = (string) ($_POST['password_confirmation'] ?? '');
        $this->validate([
            'domain' => 'required|domain',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,annual',
            'full_name' => 'required|max:150',
            'business_name' => 'max:150',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|max:40|regex:/^[0-9+()\-\s]{7,40}$/',
            'country' => 'required|max:100',
            'password' => "required|min:{$min}|confirmed",
        ], [], $data);
        if (BillingCycle::planPrice($plan, (string) $data['billing_cycle']) === null) {
            back_with_errors(['billing_cycle' => 'The selected plan is not offered on that billing cycle.'], $back);
        }
        if (db()->fetchColumn('SELECT id FROM customers WHERE email = ?', [$data['email']])) {
            back_with_errors(['email' => 'An account with this email already exists. Please sign in to order.'], $back);
        }
        if (db()->fetchColumn("SELECT COUNT(*) FROM services WHERE domain = ? AND type = ? AND status NOT IN ('terminated','cancelled')", [$data['domain'], $plan['type']])) {
            back_with_errors(['domain' => 'A hosting service for ' . $data['domain'] . ' already exists.'], $back);
        }
        [$customerId, $userId] = db()->transaction(function ($db) use ($data) {
            $cid = $db->insert('customers', [
                'full_name' => $data['full_name'],
                'business_name' => $data['business_name'] ?: null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $uid = $db->insert('users', [
                'role' => Auth::CLIENT,
                'customer_id' => $cid,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return [$cid, $uid];
        });
        try {
            $r = BillingService::createOrder($customerId, (int) $plan['id'], (string) $data['domain'], (string) $data['billing_cycle']);
        } catch (\Throwable $e) {
            Logger::error('Public order failed after account creation: ' . $e->getMessage(), ['email' => $data['email']], 'billing');
            flash('warning', 'Your account was created but the order could not be completed: ' . $e->getMessage() . ' Please sign in and try again.');
            redirect('/login');
        }
        $user = db()->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        Auth::login($user);
        audit('public.order_created', 'service', $r['service_id'], ['domain' => $data['domain'], 'plan_id' => $plan['id']]);
        NotificationService::notifyCustomer($customerId, 'welcome', [], 'welcome:' . $customerId);
        flash('success', 'Welcome! Your account and invoice are ready — pay to activate your hosting instantly.');
        redirect('/client/invoices/' . $r['invoice_id']);
    }

    private function plan(string|int $id): array
    {
        $plan = db()->fetch("SELECT * FROM hosting_plans WHERE id = ? AND status = 'active'", [(int) $id]);
        if (!$plan) {
            abort(404);
        }
        return $plan;
    }

    /** Paynow status update (resulturl). The hash is verified, then the status is confirmed by polling. */
    public function paynowWebhook(): string
    {
        return $this->handleWebhook('paynow');
    }

    public function gatewayWebhook(string $gateway): string
    {
        if ($gateway === 'paynow') {
            return $this->handleWebhook('paynow');
        }
        return $this->handleWebhook($gateway);
    }

    private function handleWebhook(string $gatewayId): string
    {
        $gateway = GatewayRegistry::get($gatewayId);
        if (!$gateway) {
            http_response_code(404);
            return 'Unknown gateway';
        }
        $reference = $gateway->parseWebhook($_POST);
        if ($reference === 'ignore') {
            return 'OK'; // recognised event that needs no action (e.g. non-checkout Stripe events)
        }
        if (!$reference) {
            http_response_code(400);
            return 'Invalid notification';
        }
        $payment = db()->fetch('SELECT * FROM payments WHERE reference = ? AND gateway = ?', [$reference, $gatewayId]);
        if (!$payment) {
            Logger::error('Webhook for unknown payment', ['gateway' => $gatewayId, 'reference' => $reference], 'payments');
            http_response_code(404);
            return 'Unknown payment';
        }
        try {
            PaymentService::verify((int) $payment['id']);
        } catch (\Throwable $e) {
            Logger::error('Webhook processing failed: ' . $e->getMessage(), ['reference' => $reference], 'payments');
            http_response_code(500);
            return 'Error';
        }
        return 'OK';
    }

    /** Optional web-triggered cron for hosts without CLI cron (requires CRON_WEB_TOKEN). */
    public function webCron(): string
    {
        $token = (string) config('cron_web_token');
        $given = (string) ($_GET['token'] ?? '');
        if ($token === '' || strlen($token) < 24 || !hash_equals($token, $given)) {
            abort(404);
        }
        @set_time_limit(300);
        $result = AutomationService::run();
        header('Content-Type: text/plain');
        return $result === null ? "Another automation run is in progress.\n" : 'Run #' . $result['run_id'] . ': ' . $result['status'] . "\n";
    }
}
