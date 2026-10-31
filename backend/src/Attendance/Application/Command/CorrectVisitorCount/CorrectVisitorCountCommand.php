<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\CorrectVisitorCount;

use App\Shared\Application\CQRS\Command;

final readonly class CorrectVisitorCountCommand implements Command
{
    public function __construct(
        public string $id,
        public int $count,
        public string $reason,
    ) {
    }
}
