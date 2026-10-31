<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PaymentConfiguration
{
    public function __construct(
        #[Autowire(env: 'PAYMENTS_PUBLIC_URL')] public string $publicUrl,
        #[Autowire(env: 'PAYFAST_MODE')] public string $payfastMode,
        #[Autowire(env: 'PAYFAST_MERCHANT_ID')] public string $merchantId,
        #[Autowire(env: 'PAYFAST_MERCHANT_KEY')] public string $merchantKey,
        #[Autowire(env: 'PAYFAST_PASSPHRASE')] public string $passphrase,
        #[Autowire(env: 'PAYFAST_ITN_IP_RANGES')] public string $payfastIpRanges,
        #[Autowire(env: 'YOCO_MODE')] public string $yocoMode,
        #[Autowire(env: 'YOCO_SECRET_KEY')] public string $yocoKey,
        #[Autowire(env: 'YOCO_WEBHOOK_SECRET')] public string $yocoWebhookSecret,
    ) {
    }

    public function ready(string $provider): bool
    {
        $url = parse_url($this->publicUrl);
        if (($url['scheme'] ?? null) !== 'https' || !isset($url['host']) || isset($url['query']) || isset($url['fragment']) || isset($url['user'])) {
            return false;
        }

        return match ($provider) {
            'payfast' => in_array($this->payfastMode, ['test', 'live'], true) && '' !== $this->merchantId && '' !== $this->merchantKey && '' !== $this->passphrase && '' !== $this->payfastIpRanges,
            'yoco' => in_array($this->yocoMode, ['test', 'live'], true) && str_starts_with($this->yocoKey, 'test' === $this->yocoMode ? 'sk_test_' : 'sk_live_') && str_starts_with($this->yocoWebhookSecret, 'whsec_'),
            default => false,
        };
    }

    public function baseUrl(): string
    {
        return rtrim($this->publicUrl, '/');
    }
}
