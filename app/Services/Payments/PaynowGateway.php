<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Logger;
use App\Services\Settings;

/**
 * Paynow Zimbabwe integration (https://developers.paynow.co.zw/docs/).
 *
 * - Initiate: POST https://www.paynow.co.zw/interface/initiatetransaction (url-encoded)
 * - Mobile express: POST https://www.paynow.co.zw/interface/remotetransaction
 * - Status update: Paynow POSTs to resulturl; we verify the hash AND poll the pollurl
 * - Hash: values concatenated in message order (excluding "hash") + integration key, SHA512, uppercase hex
 * - Paid statuses: Paid, Awaiting Delivery, Delivered
 * Currency is determined by the integration ID configured in Paynow (no currency field).
 */
final class PaynowGateway implements PaymentGatewayInterface
{
    public const INITIATE_URL = 'https://www.paynow.co.zw/interface/initiatetransaction';
    public const REMOTE_URL = 'https://www.paynow.co.zw/interface/remotetransaction';
    private const PAID = ['paid', 'awaiting delivery', 'delivered'];
    private const FAILED = ['failed', 'disputed', 'refunded'];
    private const CANCELLED = ['cancelled'];

    /** @var callable(string $url, array $fields): array{status:int, body:string, error:?string} */
    private $http;

    public function __construct(?callable $http = null, private ?string $integrationId = null, private ?string $integrationKey = null)
    {
        $this->http = $http ?? [$this, 'post'];
    }

    public function id(): string
    {
        return 'paynow';
    }

    public function name(): string
    {
        return 'Paynow';
    }

    private function integrationId(): string
    {
        return $this->integrationId ?? (string) Settings::get('paynow_integration_id');
    }

    private function integrationKey(): string
    {
        return $this->integrationKey ?? (string) Settings::get('paynow_integration_key');
    }

    public function isAvailable(): bool
    {
        return Settings::bool('paynow_enabled') && $this->integrationId() !== '' && $this->integrationKey() !== '';
    }

    public function mobileEnabled(): bool
    {
        return $this->isAvailable() && Settings::bool('paynow_mobile_enabled');
    }

    public function initiate(array $payment, array $invoice, array $customer, array $options = []): array
    {
        $mobile = !empty($options['phone']);
        $authEmail = (string) (Settings::get('paynow_auth_email') ?: $customer['email']);
        $fields = [
            'id' => $this->integrationId(),
            'reference' => $payment['reference'],
            'amount' => number_format((float) $payment['amount'], 2, '.', ''),
            'additionalinfo' => 'Invoice ' . $invoice['number'],
            'returnurl' => absolute_url('/client/payments/' . $payment['reference'] . '/return'),
            'resulturl' => absolute_url('/webhooks/paynow'),
            'authemail' => $authEmail,
        ];
        if ($mobile) {
            $fields['phone'] = preg_replace('/\D+/', '', (string) $options['phone']);
            $fields['method'] = (string) ($options['method'] ?? 'ecocash');
        }
        $fields['status'] = 'Message';
        $fields['hash'] = self::hash($fields, $this->integrationKey());

        $res = ($this->http)($mobile ? self::REMOTE_URL : self::INITIATE_URL, $fields);
        if (!empty($res['error']) || (int) $res['status'] !== 200) {
            throw new \RuntimeException('Could not reach Paynow: ' . ($res['error'] ?: 'HTTP ' . $res['status']));
        }
        $data = self::parse((string) $res['body']);
        $status = strtolower($data['status'] ?? '');
        if ($status !== 'ok') {
            $error = $data['error'] ?? 'Unknown error';
            Logger::error('Paynow initiate failed', ['reference' => $payment['reference'], 'error' => $error], 'payments');
            throw new \RuntimeException('Paynow rejected the payment request: ' . $error);
        }
        if (!self::verifyHash($data, $this->integrationKey())) {
            throw new \RuntimeException('Paynow response failed hash verification.');
        }
        $out = ['poll_url' => $data['pollurl'] ?? null, 'gateway_reference' => $data['paynowreference'] ?? null];
        if ($mobile) {
            $out['instructions'] = $data['instructions'] ?? 'Please approve the payment request on your phone.';
        } else {
            $out['redirect'] = $data['browserurl'] ?? null;
            if (!$out['redirect']) {
                throw new \RuntimeException('Paynow did not return a payment page URL.');
            }
        }
        return $out;
    }

    public function verify(array $payment): GatewayStatus
    {
        $pollUrl = (string) ($payment['poll_url'] ?? '');
        if (!str_starts_with($pollUrl, 'https://www.paynow.co.zw/')) {
            return new GatewayStatus(GatewayStatus::PENDING, 'No poll URL');
        }
        $res = ($this->http)($pollUrl, []);
        if (!empty($res['error']) || (int) $res['status'] !== 200) {
            return new GatewayStatus(GatewayStatus::PENDING, 'Poll failed: ' . ($res['error'] ?: 'HTTP ' . $res['status']));
        }
        $data = self::parse((string) $res['body']);
        if (!self::verifyHash($data, $this->integrationKey())) {
            Logger::error('Paynow poll hash mismatch', ['reference' => $payment['reference']], 'payments');
            return new GatewayStatus(GatewayStatus::PENDING, 'Hash mismatch', raw: (string) $res['body']);
        }
        if (($data['reference'] ?? '') !== $payment['reference']) {
            return new GatewayStatus(GatewayStatus::PENDING, 'Reference mismatch', raw: (string) $res['body']);
        }
        $gwStatus = (string) ($data['status'] ?? '');
        $lower = strtolower($gwStatus);
        $state = match (true) {
            in_array($lower, self::PAID, true) => GatewayStatus::PAID,
            in_array($lower, self::FAILED, true) => GatewayStatus::FAILED,
            in_array($lower, self::CANCELLED, true) => GatewayStatus::CANCELLED,
            default => GatewayStatus::PENDING,
        };
        return new GatewayStatus($state, $gwStatus, isset($data['amount']) ? (float) $data['amount'] : null, $data['paynowreference'] ?? null, (string) $res['body']);
    }

    public function parseWebhook(array $post): ?string
    {
        if (empty($post['reference']) || empty($post['hash'])) {
            return null;
        }
        $raw = file_get_contents('php://input');
        $data = $raw !== false && $raw !== '' ? self::parse($raw) : array_map('strval', $post);
        if (!self::verifyHash($data, $this->integrationKey())) {
            Logger::error('Paynow webhook hash mismatch', ['reference' => $post['reference']], 'payments');
            return null;
        }
        return (string) $data['reference'];
    }

    // ----------------------------------------------------------- helpers

    /** Paynow hash: concatenate values in order (excluding "hash") + key, SHA512, uppercase. */
    public static function hash(array $fields, string $key): string
    {
        $concat = '';
        foreach ($fields as $name => $value) {
            if (strtolower((string) $name) !== 'hash') {
                $concat .= (string) $value;
            }
        }
        return strtoupper(hash('sha512', $concat . $key));
    }

    public static function verifyHash(array $data, string $key): bool
    {
        if (!isset($data['hash'])) {
            return false;
        }
        return hash_equals(self::hash($data, $key), strtoupper((string) $data['hash']));
    }

    /** Parse a url-encoded response preserving field order (parse_str() is avoided on purpose). */
    public static function parse(string $body): array
    {
        $out = [];
        foreach (explode('&', trim($body)) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $out[strtolower(urldecode($k))] = urldecode($v);
        }
        return $out;
    }

    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'error' => $error];
    }
}
