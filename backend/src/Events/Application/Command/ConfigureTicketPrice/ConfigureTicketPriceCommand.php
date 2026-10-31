<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureTicketPrice;

use App\Shared\Application\CQRS\Command;

final readonly class ConfigureTicketPriceCommand implements Command
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public ?int $amount,
        public int $version,
    ) {
    }
}
