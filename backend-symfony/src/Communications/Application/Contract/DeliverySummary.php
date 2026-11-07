<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

/** A delivery without its recipient or content, safe for lists. */
final readonly class DeliverySummary
{
    public function __construct(
        public string $id,
        public string $communicationId,
        public string $status,
        public DateTimeImmutable $scheduledAt,
        public int $attempts,
        public string $errorCode,
        public bool $captured,
        public int $version,
    ) {
    }
}
