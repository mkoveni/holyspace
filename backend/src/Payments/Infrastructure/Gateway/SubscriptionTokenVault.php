<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Port\SubscriptionTokens;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(SubscriptionTokens::class)]
final readonly class SubscriptionTokenVault implements SubscriptionTokens
{
    public function __construct(#[Autowire('%kernel.secret%')] private string $secret)
    {
    }

    private function key(): string
    {
        return hash_hkdf('sha256', $this->secret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'Holyspace payment subscription tokens');
    }

    public function encrypt(string $token): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($token, $nonce, $this->key()));
    }

    public function decrypt(string $encrypted): string
    {
        $value = base64_decode($encrypted, true);
        if (false === $value || strlen($value) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Payment token cannot be decrypted.');
        }
        $token = sodium_crypto_secretbox_open(substr($value, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($value, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());

        return false === $token ? throw new RuntimeException('Payment token cannot be decrypted.') : $token;
    }
}
