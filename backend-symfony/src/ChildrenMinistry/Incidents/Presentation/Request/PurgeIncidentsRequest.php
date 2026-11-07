<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PurgeIncidentsRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $limit,
    ) {
    }
}
