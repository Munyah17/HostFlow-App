<?php

declare(strict_types=1);

namespace App\Services\Hosting;

/**
 * Contract for hosting infrastructure integrations (cPanel/WHM). Providers only perform hosting operations;
 * customers, billing and automation remain in the HostFlow core.
 *
 * Normalised account array:
 *   username, domain, package, suspended (bool), suspend_reason, email,
 *   disk_used_mb (float|null), disk_limit_mb (float|null, null = unlimited), created_at (Y-m-d H:i:s|null), owner
 */
interface HostingProviderInterface
{
    /** Verify credentials/connectivity. data: ['version' => string, 'privileges' => array] */
    public function testConnection(): ProviderResult;

    /** Reseller/account-level information where the API permits it. */
    public function getResellerInfo(): ProviderResult;

    /** data: list of ['name', 'disk_mb', 'bandwidth_mb', 'email_accounts', 'databases', 'domains', 'subdomains', 'parked_domains', 'ftp_accounts', 'mailing_lists', 'api_tokens'] (null = unlimited) */
    public function listPackages(): ProviderResult;

    /** data: list of normalised accounts */
    public function listAccounts(): ProviderResult;

    /** data: normalised account; notFound=true when the account does not exist */
    public function getAccount(string $username): ProviderResult;

    /** $data: username, domain, package, email, password (optional) */
    public function createAccount(array $data): ProviderResult;

    public function suspendAccount(string $username, string $reason): ProviderResult;

    public function unsuspendAccount(string $username): ProviderResult;

    public function changePackage(string $username, string $package): ProviderResult;

    public function terminateAccount(string $username): ProviderResult;

    /** data: ['url' => single-use login URL]. $service: 'cpanel' | 'webmail'; $app optional cPanel app key. */
    public function createLoginSession(string $username, string $service = 'cpanel', ?string $app = null): ProviderResult;

    /**
     * Server package (WHM pkg) management. $spec keys (all optional except name on create):
     *   name, disk_mb, bandwidth_mb, email_accounts, databases, domains (addon),
     *   parked_domains, subdomains, ftp_accounts, mailing_lists, api_tokens
     * null = unlimited where the server supports it.
     */
    public function createPackage(array $spec): ProviderResult;

    /** Update an existing package; $name identifies it. */
    public function updatePackage(string $name, array $spec): ProviderResult;

    /** Delete a package. Providers should refuse when accounts still use it. */
    public function deletePackage(string $name): ProviderResult;
}
