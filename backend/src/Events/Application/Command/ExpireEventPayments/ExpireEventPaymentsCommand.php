<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ExpireEventPayments;

use App\Shared\Application\CQRS\Command;

final readonly class ExpireEventPaymentsCommand implements Command
{
    public function __construct(public string $eventId, public string $occurrenceId)
    {
    }
}
