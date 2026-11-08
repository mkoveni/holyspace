<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use App\Shared\Domain\JavaString;
use DateTimeImmutable;

/**
 * A hosted giving checkout intent (Java `PaymentOrder`). The original intent (donor, fund, amount, provider, mode,
 * recurrence, configuration revision, request hash) is immutable in PostgreSQL; lifecycle columns change through the
 * repository, and every update advances the version by one (`payments_guard_order`).
 */
final readonly class PaymentOrder
{
    public const array PROVIDERS = ['payfast', 'yoco'];
    public const array MODES = ['test', 'live'];
    public const array STATUSES = ['PENDING', 'READY', 'PAID', 'FAILED'];
    public const array SUBSCRIPTION_STATUSES = ['NONE', 'PENDING', 'ACTIVE', 'PAUSED', 'LOCKED', 'CANCEL_REQUESTED', 'CANCELLED'];
    /** Checkout may still be opened or resumed. */
    public const array OPEN = ['PENDING', 'READY'];
    /** A monthly arrangement the donor or staff asked to end. */
    public const array CANCELLING = ['CANCEL_REQUESTED', 'CANCELLED'];

    private function __construct(
        public string $id,
        public string $donorId,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public string $mode,
        public bool $recurring,
        public string $status,
        public ?string $checkoutId,
        public ?string $checkoutUrl,
        public ?string $checkoutFields,
        public string $subscriptionStatus,
        public ?string $encryptedToken,
        public ?string $tokenHash,
        public ?string $reviewReason,
        public int $version,
        public string $requestHash,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
        public ?string $configurationId,
        public bool $selfService,
    ) {
        self::amount($amountMinor);
        if (!\in_array($provider, self::PROVIDERS, true) || !\in_array($mode, self::MODES, true)) {
            throw PaymentException::invalid('Choose a configured payment provider.');
        }
        if (!\in_array($status, self::STATUSES, true) || !\in_array($subscriptionStatus, self::SUBSCRIPTION_STATUSES, true) || $version < 0) {
            throw PaymentException::invalid('Invalid payment lifecycle state.');
        }
        if ($recurring && 'payfast' !== $provider) {
            throw PaymentException::invalid('Monthly giving uses Payfast.');
        }
    }

    /** A new checkout request: no provider checkout yet, a monthly arrangement awaits its first charge. */
    public static function start(
        string $id,
        string $donorId,
        string $fundId,
        int $amountMinor,
        string $provider,
        string $mode,
        bool $recurring,
        string $requestHash,
        string $createdBy,
        DateTimeImmutable $createdAt,
        ?string $configurationId,
        bool $selfService,
    ): self {
        return new self(
            $id,
            $donorId,
            $fundId,
            $amountMinor,
            $provider,
            $mode,
            $recurring,
            'PENDING',
            null,
            null,
            null,
            $recurring ? 'PENDING' : 'NONE',
            null,
            null,
            null,
            0,
            $requestHash,
            $createdBy,
            $createdAt,
            $configurationId,
            $selfService
        );
    }

    public static function reconstitute(
        string $id,
        string $donorId,
        string $fundId,
        int $amountMinor,
        string $provider,
        string $mode,
        bool $recurring,
        string $status,
        ?string $checkoutId,
        ?string $checkoutUrl,
        ?string $checkoutFields,
        string $subscriptionStatus,
        ?string $encryptedToken,
        ?string $tokenHash,
        ?string $reviewReason,
        int $version,
        string $requestHash,
        string $createdBy,
        DateTimeImmutable $createdAt,
        ?string $configurationId,
        bool $selfService,
    ): self {
        return new self(
            $id,
            $donorId,
            $fundId,
            $amountMinor,
            $provider,
            $mode,
            $recurring,
            $status,
            $checkoutId,
            $checkoutUrl,
            $checkoutFields,
            $subscriptionStatus,
            $encryptedToken,
            $tokenHash,
            $reviewReason,
            $version,
            $requestHash,
            $createdBy,
            $createdAt,
            $configurationId,
            $selfService
        );
    }

    public static function amount(int $amount): void
    {
        if ($amount < 500 || $amount > 100_000_000) {
            throw PaymentException::invalid('Enter ZAR 5.00 to 1,000,000.00.');
        }
    }

    /** Java `PaymentOrder.text`: required, trimmed, at most $maximum UTF-16 code units. */
    public static function text(?string $value, int $maximum, string $label): string
    {
        if (null === $value || JavaString::isBlank($value) || JavaString::length(JavaString::trim($value)) > $maximum) {
            throw PaymentException::invalid($label.' is required (at most '.$maximum.' characters).');
        }

        return JavaString::trim($value);
    }

    public function expected(int $expected): void
    {
        if ($this->version !== $expected) {
            throw PaymentException::conflict('This payment changed. Refresh before trying again.');
        }
    }

    public function isLive(): bool
    {
        return 'live' === $this->mode;
    }

    public function awaitingPayment(): bool
    {
        return \in_array($this->status, self::OPEN, true) && !\in_array($this->subscriptionStatus, self::CANCELLING, true);
    }

    /** @return array{id: string, status: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'status' => $this->status];
    }
}
