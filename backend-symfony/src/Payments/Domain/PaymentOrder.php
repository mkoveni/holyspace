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

    /** The system actor recorded as the creator of public (guest) orders, gifts and confirmations: there is no account. */
    public const string GUEST_ACTOR = '00000000-0000-0000-0000-0000000000a1';
    /** A monthly arrangement the donor or staff asked to end. */
    public const array CANCELLING = ['CANCEL_REQUESTED', 'CANCELLED'];

    private function __construct(
        public string $id,
        public ?string $donorId,
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
        public ?string $guestName = null,
        public ?string $guestEmailEncrypted = null,
        public ?string $guestEmailHash = null,
        public ?string $guestAccessHash = null,
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
        if (null === $donorId && ($recurring || $selfService || null === $guestEmailEncrypted || null === $guestEmailHash || null === $guestAccessHash)) {
            throw PaymentException::invalid('A public payment is one-off and needs a confirmation email.');
        }
    }

    /**
     * A public (guest) one-off checkout: no person, a compulsory confirmation email kept encrypted, and a random access
     * token (only its hash is stored) that lets the giver see the outcome.
     */
    public static function startGuest(
        string $id,
        string $fundId,
        int $amountMinor,
        string $provider,
        string $mode,
        string $requestHash,
        DateTimeImmutable $createdAt,
        ?string $configurationId,
        ?string $guestName,
        string $guestEmailEncrypted,
        string $guestEmailHash,
        string $guestAccessHash,
    ): self {
        return new self(
            $id,
            null,
            $fundId,
            $amountMinor,
            $provider,
            $mode,
            false,
            'PENDING',
            null,
            null,
            null,
            'NONE',
            null,
            null,
            null,
            0,
            $requestHash,
            self::GUEST_ACTOR,
            $createdAt,
            $configurationId,
            false,
            $guestName,
            $guestEmailEncrypted,
            $guestEmailHash,
            $guestAccessHash
        );
    }

    public function isGuest(): bool
    {
        return null === $this->donorId;
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
        ?string $donorId,
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
        ?string $guestName = null,
        ?string $guestEmailEncrypted = null,
        ?string $guestEmailHash = null,
        ?string $guestAccessHash = null,
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
            $selfService,
            $guestName,
            $guestEmailEncrypted,
            $guestEmailHash,
            $guestAccessHash
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
