<?php

declare(strict_types=1);

namespace App\Core;

/**
 * AES-256-GCM encryption for stored secrets (API tokens, SMTP/payment credentials).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $raw = (string) Env::get('APP_KEY', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }
        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('APP_KEY is missing or invalid. Generate one with: php bin/hostflow key:generate');
        }
        return $key;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'enc:v1:')) {
            return $payload;
        }
        $data = base64_decode(substr($payload, 7), true);
        if ($data === false || strlen($data) < 28) {
            throw new \RuntimeException('Encrypted value is corrupted.');
        }
        $plain = openssl_decrypt(substr($data, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
        if ($plain === false) {
            throw new \RuntimeException('Unable to decrypt value. Has APP_KEY changed?');
        }
        return $plain;
    }
}
