<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Request;

/** Java `VolunteeringApi.Expire`; a missing limit is 0, which the handler refuses. */
final readonly class ExpireRequest
{
    public function __construct(
        public int $limit = 0,
    ) {
    }
}
