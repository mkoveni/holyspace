<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use DateTimeImmutable;

final readonly class DonorPayment
{
    public function __construct(
        public string $id,
        public string $fundId,
        public int $amountMinor,
        public string $provider,
        public string $mode,
        public bool $recurring,
        public string $status,
        public string $subscriptionStatus,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
