<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\Hosting\ProviderRegistry;

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
        return $this->view('admin.plans.form', ['title' => 'Add plan', 'plan' => null, 'providers' => $this->providers(), 'packageNames' => $this->packageNames()]);
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
        return $this->view('admin.plans.form', ['title' => 'Edit plan', 'plan' => $this->findOrFail('hosting_plans', $id), 'providers' => $this->providers(), 'packageNames' => $this->packageNames()]);
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

    /** WHM package names across cPanel providers, for the plan form datalist. */
    private function packageNames(): array
    {
        $names = [];
        foreach (db()->fetchAll("SELECT * FROM hosting_providers WHERE type = 'cpanel_whm' AND status = 'active'") as $row) {
            try {
                $r = ProviderRegistry::make($row)->listPackages();
                if ($r->success) {
                    foreach ($r->data as $p) {
                        $names[] = (string) $p['name'];
                    }
                }
            } catch (\Throwable) {
                // Provider unreachable — datalist just stays shorter.
            }
        }
        return array_values(array_unique($names));
    }

    // ---------------------------------------------------------- server packages

    /** Server-side (WHM) package management for cPanel providers. */
    public function packages(): string
    {
        $providers = [];
        foreach (db()->fetchAll("SELECT * FROM hosting_providers WHERE type = 'cpanel_whm' AND status = 'active' ORDER BY name") as $row) {
            $provider = ProviderRegistry::make($row);
            $result = $provider->listPackages();
            $accounts = $provider->listAccounts();
            $usage = [];
            if ($accounts->success) {
                foreach ($accounts->data as $a) {
                    $pkg = (string) ($a['package'] ?? '');
                    $usage[$pkg] = ($usage[$pkg] ?? 0) + 1;
                }
            }
            // HostFlow-side usage: services + plans mapped to each package.
            $svcUsage = [];
            foreach (db()->fetchAll('SELECT provider_package AS pkg, COUNT(*) AS n FROM services WHERE provider_id = ? GROUP BY provider_package', [(int) $row['id']]) as $r) {
                $svcUsage[(string) $r['pkg']] = (int) $r['n'];
            }
            $planUsage = [];
            foreach (db()->fetchAll('SELECT provider_package AS pkg, COUNT(*) AS n FROM hosting_plans WHERE provider_id = ? GROUP BY provider_package', [(int) $row['id']]) as $r) {
                $planUsage[(string) $r['pkg']] = (int) $r['n'];
            }
            $providers[] = [
                'row' => $row,
                'packages' => $result->success ? $result->data : [],
                'error' => $result->success ? null : $result->message,
                'account_usage' => $usage,
                'service_usage' => $svcUsage,
                'plan_usage' => $planUsage,
            ];
        }
        return $this->view('admin.plans.packages', ['title' => 'Server packages', 'providers' => $providers]);
    }

    public function packageSave(): never
    {
        $providerId = (int) Request::input('provider_id', 0);
        $row = db()->fetch("SELECT * FROM hosting_providers WHERE id = ? AND type = 'cpanel_whm' AND status = 'active'", [$providerId]);
        if (!$row) {
            flash('error', 'Provider not found.');
            redirect('/hosting/packages');
        }
        $provider = ProviderRegistry::make($row);
        $spec = $this->packageSpec();
        $existing = trim((string) Request::input('pkg', ''));
        try {
            if ($existing !== '') {
                // Rename is not supported by WHM — the package name stays fixed on edit.
                $r = $provider->updatePackage($existing, $spec);
                audit('package.updated', 'provider', $providerId, ['package' => $existing]);
            } else {
                $r = $provider->createPackage($spec);
                audit('package.created', 'provider', $providerId, ['package' => $spec['name'] ?? '']);
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/hosting/packages');
        }
        if (!$r->success) {
            flash('error', $r->message);
        } else {
            flash('success', $existing !== '' ? "Package '{$existing}' updated on {$row['name']}." : "Package '{$spec['name']}' created on {$row['name']}.");
        }
        redirect('/hosting/packages');
    }

    public function packageDelete(): never
    {
        $providerId = (int) Request::input('provider_id', 0);
        $name = trim((string) Request::input('pkg', ''));
        $row = db()->fetch("SELECT * FROM hosting_providers WHERE id = ? AND type = 'cpanel_whm' AND status = 'active'", [$providerId]);
        if (!$row || $name === '') {
            flash('error', 'Provider or package missing.');
            redirect('/hosting/packages');
        }
        // HostFlow-side guard: plans/services still pointing at this package.
        $mapped = (int) db()->fetchColumn('SELECT COUNT(*) FROM hosting_plans WHERE provider_id = ? AND provider_package = ?', [$providerId, $name])
            + (int) db()->fetchColumn('SELECT COUNT(*) FROM services WHERE provider_id = ? AND provider_package = ? AND status NOT IN (\'terminated\',\'cancelled\')', [$providerId, $name]);
        if ($mapped > 0) {
            flash('error', "Cannot delete '{$name}' — {$mapped} HostFlow plan(s)/service(s) still map to it. Re-map them first.");
            redirect('/hosting/packages');
        }
        $r = ProviderRegistry::make($row)->deletePackage($name);
        if ($r->success) {
            audit('package.deleted', 'provider', $providerId, ['package' => $name]);
            flash('success', "Package '{$name}' deleted from {$row['name']}.");
        } else {
            flash('error', $r->message);
        }
        redirect('/hosting/packages');
    }

    /** Read the package form into a normalised spec (GB→MB for disk/bandwidth). */
    private function packageSpec(): array
    {
        $num = function (string $key) {
            $v = trim((string) Request::input($key, ''));
            return $v === '' ? null : (float) $v;
        };
        $int = function (string $key) {
            $v = trim((string) Request::input($key, ''));
            return $v === '' ? null : (int) $v;
        };
        $spec = [
            'name' => trim((string) Request::input('name', '')),
            'disk_mb' => ($g = $num('disk_gb')) !== null ? $g * 1024 : null,
            'bandwidth_mb' => ($g = $num('bandwidth_gb')) !== null ? $g * 1024 : null,
            'email_accounts' => $int('email_accounts'),
            'databases' => $int('databases'),
            'domains' => $int('domains'),
            'subdomains' => $int('subdomains'),
            'parked_domains' => $int('parked_domains'),
            'ftp_accounts' => $int('ftp_accounts'),
        ];
        // Keep nulls: on edit an empty field means "set to unlimited", not "leave unchanged".
        return [
            'name' => $spec['name'],
            'disk_mb' => $spec['disk_mb'],
            'bandwidth_mb' => $spec['bandwidth_mb'],
            'email_accounts' => $spec['email_accounts'],
            'databases' => $spec['databases'],
            'domains' => $spec['domains'],
            'subdomains' => $spec['subdomains'],
            'parked_domains' => $spec['parked_domains'],
            'ftp_accounts' => $spec['ftp_accounts'],
        ];
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
