<?php

declare(strict_types=1);

namespace App\Events\Domain;

/** What was bought, frozen at purchase: later edits to the ticket type never change history. */
final readonly class TicketOrderLine
{
    public function __construct(public string $id, public string $ticketTypeId, public string $typeName, public int $quantity, public int $unitPriceMinor)
    {
    }

    public function lineMinor(): int
    {
        return $this->quantity * $this->unitPriceMinor;
    }
}
