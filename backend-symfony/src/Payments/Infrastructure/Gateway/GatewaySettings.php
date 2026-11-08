<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Domain\HttpsOrigin;
use App\Payments\Domain\JavaBase64;
use App\Payments\Domain\PaymentException;
use SensitiveParameter;

/**
 * Provider connection values (Java `PaymentSettings`): either the deployment bootstrap read from the environment
 * (PAYMENTS_PUBLIC_URL, PAYFAST_*, YOCO_*, PAYMENTS_TOKEN_KEY, PAYMENTS_BACKEND_URL) or one stored revision,
 * decrypted for a single provider call. Never logged (`__debugInfo` redacts).
 */
final readonly class GatewaySettings
{
    public string $backendUrl;

    public function __construct(
        public string $publicUrl,
        public string $payfastMode,
        #[SensitiveParameter] public string $merchantId,
        #[SensitiveParameter] public string $merchantKey,
        #[SensitiveParameter] public string $passphrase,
        public string $ipRanges,
        public string $yocoMode,
        #[SensitiveParameter] public string $yocoKey,
        #[SensitiveParameter] public string $yocoWebhookSecret,
        #[SensitiveParameter] public string $vaultKey,
        string $backendUrl = '',
    ) {
        $this->backendUrl = '' === $backendUrl ? $publicUrl : $backendUrl;
    }

    public function available(string $provider): bool
    {
        if (!self::validBase($this->publicUrl) || !self::validBase($this->backendUrl)) {
            return false;
        }
        if ('payfast' === $provider) {
            return \in_array($this->payfastMode, ['test', 'live'], true) && '' !== trim($this->merchantId)
                && '' !== trim($this->merchantKey) && '' !== trim($this->passphrase) && '' !== trim($this->ipRanges) && $this->validVault();
        }

        return 'yoco' === $provider && \in_array($this->yocoMode, ['test', 'live'], true)
            && str_starts_with($this->yocoKey, 'sk_'.$this->yocoMode.'_') && str_starts_with($this->yocoWebhookSecret, 'whsec_');
    }

    public function mode(string $provider): string
    {
        return 'payfast' === $provider ? $this->payfastMode : $this->yocoMode;
    }

    public function validVault(): bool
    {
        return 32 === \strlen((string) JavaBase64::decode($this->vaultKey));
    }

    public function backendBase(): string
    {
        if (!self::validBase($this->backendUrl)) {
            throw PaymentException::unavailable('Configure the public HTTPS backend URL.');
        }

        return rtrim($this->backendUrl, '/');
    }

    public function base(): string
    {
        if (!self::validBase($this->publicUrl)) {
            throw PaymentException::unavailable('Configure the public HTTPS frontend URL.');
        }

        return rtrim($this->publicUrl, '/');
    }

    private static function validBase(string $value): bool
    {
        return null !== HttpsOrigin::host($value);
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}
