<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CancelPledge;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CancelPledgeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $pledgeId,
        public int $expectedVersion,
        public ?string $reason,
    ) {
    }
}
