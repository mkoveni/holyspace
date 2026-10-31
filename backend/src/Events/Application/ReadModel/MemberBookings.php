<?php

declare(strict_types=1);

namespace App\Events\Application\ReadModel;

interface MemberBookings
{
    public function list(string $personId): array;
}
