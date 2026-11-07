<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class CloseoutView
{
    public function __construct(
        public string $occurrenceId,
        public string $status,
        public int $version,
        public string $note,
        public string $followUp,
        public string $reviewHash,
        public ?string $updatedBy,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
