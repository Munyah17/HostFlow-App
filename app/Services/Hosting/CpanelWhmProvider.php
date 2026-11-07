<?php

declare(strict_types=1);

namespace App\Services\Hosting;

use App\Core\Logger;

/**
 * cPanel & WHM integration using the official WHM API 1 (JSON API) with API-token auth.
 *
 *   https://{host}:2087/json-api/{function}?api.version=1&...
 *   Authorization: whm {username}:{api_token}
 *
 * Every response is checked for metadata.result === 1. HTTP 200 alone is never
 * treated as success. State-changing operations are re-verified by reading the
 * account back from the server before success is reported.
 *
 * Reference: https://api.docs.cpanel.net/whm/introduction
 */
final class CpanelWhmProvider implements HostingProviderInterface
{
    /** @var callable(string $url, array $headers, int $timeout): array{status:int, body:string, error:?string} */
    private $http;

    public function __construct(
        private string $hostname,
        private string $username,
        private string $apiToken,
        private int $port = 2087,
        private bool $verifySsl = true,
        ?callable $http = null,
    ) {
        $this->http = $http ?? [$this, 'curlRequest'];
    }

    public static function fromRow(array $provider, ?callable $http = null): self
    {
        $token = (string) ($provider['api_token'] ?? '');
        if ($token !== '') {
            $token = \App\Core\Crypto::decrypt($token);
        }
        return new self((string) $provider['hostname'], (string) $provider['username'], $token, (int) ($provider['port'] ?: 2087), (bool) $provider['verify_ssl'], $http);
    }

    // ---------------------------------------------------------------- API ops

    public function testConnection(): ProviderResult
    {
        $version = $this->call('version');
        if (!$version->success) {
            return $version;
        }
        $privs = $this->call('myprivs');
        $privileges = $privs->success ? (array) ($privs->data['privileges'] ?? []) : [];
        // WHM may return privileges as a list of maps — flatten to acl => granted.
        if (isset($privileges[0]) && is_array($privileges[0])) {
            $flat = [];
            foreach ($privileges as $p) {
                $flat += (array) $p;
            }
            $privileges = $flat;
        }
        $required = ['list-accts', 'acct-summary', 'create-acct', 'suspend-acct', 'upgrade-account', 'kill-acct', 'create-user-session'];
        $missing = [];
        if (empty($privileges['all'])) {
            foreach ($required as $acl) {
                if (empty($privileges[$acl])) {
                    $missing[] = $acl;
                }
            }
        }
        $message = 'Connected to cPanel & WHM ' . ($version->data['version'] ?? '');
        if (!$privs->success) {
            $message .= '. Could not read privileges: ' . $privs->message;
        } elseif ($missing) {
            $message .= '. Missing reseller privileges: ' . implode(', ', $missing) . ' — related operations will fail until granted.';
        }
        return ProviderResult::ok($message, ['version' => $version->data['version'] ?? null, 'privileges' => $privileges, 'missing_privileges' => $missing]);
    }

    public function getResellerInfo(): ProviderResult
    {
        $counts = $this->call('acctcounts');
        if (!$counts->success) {
            return $counts;
        }
        $r = (array) ($counts->data['reseller'] ?? []);
        return ProviderResult::ok('OK', [
            'user' => $r['user'] ?? $this->username,
            'active' => (int) ($r['active'] ?? 0),
            'suspended' => (int) ($r['suspended'] ?? 0),
            'limit' => isset($r['limit']) ? (int) $r['limit'] : null,
        ]);
    }

    public function listPackages(): ProviderResult
    {
        $res = $this->call('listpkgs');
        if (!$res->success) {
            return $res;
        }
        $out = [];
        foreach ((array) ($res->data['pkg'] ?? []) as $pkg) {
            $out[] = [
                'name' => (string) ($pkg['name'] ?? ''),
                'disk_mb' => self::limit($pkg['QUOTA'] ?? null),
                'bandwidth_mb' => self::limit($pkg['BWLIMIT'] ?? null),
                'email_accounts' => self::limit($pkg['MAXPOP'] ?? null),
                'databases' => self::limit($pkg['MAXSQL'] ?? null),
                'domains' => self::limit($pkg['MAXADDON'] ?? null),
                'subdomains' => self::limit($pkg['MAXSUB'] ?? null),
                'parked_domains' => self::limit($pkg['MAXPARK'] ?? null),
                'ftp_accounts' => self::limit($pkg['MAXFTP'] ?? null),
                'mailing_lists' => self::limit($pkg['MAXLST'] ?? null),
                'api_tokens' => self::limit($pkg['MAXTOKENS'] ?? null),
            ];
        }
        return ProviderResult::ok('OK', $out);
    }

    public function createPackage(array $spec): ProviderResult
    {
        $name = trim((string) ($spec['name'] ?? ''));
        if ($name === '') {
            return ProviderResult::fail('Package name is required.');
        }
        $res = $this->call('addpkg', $this->packageParams($spec + ['name' => $name], fillDefaults: true), 60);
        if ($res->success) {
            // Verify it is really there before reporting success.
            $check = $this->call('listpkgs');
            $found = false;
            foreach ((array) ($check->data['pkg'] ?? []) as $pkg) {
                if (strcasecmp((string) ($pkg['name'] ?? ''), $name) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return ProviderResult::fail('WHM reported success but the package was not found in listpkgs.', null, transient: true);
            }
        }
        return $res;
    }

    public function updatePackage(string $name, array $spec): ProviderResult
    {
        $name = trim($name);
        if ($name === '') {
            return ProviderResult::fail('Package name is required.');
        }
        // WHM editpkg identifies the package via 'pkgname'; only send fields the caller provided.
        $allowed = ['disk_mb', 'bandwidth_mb', 'email_accounts', 'databases', 'domains', 'parked_domains', 'subdomains', 'ftp_accounts', 'mailing_lists', 'api_tokens'];
        $partial = array_intersect_key($spec, array_flip($allowed));
        return $this->call('editpkg', $this->packageParams($partial, fillDefaults: false) + ['pkgname' => $name], 60);
    }

    public function deletePackage(string $name): ProviderResult
    {
        $name = trim($name);
        if ($name === '') {
            return ProviderResult::fail('Package name is required.');
        }
        // Refuse to delete a package still assigned to accounts on this server.
        $accts = $this->listAccounts();
        if ($accts->success) {
            $users = [];
            foreach ($accts->data as $a) {
                if (strcasecmp((string) ($a['package'] ?? ''), $name) === 0) {
                    $users[] = (string) $a['username'];
                }
            }
            if ($users) {
                return ProviderResult::fail("Cannot delete '{$name}' — still used by " . count($users) . ' account(s): ' . implode(', ', array_slice($users, 0, 10)) . (count($users) > 10 ? ', …' : ''));
            }
        }
        return $this->call('killpkg', ['pkgname' => $name], 60);
    }

    /**
     * Normalise a HostFlow package spec into WHM addpkg/editpkg params.
     * This WHM version expects QUOTA/BWLIMIT in bytes (verified on v11.138);
     * unlimited MAX* fields take the literal 'unlimited'.
     */
    private function packageParams(array $spec, bool $fillDefaults = true): array
    {
        $mb = fn ($v) => ($v === null || $v === '') ? 'unlimited' : (int) round(((float) $v) * 1048576);
        $max = fn ($v) => ($v === null || $v === '') ? 'unlimited' : max(0, (int) $v);
        $map = [
            'disk_mb' => 'QUOTA', 'bandwidth_mb' => 'BWLIMIT', 'ftp_accounts' => 'MAXFTP',
            'databases' => 'MAXSQL', 'email_accounts' => 'MAXPOP', 'mailing_lists' => 'MAXLST',
            'subdomains' => 'MAXSUB', 'parked_domains' => 'MAXPARK', 'domains' => 'MAXADDON',
            'api_tokens' => 'MAXTOKENS',
        ];
        $params = [];
        if (array_key_exists('name', $spec)) {
            $params['name'] = (string) $spec['name'];
        }
        foreach ($map as $key => $whm) {
            if (array_key_exists($key, $spec) || $fillDefaults) {
                $params[$whm] = in_array($whm, ['QUOTA', 'BWLIMIT'], true) ? $mb($spec[$key] ?? null) : $max($spec[$key] ?? null);
            }
        }
        return $params;
    }

    public function listAccounts(): ProviderResult
    {
        // Scope to accounts owned by this reseller via WHM API 1 output filtering
        // (api.filter.a.field=owner, eq). Reseller tokens are already scoped by WHM;
        // this mainly protects against a root-level token reconciling other
        // resellers' accounts. 'root' itself stays unfiltered so the sync report
        // can still surface every unmanaged account on the server.
        $params = [];
        if ($this->username !== '' && strtolower($this->username) !== 'root') {
            $params = [
                'api.filter.enable' => 1,
                'api.filter.a.field' => 'owner',
                'api.filter.a.arg0' => $this->username,
                'api.filter.a.type' => 'eq',
            ];
        }
        $res = $this->call('listaccts', $params);
        if (!$res->success) {
            return $res;
        }
        return ProviderResult::ok('OK', array_map([self::class, 'normaliseAccount'], (array) ($res->data['acct'] ?? [])));
    }

    public function getAccount(string $username): ProviderResult
    {
        if (!self::validUsername($username)) {
            return ProviderResult::fail('Invalid cPanel username.');
        }
        $res = $this->call('listaccts', ['search' => $username, 'searchtype' => 'user', 'searchmethod' => 'exact']);
        if (!$res->success) {
            return $res;
        }
        foreach ((array) ($res->data['acct'] ?? []) as $acct) {
            if (strtolower((string) ($acct['user'] ?? '')) === strtolower($username)) {
                return ProviderResult::ok('OK', self::normaliseAccount($acct));
            }
        }
        return ProviderResult::fail("Account '{$username}' does not exist on the server.", null, notFound: true);
    }

    public function createAccount(array $data): ProviderResult
    {
        $username = strtolower((string) ($data['username'] ?? ''));
        if (!self::validUsername($username)) {
            return ProviderResult::fail('Invalid cPanel username. Use 1-16 lowercase letters/digits, starting with a letter and not "test".');
        }
        if (empty($data['domain']) || empty($data['package'])) {
            return ProviderResult::fail('Domain and hosting package are required to create an account.');
        }
        $params = [
            'username' => $username,
            'domain' => strtolower((string) $data['domain']),
            'plan' => (string) $data['package'],
            'contactemail' => (string) ($data['email'] ?? ''),
        ];
        if (!empty($data['password'])) {
            $params['password'] = (string) $data['password'];
        }
        $res = $this->call('createacct', $params, 120);
        if (!$res->success) {
            return $res;
        }
        // Confirm the account really exists before reporting success.
        $verify = $this->getAccount($username);
        if (!$verify->success) {
            return ProviderResult::fail('WHM reported success but the account could not be verified: ' . $verify->message, transient: true);
        }
        return ProviderResult::ok('Account created', $verify->data);
    }

    public function suspendAccount(string $username, string $reason): ProviderResult
    {
        $res = $this->call('suspendacct', ['user' => $username, 'reason' => mb_substr($reason, 0, 250)]);
        if (!$res->success) {
            return $res;
        }
        $verify = $this->getAccount($username);
        if (!$verify->success || empty($verify->data['suspended'])) {
            return ProviderResult::fail('Suspension could not be verified on the server: ' . ($verify->success ? 'account is not suspended' : $verify->message), transient: true);
        }
        return ProviderResult::ok('Account suspended', $verify->data);
    }

    public function unsuspendAccount(string $username): ProviderResult
    {
        $res = $this->call('unsuspendacct', ['user' => $username]);
        if (!$res->success) {
            return $res;
        }
        $verify = $this->getAccount($username);
        if (!$verify->success || !empty($verify->data['suspended'])) {
            return ProviderResult::fail('Unsuspension could not be verified on the server: ' . ($verify->success ? 'account is still suspended' : $verify->message), transient: true);
        }
        return ProviderResult::ok('Account unsuspended', $verify->data);
    }

    public function changePackage(string $username, string $package): ProviderResult
    {
        $res = $this->call('changepackage', ['user' => $username, 'pkg' => $package], 90);
        if (!$res->success) {
            return $res;
        }
        $verify = $this->getAccount($username);
        if (!$verify->success || (string) $verify->data['package'] !== $package) {
            return ProviderResult::fail('Package change could not be verified on the server: ' . ($verify->success ? 'package is ' . $verify->data['package'] : $verify->message), transient: true);
        }
        return ProviderResult::ok('Package changed', $verify->data);
    }

    public function terminateAccount(string $username): ProviderResult
    {
        $res = $this->call('removeacct', ['username' => $username], 120);
        if (!$res->success) {
            return $res;
        }
        $verify = $this->getAccount($username);
        if (!$verify->notFound) {
            return ProviderResult::fail('Termination could not be verified: ' . ($verify->success ? 'the account still exists' : $verify->message), transient: true);
        }
        return ProviderResult::ok('Account terminated');
    }

    public function createLoginSession(string $username, string $service = 'cpanel', ?string $app = null): ProviderResult
    {
        $params = ['user' => $username, 'service' => $service === 'webmail' ? 'webmaild' : 'cpaneld'];
        if ($app) {
            $params['app'] = $app;
        }
        $res = $this->call('create_user_session', $params);
        if (!$res->success) {
            return $res;
        }
        $url = (string) ($res->data['url'] ?? '');
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            return ProviderResult::fail('WHM did not return a login URL.');
        }
        return ProviderResult::ok('Session created', ['url' => $url, 'expires' => $res->data['expires'] ?? null]);
    }

    // ------------------------------------------------------------ transport

    /**
     * Call a WHM API 1 function and validate the response envelope.
     */
    public function call(string $function, array $params = [], int $timeout = 45): ProviderResult
    {
        if ($this->hostname === '' || $this->username === '' || $this->apiToken === '') {
            return ProviderResult::fail('Hosting provider credentials are incomplete (hostname, username and API token are required).');
        }
        $host = preg_replace('#^https?://#', '', rtrim($this->hostname, '/'));
        $url = sprintf('https://%s:%d/json-api/%s?%s', $host, $this->port, rawurlencode($function), http_build_query(['api.version' => 1] + $params, '', '&', PHP_QUERY_RFC3986));
        $headers = ['Authorization: whm ' . $this->username . ':' . $this->apiToken, 'Accept: application/json'];

        $response = ($this->http)($url, $headers, $timeout);
        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');

        if (!empty($response['error'])) {
            Logger::error("WHM {$function} transport error", ['error' => $response['error']], 'hosting');
            return ProviderResult::fail('Could not connect to the hosting server: ' . $response['error'], transient: true);
        }
        if ($status === 401) {
            return ProviderResult::fail('Authentication failed (HTTP 401). Check the WHM username and API token.', permissionDenied: true);
        }
        if ($status === 403) {
            return ProviderResult::fail("Permission denied for '{$function}' (HTTP 403). Check the API token's ACLs / reseller privileges.", permissionDenied: true);
        }
        if ($status >= 500 || $status === 0) {
            return ProviderResult::fail("Hosting server returned HTTP {$status} for '{$function}'.", transient: true);
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['metadata']) || !is_array($json['metadata'])) {
            Logger::error("WHM {$function} invalid response", ['status' => $status, 'body' => substr($body, 0, 500)], 'hosting');
            return ProviderResult::fail("Unexpected response from the hosting server for '{$function}' (HTTP {$status}).", transient: $status !== 200);
        }
        $meta = $json['metadata'];
        $reason = trim((string) ($meta['reason'] ?? ''));
        if ((int) ($meta['result'] ?? 0) !== 1) {
            $denied = (bool) preg_match('/permission|not allowed|access denied|privilege|acl/i', $reason);
            Logger::error("WHM {$function} failed", ['reason' => $reason, 'params' => self::redact($params)], 'hosting');
            return ProviderResult::fail(($denied ? 'Permission denied: ' : 'WHM error: ') . ($reason ?: 'unknown error') . " ({$function})", $json['data'] ?? null, permissionDenied: $denied);
        }
        return ProviderResult::ok($reason ?: 'OK', $json['data'] ?? []);
    }

    private function curlRequest(string $url, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'HostFlow/' . app_version(),
        ]);
        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'error' => $error];
    }

    // -------------------------------------------------------------- helpers

    public static function validUsername(string $u): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9]{0,15}$/', $u) && !str_starts_with($u, 'test');
    }

    public static function normaliseAccount(array $a): array
    {
        $start = isset($a['unix_startdate']) && is_numeric($a['unix_startdate']) ? date('Y-m-d H:i:s', (int) $a['unix_startdate']) : null;
        return [
            'username' => (string) ($a['user'] ?? ''),
            'domain' => strtolower((string) ($a['domain'] ?? '')),
            'package' => (string) ($a['plan'] ?? ''),
            'suspended' => (int) ($a['suspended'] ?? 0) === 1,
            'suspend_reason' => isset($a['suspendreason']) && $a['suspendreason'] !== 'not suspended' ? (string) $a['suspendreason'] : null,
            'email' => (string) ($a['email'] ?? ''),
            'disk_used_mb' => self::sizeToMb($a['diskused'] ?? null),
            'disk_limit_mb' => self::sizeToMb($a['disklimit'] ?? null),
            'created_at' => $start,
            'owner' => (string) ($a['owner'] ?? ''),
        ];
    }

    /** "14M" / "1.5G" / "unlimited" / 500 → MB (null = unlimited/unknown). */
    public static function sizeToMb(mixed $value): ?float
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'unlimited') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (preg_match('/^([\d.]+)\s*([KMGT]?)/i', (string) $value, $m)) {
            $n = (float) $m[1];
            return match (strtoupper($m[2])) {
                'K' => round($n / 1024, 2),
                'G' => $n * 1024,
                'T' => $n * 1048576,
                default => $n,
            };
        }
        return null;
    }

    private static function limit(mixed $value): ?float
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'unlimited') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    private static function redact(array $params): array
    {
        foreach (['password', 'pass'] as $k) {
            if (isset($params[$k])) {
                $params[$k] = '***';
            }
        }
        return $params;
    }
}
