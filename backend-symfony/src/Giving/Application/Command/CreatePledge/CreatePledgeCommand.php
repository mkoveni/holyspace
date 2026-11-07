<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreatePledge;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class CreatePledgeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public ?string $donorId,
        public ?string $fundId,
        public int $amountMinor,
        public ?string $currency,
        public ?CalendarDate $startsOn,
        public ?CalendarDate $endsOn,
    ) {
    }
}
