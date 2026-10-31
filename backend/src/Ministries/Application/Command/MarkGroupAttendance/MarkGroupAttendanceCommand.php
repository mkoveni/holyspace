<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\MarkGroupAttendance;

use App\Shared\Application\CQRS\Command;

final readonly class MarkGroupAttendanceCommand implements Command
{
    public function __construct(
        public string $groupId,
        public string $id,
        public string $personId,
        public string $status,
        public ?int $expectedVersion,
        public ?string $reason,
    ) {
    }
}
