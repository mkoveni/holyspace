<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Query\ListClasses;

final readonly class ListClassesQuery
{
    public function __construct(
        public string $programmeId,
    ) {
    }
}
