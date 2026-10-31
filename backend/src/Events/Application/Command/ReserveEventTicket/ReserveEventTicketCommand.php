<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReserveEventTicket;

use App\Shared\Application\CQRS\Command;

final readonly class ReserveEventTicketCommand implements Command
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public string $personId,
        public string $orderId,
        public string $expires,
    ) {
    }
}
