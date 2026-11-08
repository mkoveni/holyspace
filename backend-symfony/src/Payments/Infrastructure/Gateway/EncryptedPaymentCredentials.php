<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\SubscriptionVault;
use App\Payments\Domain\JavaBase64;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderCredentials;
use JsonException;
use SensitiveParameter;

/** Provider credentials as the JSON Java's Jackson writes, sealed by the subscription vault. */
final readonly class EncryptedPaymentCredentials implements PaymentCredentialVault
{
    public function __construct(private SubscriptionVault $vault, #[SensitiveParameter] private string $key)
    {
    }

    public function ready(): bool
    {
        return 32 === \strlen((string) JavaBase64::decode($this->key));
    }

    public function encrypt(ProviderCredentials $credentials): string
    {
        return $this->vault->encrypt(json_encode([
            'merchantId' => $credentials->merchantId,
            'merchantKey' => $credentials->merchantKey,
            'passphrase' => $credentials->passphrase,
            'yocoKey' => $credentials->yocoKey,
            'webhookSecret' => $credentials->webhookSecret,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    public function decrypt(string $encrypted): ProviderCredentials
    {
        try {
            $values = json_decode($this->vault->decrypt($encrypted), true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PaymentException::unavailable('Payment token could not be decrypted. Check the configured key.');
        }
        $field = static function (string $name) use ($values): ?string {
            $value = \is_array($values) ? ($values[$name] ?? null) : null;

            return \is_string($value) ? $value : null;
        };

        return new ProviderCredentials($field('merchantId'), $field('merchantKey'), $field('passphrase'), $field('yocoKey'), $field('webhookSecret'));
    }
}
