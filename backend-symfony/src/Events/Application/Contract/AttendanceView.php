<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class AttendanceView
{
    public function __construct(
        public string $id,
        public string $registrationId,
        public DateTimeImmutable $arrivedAt,
        public string $status,
        public int $version,
    ) {
    }
}
