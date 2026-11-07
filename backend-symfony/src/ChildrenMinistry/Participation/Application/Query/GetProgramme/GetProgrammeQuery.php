<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\GetProgramme;

final readonly class GetProgrammeQuery
{
    public function __construct(
        public string $programmeId,
    ) {
    }
}
