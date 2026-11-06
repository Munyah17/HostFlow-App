<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Logger;
use App\Services\Settings;

/**
 * Stripe integration (https://stripe.com/docs/api) — no SDK required, uses REST over cURL.
 *
 * - Initiate: POST /v1/checkout/sessions (hosted Checkout page; supports cards, mobile wallets)
 * - Status: the return page and cron poll GET /v1/checkout/sessions/{id}
 * - Webhook: Stripe POSTs JSON to resulturl-equivalent /webhooks/gateway/stripe; the
 *   Stripe-Signature header is verified with the webhook signing secret, then the
 *   session is fetched server-side (webhooks alone are never trusted).
 * Activate in Settings → Payments: paste secret + publishable keys, tick "Accept Stripe".
 */
final class StripeGateway implements PaymentGatewayInterface
{
    public const API = 'https://api.stripe.com/v1';

    /** @var callable(string $url, string $method, array $fields, string $key): array{status:int, body:string, error:?string} */
    private $http;

    public function __construct(?callable $http = null, private ?string $secretKey = null)
    {
        $this->http = $http ?? [$this, 'request'];
    }

    public function id(): string
    {
        return 'stripe';
    }

    public function name(): string
    {
        return 'Stripe';
    }

    private function key(): string
    {
        return $this->secretKey ?? (string) Settings::get('stripe_secret_key');
    }

    public function isAvailable(): bool
    {
        return Settings::bool('stripe_enabled') && $this->key() !== '';
    }

    public function initiate(array $payment, array $invoice, array $customer, array $options = []): array
    {
        $fields = [
            'mode' => 'payment',
            'success_url' => absolute_url('/client/payments/' . $payment['reference'] . '/return'),
            'cancel_url' => absolute_url('/client/invoices/' . $invoice['id']),
            'client_reference_id' => $payment['reference'],
            'line_items[0][price_data][currency]' => strtolower((string) $payment['currency']),
            'line_items[0][price_data][unit_amount]' => (int) round((float) $payment['amount'] * 100),
            'line_items[0][price_data][product_data][name]' => 'Invoice ' . $invoice['number'] . ' — ' . Settings::get('business_name'),
            'line_items[0][quantity]' => 1,
            'metadata[invoice_id]' => $invoice['id'],
            'metadata[payment_reference]' => $payment['reference'],
        ];
        if (filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $fields['customer_email'] = $customer['email'];
        }
        $res = ($this->http)(self::API . '/checkout/sessions', 'POST', $fields, $this->key());
        $data = $this->decode($res, 'create a Checkout Session');
        if (empty($data['url']) || empty($data['id'])) {
            throw new \RuntimeException('Stripe did not return a checkout URL.');
        }
        return ['redirect' => $data['url'], 'gateway_reference' => $data['id'], 'poll_url' => self::API . '/checkout/sessions/' . $data['id']];
    }

    public function verify(array $payment): GatewayStatus
    {
        $sessionId = (string) ($payment['gateway_reference'] ?? '');
        if ($sessionId === '' && preg_match('#/checkout/sessions/([^/]+)$#', (string) ($payment['poll_url'] ?? ''), $m)) {
            $sessionId = $m[1];
        }
        if ($sessionId === '') {
            return new GatewayStatus(GatewayStatus::PENDING, 'No Checkout Session id');
        }
        $res = ($this->http)(self::API . '/checkout/sessions/' . urlencode($sessionId), 'GET', [], $this->key());
        if (!empty($res['error']) || (int) $res['status'] !== 200) {
            return new GatewayStatus(GatewayStatus::PENDING, 'Stripe lookup failed: ' . ($res['error'] ?: 'HTTP ' . $res['status']), raw: (string) $res['body']);
        }
        $s = json_decode((string) $res['body'], true);
        if (!is_array($s)) {
            return new GatewayStatus(GatewayStatus::PENDING, 'Invalid Stripe response', raw: (string) $res['body']);
        }
        $payStatus = (string) ($s['payment_status'] ?? '');
        $status = (string) ($s['status'] ?? '');
        $amount = isset($s['amount_total']) ? ((float) $s['amount_total'] / 100) : null;
        $state = match (true) {
            $payStatus === 'paid' => GatewayStatus::PAID,
            $payStatus === 'unpaid' && $status === 'expired' => GatewayStatus::CANCELLED,
            default => GatewayStatus::PENDING,
        };
        $ref = is_array($s['payment_intent'] ?? null) ? ($s['payment_intent']['id'] ?? null) : ($s['payment_intent'] ?? null);
        return new GatewayStatus($state, $status !== '' ? $status . '/' . $payStatus : $payStatus, $amount, $ref ?: $sessionId, (string) $res['body']);
    }

    public function parseWebhook(array $post): ?string
    {
        $secret = (string) Settings::get('stripe_webhook_secret');
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }
        $event = json_decode($raw, true);
        if (!is_array($event) || empty($event['type'])) {
            return null;
        }
        if ($secret !== '' && !$this->verifySignature($raw, $secret)) {
            Logger::error('Stripe webhook signature mismatch', [], 'payments');
            return null;
        }
        // No signing secret configured: still safe — verify() re-checks with the API.
        if ($event['type'] !== 'checkout.session.completed') {
            return 'ignore'; // acknowledged but nothing to verify
        }
        $ref = $event['data']['object']['client_reference_id'] ?? null;
        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    /** Stripe-Signature: "t=...,v1=...,v0=..." — signed payload is "t.payload" with the webhook secret. */
    private function verifySignature(string $raw, string $secret): bool
    {
        $header = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            if ($k === 't') {
                $t = $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if ($t === null || !$sigs) {
            return false;
        }
        if (abs(time() - (int) $t) > 300) {
            Logger::error('Stripe webhook timestamp out of tolerance', [], 'payments');
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $raw, $secret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }

    private function decode(array $res, string $action): array
    {
        if (!empty($res['error'])) {
            throw new \RuntimeException('Could not reach Stripe: ' . $res['error']);
        }
        $data = json_decode((string) $res['body'], true);
        if ((int) $res['status'] >= 400 || isset($data['error'])) {
            $msg = is_array($data['error'] ?? null) ? (string) ($data['error']['message'] ?? 'error') : 'HTTP ' . $res['status'];
            Logger::error('Stripe ' . $action . ' failed', ['error' => $msg], 'payments');
            throw new \RuntimeException('Stripe: ' . $msg);
        }
        return is_array($data) ? $data : [];
    }

    private function request(string $url, string $method, array $fields, string $key): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body, 'error' => $error];
    }
}
