<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\Hosting\ProviderRegistry;
use App\Services\ProvisioningService;
use App\Services\SyncService;

final class IntegrationController extends Controller
{
    private const RULES = [
        'name' => 'required|max:120',
        'type' => 'required|max:50',
        'hostname' => 'required|max:190',
        'port' => 'required|integer|min:1|max:65535',
        'username' => 'required|max:100',
        'nameservers' => 'max:255',
        'status' => 'required|in:active,disabled',
    ];

    public function index(): string
    {
        return $this->view('admin.integrations.index', [
            'title' => 'Integrations',
            'providers' => db()->fetchAll(
                'SELECT hp.*, (SELECT COUNT(*) FROM services s WHERE s.provider_id = hp.id AND s.status NOT IN (\'terminated\',\'cancelled\')) AS services
                 FROM hosting_providers hp ORDER BY hp.name'
            ),
            'types' => ProviderRegistry::types(),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin.integrations.form', ['title' => 'Add hosting server', 'provider' => null, 'types' => ProviderRegistry::types()]);
    }

    public function store(): never
    {
        $data = $this->validate(self::RULES);
        if (!isset(ProviderRegistry::types()[$data['type']])) {
            back_with_errors(['type' => 'Unknown provider type.'], '/integrations/providers/create');
        }
        $token = trim((string) Request::input('api_token', ''));
        $id = db()->insert('hosting_providers', $this->fields($data, $token) + ['created_at' => now(), 'updated_at' => now()]);
        audit('provider.created', 'provider', $id, ['name' => $data['name'], 'type' => $data['type'], 'hostname' => $data['hostname']]);
        flash('success', 'Server added. Test the connection before mapping plans to it.');
        redirect('/integrations');
    }

    public function edit(int $id): string
    {
        return $this->view('admin.integrations.form', ['title' => 'Edit hosting server', 'provider' => $this->findOrFail('hosting_providers', $id), 'types' => ProviderRegistry::types()]);
    }

    public function update(int $id): never
    {
        $provider = $this->findOrFail('hosting_providers', $id);
        $data = $this->validate(self::RULES);
        if (!isset(ProviderRegistry::types()[$data['type']])) {
            back_with_errors(['type' => 'Unknown provider type.'], '/integrations/providers/edit/' . $id);
        }
        $token = trim((string) Request::input('api_token', ''));
        db()->update('hosting_providers', $this->fields($data, $token !== '' ? $token : $provider['api_token']) + ['updated_at' => now()], 'id = :id', ['id' => $id]);
        audit('provider.updated', 'provider', $id, ['name' => $data['name'], 'status' => $data['status']]);
        flash('success', 'Server saved.');
        redirect('/integrations');
    }

    /** Test API connectivity and store the result on the provider row. */
    public function test(int $id): never
    {
        $provider = $this->findOrFail('hosting_providers', $id);
        try {
            $r = ProviderRegistry::make($provider)->testConnection();
            db()->update('hosting_providers', [
                'last_checked_at' => now(),
                'last_check_ok' => $r->success ? 1 : 0,
                'last_check_message' => mb_substr($r->message, 0, 500),
            ], 'id = :id', ['id' => $id]);
            audit('provider.tested', 'provider', $id, ['ok' => $r->success, 'message' => $r->message]);
            flash($r->success ? 'success' : 'error', $r->message);
        } catch (\Throwable $e) {
            db()->update('hosting_providers', ['last_checked_at' => now(), 'last_check_ok' => 0, 'last_check_message' => mb_substr($e->getMessage(), 0, 500)], 'id = :id', ['id' => $id]);
            flash('error', 'Connection test failed: ' . $e->getMessage());
        }
        redirect('/integrations');
    }

    /** List accounts that exist on the server, flagging ones not managed by HostFlow. */
    public function accounts(int $id): string
    {
        $provider = $this->findOrFail('hosting_providers', $id);
        $accounts = [];
        $error = null;
        try {
            $r = ProviderRegistry::make($provider)->listAccounts();
            if ($r->success) {
                $accounts = $r->data;
            } else {
                $error = $r->message;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $linked = [];
        foreach (db()->fetchAll('SELECT provider_username, id, domain, status FROM services WHERE provider_id = ? AND provider_username IS NOT NULL', [$id]) as $row) {
            $linked[strtolower($row['provider_username'])] = $row;
        }
        return $this->view('admin.integrations.accounts', [
            'title' => $provider['name'] . ' · server accounts',
            'provider' => $provider,
            'accounts' => $accounts,
            'linked' => $linked,
            'error' => $error,
            'customers' => db()->fetchAll('SELECT id, full_name, business_name FROM customers ORDER BY business_name, full_name'),
            'plans' => db()->fetchAll("SELECT id, name, type FROM hosting_plans WHERE status IN ('active','hidden') AND provider_id = ? ORDER BY type, name", [$id]),
        ]);
    }

    /** Reconcile HostFlow services with the server. */
    public function sync(int $id): never
    {
        $provider = $this->findOrFail('hosting_providers', $id);
        try {
            $summary = SyncService::syncProvider($provider);
            audit('provider.synced', 'provider', $id, ['checked' => $summary['checked'], 'issues' => $summary['issues'], 'unmanaged' => count($summary['unmanaged'])]);
            flash('success', "Sync complete: {$summary['checked']} checked, {$summary['in_sync']} in sync, {$summary['issues']} issue(s), " . count($summary['unmanaged']) . ' unmanaged account(s).');
        } catch (\Throwable $e) {
            flash('error', 'Sync failed: ' . $e->getMessage());
        }
        redirect('/integrations/providers/' . $id . '/accounts');
    }

    /** Import an unmanaged server account as a HostFlow service for a customer. */
    public function import(int $id): never
    {
        $this->findOrFail('hosting_providers', $id);
        $data = $this->validate([
            'username' => 'required|max:64',
            'customer_id' => 'required|integer|exists:customers,id',
            'plan_id' => 'required|integer|exists:hosting_plans,id',
        ]);
        try {
            $plan = db()->fetch('SELECT * FROM hosting_plans WHERE id = ?', [(int) $data['plan_id']]);
            $acct = ProviderRegistry::make(db()->fetch('SELECT * FROM hosting_providers WHERE id = ?', [$id]))->getAccount($data['username']);
            if (!$acct->success) {
                throw new \InvalidArgumentException($acct->message);
            }
            $domain = strtolower((string) ($acct->data['domain'] ?? ''));
            if ($domain === '') {
                throw new \InvalidArgumentException('The server did not return a domain for this account.');
            }
            if (db()->fetchColumn("SELECT COUNT(*) FROM services WHERE domain = ? AND type = ? AND status NOT IN ('terminated','cancelled')", [$domain, $plan['type']])) {
                throw new \InvalidArgumentException("A {$plan['type']} service for {$domain} already exists in HostFlow.");
            }
            $price = \App\Services\BillingCycle::planPrice($plan, $plan['default_cycle']) ?? 0.0;
            $serviceId = db()->insert('services', [
                'customer_id' => (int) $data['customer_id'],
                'plan_id' => (int) $data['plan_id'],
                'type' => $plan['type'],
                'domain' => $domain,
                'provider_id' => $id,
                'billing_cycle' => $plan['default_cycle'],
                'price' => $price,
                'status' => 'provisioning',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $r = ProvisioningService::linkAccount($serviceId, $id, $data['username']);
            if (!$r['success']) {
                throw new \InvalidArgumentException($r['message']);
            }
            audit('service.imported', 'service', $serviceId, ['provider_id' => $id, 'username' => $data['username'], 'domain' => $domain]);
            flash('success', "Account '{$data['username']}' ({$domain}) imported and linked.");
            redirect('/hosting/view/' . $serviceId);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            flash('error', 'Import failed: ' . $e->getMessage());
        }
        redirect('/integrations/providers/' . $id . '/accounts');
    }

    private function fields(array $d, ?string $token): array
    {
        return [
            'name' => $d['name'],
            'type' => $d['type'],
            'hostname' => strtolower(trim($d['hostname'])),
            'port' => (int) $d['port'],
            'username' => $d['username'],
            'api_token' => $token,
            'verify_ssl' => !empty($_POST['verify_ssl']) ? 1 : 0,
            'nameservers' => $d['nameservers'] ?: null,
            'status' => $d['status'],
        ];
    }
}
