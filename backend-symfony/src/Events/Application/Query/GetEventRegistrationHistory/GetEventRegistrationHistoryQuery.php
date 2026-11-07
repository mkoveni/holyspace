<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventRegistrationHistory;

final readonly class GetEventRegistrationHistoryQuery
{
    public function __construct(public string $registrationId, public int $limit, public int $offset)
    {
    }
}
