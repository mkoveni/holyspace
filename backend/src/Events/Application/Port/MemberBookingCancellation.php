<?php

declare(strict_types=1);

namespace App\Events\Application\Port;

use App\Events\Application\ReadModel\MemberBookings;

interface MemberBookingCancellation extends MemberBookings
{
    public function cancel(string $personId, string $registrationId, int $expectedVersion): array;
}
