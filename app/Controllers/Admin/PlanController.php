<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;

final class PlanController extends Controller
{
    private const RULES = [
        'type' => 'required|in:web,email',
        'name' => 'required|max:120',
        'description' => 'max:2000',
        'price_monthly' => 'numeric|min:0|max:999999',
        'price_quarterly' => 'numeric|min:0|max:999999',
        'price_semiannual' => 'numeric|min:0|max:999999',
        'price_annual' => 'numeric|min:0|max:999999',
        'setup_fee' => 'numeric|min:0|max:999999',
        'default_cycle' => 'required|in:monthly,quarterly,semiannual,annual',
        'storage_gb' => 'numeric|min:0|max:999999',
        'bandwidth_gb' => 'numeric|min:0|max:999999',
        'domains_allowed' => 'integer|min:0|max:999999',
        'email_accounts' => 'integer|min:0|max:999999',
        'databases_allowed' => 'integer|min:0|max:999999',
        'features' => 'max:5000',
        'provider_id' => 'integer|exists:hosting_providers,id',
        'provider_package' => 'max:150',
        'status' => 'required|in:active,hidden,retired',
        'sort_order' => 'integer|min:0|max:9999',
    ];

    public function index(): string
    {
        return $this->view('admin.plans.index', [
            'title' => 'Plans & pricing',
            'plans' => db()->fetchAll(
                'SELECT p.*, hp.name AS provider_name,
                        (SELECT COUNT(*) FROM services s WHERE s.plan_id = p.id AND s.status NOT IN (\'terminated\',\'cancelled\')) AS services
                 FROM hosting_plans p LEFT JOIN hosting_providers hp ON hp.id = p.provider_id
                 ORDER BY p.type, p.sort_order, p.name'
            ),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin.plans.form', ['title' => 'Add plan', 'plan' => null, 'providers' => $this->providers()]);
    }

    public function store(): never
    {
        $data = $this->validate(self::RULES);
        $id = db()->insert('hosting_plans', $this->fields($data) + ['created_at' => now(), 'updated_at' => now()]);
        audit('plan.created', 'plan', $id, ['name' => $data['name']]);
        flash('success', 'Plan created.');
        redirect('/hosting/plans');
    }

    public function edit(int $id): string
    {
        return $this->view('admin.plans.form', ['title' => 'Edit plan', 'plan' => $this->findOrFail('hosting_plans', $id), 'providers' => $this->providers()]);
    }

    public function update(int $id): never
    {
        $this->findOrFail('hosting_plans', $id);
        $data = $this->validate(self::RULES);
        db()->update('hosting_plans', $this->fields($data) + ['updated_at' => now()], 'id = :id', ['id' => $id]);
        audit('plan.updated', 'plan', $id, ['name' => $data['name'], 'status' => $data['status']]);
        flash('success', 'Plan saved.');
        redirect('/hosting/plans');
    }

    private function providers(): array
    {
        return db()->fetchAll("SELECT id, name FROM hosting_providers WHERE status = 'active' ORDER BY name");
    }

    private function fields(array $d): array
    {
        return [
            'type' => $d['type'],
            'name' => $d['name'],
            'description' => $d['description'] ?: null,
            'price_monthly' => $d['price_monthly'] !== '' ? $d['price_monthly'] : null,
            'price_quarterly' => $d['price_quarterly'] !== '' ? $d['price_quarterly'] : null,
            'price_semiannual' => $d['price_semiannual'] !== '' ? $d['price_semiannual'] : null,
            'price_annual' => $d['price_annual'] !== '' ? $d['price_annual'] : null,
            'setup_fee' => $d['setup_fee'] !== '' ? $d['setup_fee'] : 0,
            'default_cycle' => $d['default_cycle'],
            'storage_gb' => $d['storage_gb'] !== '' ? $d['storage_gb'] : null,
            'bandwidth_gb' => $d['bandwidth_gb'] !== '' ? $d['bandwidth_gb'] : null,
            'domains_allowed' => $d['domains_allowed'] !== '' ? $d['domains_allowed'] : null,
            'email_accounts' => $d['email_accounts'] !== '' ? $d['email_accounts'] : null,
            'databases_allowed' => $d['databases_allowed'] !== '' ? $d['databases_allowed'] : null,
            'features' => $d['features'] ?: null,
            'provider_id' => $d['provider_id'] !== '' ? (int) $d['provider_id'] : null,
            'provider_package' => $d['provider_package'] ?: null,
            'status' => $d['status'],
            'sort_order' => (int) ($d['sort_order'] !== '' ? $d['sort_order'] : 0),
        ];
    }
}
