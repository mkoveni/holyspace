<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\AllocatePledge;

use App\Shared\Application\CQRS\Command;

final readonly class AllocatePledgeCommand implements Command
{
    public function __construct(
        public string $pledgeId,
        public string $giftId,
        public int $amountMinor,
        public string $reference,
    ) {
    }
}
