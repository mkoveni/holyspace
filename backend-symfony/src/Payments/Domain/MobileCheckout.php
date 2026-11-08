<?php

declare(strict_types=1);

namespace App\Payments\Domain;

use DateTimeImmutable;

/** One-time, five-minute native-to-browser checkout handoff bound to a live session (only the token hash is stored). */
final readonly class MobileCheckout
{
    public function __construct(
        public string $tokenHash,
        public string $orderId,
        public string $accountId,
        public string $sessionId,
        public string $encryptedCheckout,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
    ) {
    }

    public function usableAt(DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $this->expiresAt > $now;
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}
