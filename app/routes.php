<?php

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\Client;
use App\Controllers\InstallController;
use App\Controllers\PublicController;
use App\Core\Router;

return function (Router $r): void {
    // Installer (only reachable before installation; InstallController enforces the lock).
    $r->get('/install', [InstallController::class, 'show']);
    $r->post('/install', [InstallController::class, 'install']);

    // Public
    $r->get('/', [PublicController::class, 'home']);
    $r->post('/webhooks/paynow', [PublicController::class, 'paynowWebhook'], ['nocsrf']);
    $r->post('/webhooks/gateway/{gateway}', [PublicController::class, 'gatewayWebhook'], ['nocsrf']);
    $r->get('/cron/run', [PublicController::class, 'webCron']);
    $r->get('/plugin-assets/{plugin}/{file}', [PublicController::class, 'pluginAsset']);

    // Authentication
    $r->group(['middleware' => ['guest']], function (Router $r) {
        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->get('/forgot-password', [AuthController::class, 'showForgot']);
        $r->post('/forgot-password', [AuthController::class, 'forgot']);
        $r->get('/reset-password/{token}', [AuthController::class, 'showReset']);
        $r->post('/reset-password/{token}', [AuthController::class, 'reset']);
    });
    $r->post('/logout', [AuthController::class, 'logout']);

    // Staff (Super Admin + Admin)
    $r->group(['middleware' => ['staff']], function (Router $r) {
        $r->get('/dashboard', [Admin\DashboardController::class, 'index']);

        $r->get('/customers', [Admin\CustomerController::class, 'index']);
        $r->get('/customers/create', [Admin\CustomerController::class, 'create']);
        $r->post('/customers', [Admin\CustomerController::class, 'store']);
        $r->get('/customers/view/{id}', [Admin\CustomerController::class, 'show']);
        $r->get('/customers/edit/{id}', [Admin\CustomerController::class, 'edit']);
        $r->post('/customers/update/{id}', [Admin\CustomerController::class, 'update']);
        $r->post('/customers/{id}/notes', [Admin\CustomerController::class, 'addNote']);
        $r->post('/customers/{id}/status', [Admin\CustomerController::class, 'toggleStatus']);
        $r->post('/customers/{id}/portal-access', [Admin\CustomerController::class, 'portalAccess']);

        $r->get('/hosting', [Admin\ServiceController::class, 'web']);
        $r->get('/email-hosting', [Admin\ServiceController::class, 'email']);
        $r->get('/hosting/create', [Admin\ServiceController::class, 'create']);
        $r->post('/hosting', [Admin\ServiceController::class, 'store']);
        $r->get('/hosting/view/{id}', [Admin\ServiceController::class, 'show']);
        $r->post('/hosting/update/{id}', [Admin\ServiceController::class, 'update']);
        $r->post('/hosting/{id}/provision', [Admin\ServiceController::class, 'provision']);
        $r->post('/hosting/{id}/suspend', [Admin\ServiceController::class, 'suspend']);
        $r->post('/hosting/{id}/unsuspend', [Admin\ServiceController::class, 'unsuspend']);
        $r->post('/hosting/{id}/change-plan', [Admin\ServiceController::class, 'changePlan']);
        $r->post('/hosting/{id}/sync', [Admin\ServiceController::class, 'sync']);
        $r->post('/hosting/{id}/link', [Admin\ServiceController::class, 'link']);
        $r->post('/hosting/{id}/login', [Admin\ServiceController::class, 'login']);
        $r->post('/hosting/{id}/renew', [Admin\ServiceController::class, 'renew']);
        $r->post('/hosting/{id}/cancel', [Admin\ServiceController::class, 'cancel']);
        $r->post('/hosting/{id}/terminate', [Admin\ServiceController::class, 'terminate'], ['super']);

        $r->get('/domains', [Admin\DomainController::class, 'index']);
        $r->get('/domains/create', [Admin\DomainController::class, 'create']);
        $r->post('/domains', [Admin\DomainController::class, 'store']);
        $r->get('/domains/view/{id}', [Admin\DomainController::class, 'show']);
        $r->post('/domains/update/{id}', [Admin\DomainController::class, 'update']);
        $r->post('/domains/{id}/register', [Admin\DomainController::class, 'register']);
        $r->post('/domains/{id}/renew', [Admin\DomainController::class, 'renew']);
        $r->post('/domains/{id}/status', [Admin\DomainController::class, 'setStatus']);
        $r->get('/domains/pricing', [Admin\DomainController::class, 'pricing']);
        $r->post('/domains/pricing', [Admin\DomainController::class, 'pricingStore'], ['super']);
        $r->post('/domains/pricing/{id}/update', [Admin\DomainController::class, 'pricingUpdate'], ['super']);
        $r->post('/domains/pricing/{id}/delete', [Admin\DomainController::class, 'pricingDelete'], ['super']);

        $r->get('/hosting/plans', [Admin\PlanController::class, 'index']);
        $r->get('/hosting/plans/create', [Admin\PlanController::class, 'create'], ['super']);
        $r->post('/hosting/plans', [Admin\PlanController::class, 'store'], ['super']);
        $r->get('/hosting/plans/edit/{id}', [Admin\PlanController::class, 'edit'], ['super']);
        $r->post('/hosting/plans/update/{id}', [Admin\PlanController::class, 'update'], ['super']);

        $r->get('/billing/invoices', [Admin\InvoiceController::class, 'index']);
        $r->get('/billing/invoices/create', [Admin\InvoiceController::class, 'create']);
        $r->post('/billing/invoices', [Admin\InvoiceController::class, 'store']);
        $r->get('/billing/invoices/view/{id}', [Admin\InvoiceController::class, 'show']);
        $r->get('/billing/invoices/{id}/pdf', [Admin\InvoiceController::class, 'pdf']);
        $r->post('/billing/invoices/{id}/publish', [Admin\InvoiceController::class, 'publish']);
        $r->post('/billing/invoices/{id}/cancel', [Admin\InvoiceController::class, 'cancel']);
        $r->post('/billing/invoices/{id}/send', [Admin\InvoiceController::class, 'send']);
        $r->post('/billing/invoices/{id}/due-date', [Admin\InvoiceController::class, 'dueDate']);

        $r->get('/payments', [Admin\PaymentController::class, 'index']);
        $r->post('/payments/manual', [Admin\PaymentController::class, 'manual']);
        $r->post('/payments/{id}/verify', [Admin\PaymentController::class, 'verify']);

        $r->get('/automation', [Admin\AutomationController::class, 'index']);
        $r->get('/automation/runs/{id}', [Admin\AutomationController::class, 'run']);
        $r->post('/automation/run', [Admin\AutomationController::class, 'runNow'], ['super']);
        $r->post('/automation/notifications/{id}/retry', [Admin\AutomationController::class, 'retryNotification']);

        $r->get('/support', [Admin\SupportController::class, 'index']);
        $r->get('/support/create', [Admin\SupportController::class, 'create']);
        $r->post('/support', [Admin\SupportController::class, 'store']);
        $r->get('/support/tickets/{id}', [Admin\SupportController::class, 'show']);
        $r->post('/support/tickets/{id}/reply', [Admin\SupportController::class, 'reply']);
        $r->post('/support/tickets/{id}/update', [Admin\SupportController::class, 'update']);

        $r->get('/reports', [Admin\ReportController::class, 'index']);
    });

    // Super Admin only
    $r->group(['middleware' => ['super']], function (Router $r) {
        $r->get('/integrations', [Admin\IntegrationController::class, 'index']);
        $r->get('/integrations/providers/create', [Admin\IntegrationController::class, 'create']);
        $r->post('/integrations/providers', [Admin\IntegrationController::class, 'store']);
        $r->get('/integrations/providers/edit/{id}', [Admin\IntegrationController::class, 'edit']);
        $r->post('/integrations/providers/update/{id}', [Admin\IntegrationController::class, 'update']);
        $r->post('/integrations/providers/{id}/test', [Admin\IntegrationController::class, 'test']);
        $r->get('/integrations/providers/{id}/accounts', [Admin\IntegrationController::class, 'accounts']);
        $r->post('/integrations/providers/{id}/sync', [Admin\IntegrationController::class, 'sync']);
        $r->post('/integrations/providers/{id}/import', [Admin\IntegrationController::class, 'import']);

        $r->get('/staff', [Admin\StaffController::class, 'index']);
        $r->post('/staff', [Admin\StaffController::class, 'store']);
        $r->post('/staff/{id}/update', [Admin\StaffController::class, 'update']);

        $r->get('/activity-log', [Admin\AuditController::class, 'index']);

        $r->get('/settings', [Admin\SettingsController::class, 'index']);
        $r->get('/settings/plugins', [Admin\PluginController::class, 'index']);
        $r->post('/settings/plugins/upload', [Admin\PluginController::class, 'upload']);
        $r->get('/settings/plugins/{id}/configure', [Admin\PluginController::class, 'configure']);
        $r->post('/settings/plugins/{id}/configure', [Admin\PluginController::class, 'saveConfig']);
        $r->post('/settings/plugins/{id}/{action}', [Admin\PluginController::class, 'action']);
        $r->get('/settings/system', [Admin\SystemController::class, 'index']);
        $r->post('/settings/system/migrate', [Admin\SystemController::class, 'migrate']);
        $r->post('/settings/system/backup', [Admin\SystemController::class, 'backup']);
        $r->get('/settings/{section}', [Admin\SettingsController::class, 'index']);
        $r->post('/settings/email/test', [Admin\SettingsController::class, 'testEmail']);
        $r->post('/settings/{section}', [Admin\SettingsController::class, 'save']);
    });

    // Client portal
    $r->group(['middleware' => ['client'], 'prefix' => '/client'], function (Router $r) {
        $r->get('/', [Client\DashboardController::class, 'index']);
        $r->get('/services', [Client\ServiceController::class, 'web']);
        $r->get('/email', [Client\ServiceController::class, 'email']);
        $r->get('/services/{id}', [Client\ServiceController::class, 'show']);
        $r->post('/services/{id}/renew', [Client\ServiceController::class, 'renew']);
        $r->post('/services/{id}/change-plan', [Client\ServiceController::class, 'changePlan']);
        $r->post('/services/{id}/login', [Client\ServiceController::class, 'login']);
        $r->get('/domains', [Client\DomainController::class, 'index']);
        $r->get('/domains/order', [Client\DomainController::class, 'order']);
        $r->post('/domains/order', [Client\DomainController::class, 'store']);
        $r->get('/domains/{id}', [Client\DomainController::class, 'show']);
        $r->post('/domains/{id}/renew', [Client\DomainController::class, 'renew']);
        $r->get('/order', [Client\OrderController::class, 'index']);
        $r->post('/order', [Client\OrderController::class, 'store']);
        $r->get('/invoices', [Client\InvoiceController::class, 'index']);
        $r->get('/invoices/{id}', [Client\InvoiceController::class, 'show']);
        $r->get('/invoices/{id}/pdf', [Client\InvoiceController::class, 'pdf']);
        $r->post('/invoices/{id}/pay', [Client\InvoiceController::class, 'pay']);
        $r->get('/payments', [Client\PaymentController::class, 'index']);
        $r->get('/payments/{reference}/return', [Client\PaymentController::class, 'returned']);
        $r->get('/payments/{reference}/status', [Client\PaymentController::class, 'status']);
        $r->get('/support', [Client\SupportController::class, 'index']);
        $r->get('/support/create', [Client\SupportController::class, 'create']);
        $r->post('/support', [Client\SupportController::class, 'store']);
        $r->get('/support/{id}', [Client\SupportController::class, 'show']);
        $r->post('/support/{id}/reply', [Client\SupportController::class, 'reply']);
        $r->get('/profile', [Client\ProfileController::class, 'index']);
        $r->post('/profile', [Client\ProfileController::class, 'update']);
        $r->post('/profile/password', [Client\ProfileController::class, 'password']);
    });
};
