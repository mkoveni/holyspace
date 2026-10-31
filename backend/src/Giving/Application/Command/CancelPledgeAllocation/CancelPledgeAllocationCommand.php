<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledgeAllocation;

use App\Shared\Application\CQRS\Command;

final readonly class CancelPledgeAllocationCommand implements Command
{
    public function __construct(
        public string $id,
        public int $expectedVersion,
        public string $reason,
    ) {
    }
}
