<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\AssignPledgeGift;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class AssignPledgeGiftCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $pledgeId,
        public string $giftId,
        public int $expectedVersion,
        public int $amountMinor,
    ) {
    }
}
