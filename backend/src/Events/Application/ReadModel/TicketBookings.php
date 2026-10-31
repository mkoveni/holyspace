<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

interface TicketBookings
{
    public function offers(): array;

    public function price(string $occurrenceId): int;

    public function status(string $occurrenceId, string $personId, string $orderId): ?string;
}
