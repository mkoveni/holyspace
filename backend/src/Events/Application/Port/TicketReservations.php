<?php

declare(strict_types=1);

namespace App\Events\Application\Port;

use App\Events\Application\ReadModel\TicketBookings;

interface TicketReservations extends TicketBookings
{
    public function reserve(string $occurrenceId, string $personId, string $orderId, string $expiresAt): void;

    public function confirm(string $occurrenceId, string $personId, string $orderId): bool;

    public function release(string $occurrenceId, string $personId, string $orderId): void;

    public function expire(): void;
}
