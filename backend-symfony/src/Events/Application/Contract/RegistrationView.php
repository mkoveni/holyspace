<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class RegistrationView
{
    public function __construct(
        public string $id,
        public string $occurrenceId,
        public string $personId,
        public string $status,
        public int $position,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
