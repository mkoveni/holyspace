<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordGift;

use App\Giving\Domain\Allocation;
use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class RecordGiftCommand implements Command
{
    /** @param list<Allocation|null>|null $allocations */
    public function __construct(
        public string $actorId,
        public string $requestId,
        public ?string $donorId,
        public int $amountMinor,
        public ?string $currency,
        public ?CalendarDate $receivedOn,
        public ?string $method,
        public ?string $externalReference,
        public ?array $allocations,
    ) {
    }
}
