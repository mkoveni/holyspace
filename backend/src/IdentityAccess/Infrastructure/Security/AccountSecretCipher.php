<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\SecretCipher;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(SecretCipher::class)]
final readonly class AccountSecretCipher implements SecretCipher
{
    public function __construct(#[Autowire('%kernel.secret%')] private string $encryptionKey)
    {
    }

    private function key(): string
    {
        return hash_hkdf('sha256', $this->encryptionKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'Holyspace authenticator secrets');
    }

    public function encrypt(string $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($value, $nonce, $this->key()));
    }

    public function decrypt(string $value): string
    {
        $binary = base64_decode($value, true);
        if (false === $binary || strlen($binary) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Authenticator secret is unavailable.');
        }

        return sodium_crypto_secretbox_open(substr($binary, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($binary, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key()) ?: throw new RuntimeException('Authenticator secret is unavailable.');
    }
}
