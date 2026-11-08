<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\JavaString;
use DateTimeImmutable;

/**
 * One immutable configuration revision of a provider environment. Credentials are only ever held encrypted; the flags
 * say which ones are set. New orders pin the revision they were created with.
 */
final readonly class ProviderConfiguration
{
    public string $publicUrl;
    public string $backendUrl;
    public string $ipRanges;

    public function __construct(
        public string $id,
        public string $provider,
        public string $mode,
        public int $sequence,
        string $publicUrl,
        ?string $ipRanges,
        public string $encryptedCredentials,
        public string $credentialHash,
        public bool $merchantIdSet,
        public bool $merchantKeySet,
        public bool $passphraseSet,
        public bool $yocoKeySet,
        public bool $webhookSecretSet,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        ?string $backendUrl,
    ) {
        ProviderSettings::validate($provider, $mode);
        $this->publicUrl = HttpsOrigin::public($publicUrl);
        $this->backendUrl = HttpsOrigin::public($backendUrl ?? $this->publicUrl);
        $this->ipRanges = JavaString::trim($ipRanges ?? '');
        if (JavaString::length($this->ipRanges) > 2000) {
            throw PaymentException::invalid('Too many trusted notification networks.');
        }
    }

    /** Whether the saved credential flags and networks are enough for this provider to take payments. */
    public function complete(): bool
    {
        return 'payfast' === $this->provider
            ? $this->merchantIdSet && $this->merchantKeySet && $this->passphraseSet && !JavaString::isBlank($this->ipRanges)
            : $this->yocoKeySet && $this->webhookSecretSet;
    }

    /** @return array{id: string, provider: string, mode: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'provider' => $this->provider, 'mode' => $this->mode];
    }
}
