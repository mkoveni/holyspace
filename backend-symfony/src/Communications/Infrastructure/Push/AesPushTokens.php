<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Push;

use App\Communications\Application\Contract\PushTokens;
use App\Communications\Domain\CommunicationException;
use SensitiveParameter;

/**
 * AES-256-GCM with the Java format, so rows written by either backend read in both:
 * `base64(nonce12):base64(ciphertext || tag16)`, additional data `holyspace-push-v1`, key `PUSH_TOKEN_KEY` (base64).
 */
final readonly class AesPushTokens implements PushTokens
{
    private const string CIPHER = 'aes-256-gcm';
    private const string AAD = 'holyspace-push-v1';
    private const int TAG_BYTES = 16;

    private string $key;

    public function __construct(#[SensitiveParameter] string $key)
    {
        $decoded = base64_decode($key, true);
        $this->key = false === $decoded ? '' : $decoded;
    }

    public function available(): bool
    {
        return 32 === strlen($this->key);
    }

    public function encrypt(#[SensitiveParameter] string $token): string
    {
        $this->requireKey();
        $nonce = random_bytes(12);
        $tag = '';
        $encoded = openssl_encrypt($token, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD, self::TAG_BYTES);
        if (false === $encoded) {
            throw CommunicationException::conflict('Push token encryption is unavailable.');
        }

        return base64_encode($nonce).':'.base64_encode($encoded.$tag);
    }

    public function decrypt(string $encrypted): string
    {
        $this->requireKey();
        $parts = explode(':', $encrypted);
        $nonce = 2 === count($parts) ? base64_decode($parts[0], true) : false;
        $sealed = 2 === count($parts) ? base64_decode($parts[1], true) : false;
        if (false === $nonce || false === $sealed || 12 !== strlen($nonce) || strlen($sealed) < self::TAG_BYTES) {
            throw CommunicationException::conflict('Push token decryption is unavailable.');
        }
        $token = openssl_decrypt(substr($sealed, 0, -self::TAG_BYTES), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, substr($sealed, -self::TAG_BYTES), self::AAD);
        if (false === $token) {
            throw CommunicationException::conflict('Push token decryption is unavailable.');
        }

        return $token;
    }

    private function requireKey(): void
    {
        if (!$this->available()) {
            throw CommunicationException::conflict('Configure the private push token encryption key.');
        }
    }

    /** @return array<string, never> the key is never dumped */
    public function __debugInfo(): array
    {
        return [];
    }
}
