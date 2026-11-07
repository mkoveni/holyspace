<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateReconciliation;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> */
final readonly class CreateReconciliationCommand implements Command
{
    /** @param list<string|null>|null $giftIds */
    public function __construct(
        public string $actorId,
        public string $requestId,
        public ?string $reference,
        public int $expectedMinor,
        public ?string $currency,
        public ?CalendarDate $reconciledOn,
        public ?string $reason,
        public ?array $giftIds,
    ) {
    }
}
