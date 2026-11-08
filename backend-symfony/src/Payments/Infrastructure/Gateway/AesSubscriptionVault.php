<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Domain\JavaBase64;
use App\Payments\Domain\PaymentException;
use SensitiveParameter;

/**
 * AES-256-GCM with a random 12-byte nonce, no associated data, stored as `base64(nonce):base64(ciphertext‖tag16)`,
 * exactly as Java's `AesSubscriptionVault` (keep the Java-captured vectors in AesSubscriptionVaultTest green).
 */
final readonly class AesSubscriptionVault implements SubscriptionVault
{
    private const string CIPHER = 'aes-256-gcm';

    public function __construct(#[SensitiveParameter] private string $key)
    {
    }

    public function encrypt(#[SensitiveParameter] string $token): string
    {
        $key = $this->key('Payment token encryption is unavailable.');
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($token, self::CIPHER, $key, \OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if (false === $ciphertext) {
            throw PaymentException::unavailable('Payment token encryption is unavailable.');
        }

        return base64_encode($nonce).':'.base64_encode($ciphertext.$tag);
    }

    public function decrypt(string $encrypted): string
    {
        $failure = 'Payment token could not be decrypted. Check the configured key.';
        $parts = explode(':', $encrypted);
        $key = $this->key($failure);
        if (2 !== \count($parts)) {
            throw PaymentException::unavailable($failure);
        }
        $nonce = JavaBase64::decode($parts[0]);
        $sealed = JavaBase64::decode($parts[1]);
        if (null === $nonce || null === $sealed || 12 !== \strlen($nonce) || \strlen($sealed) < 16) {
            throw PaymentException::unavailable($failure);
        }
        $plain = openssl_decrypt(substr($sealed, 0, -16), self::CIPHER, $key, \OPENSSL_RAW_DATA, $nonce, substr($sealed, -16));
        if (false === $plain) {
            throw PaymentException::unavailable($failure);
        }

        return $plain;
    }

    private function key(string $malformed): string
    {
        $key = JavaBase64::decode($this->key);
        if (null === $key) {
            throw PaymentException::unavailable($malformed);
        }
        if (32 !== \strlen($key)) {
            throw PaymentException::unavailable('Configure the private payment token encryption key.');
        }

        return $key;
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}
