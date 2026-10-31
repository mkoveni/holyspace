<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetRegistrationOverview;

use App\Shared\Application\CQRS\Query;

final readonly class GetRegistrationOverviewQuery implements Query
{
    public function __construct(
        public string $eventId,
        public string $occurrenceId,
        public ?string $after = null,
        public int $limit = 50,
    ) {
    }
}
