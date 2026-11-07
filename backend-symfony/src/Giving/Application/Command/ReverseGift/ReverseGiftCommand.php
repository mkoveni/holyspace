<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReverseGift;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class ReverseGiftCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $giftId,
        public ?CalendarDate $reversedOn,
        public ?string $reason,
    ) {
    }
}
