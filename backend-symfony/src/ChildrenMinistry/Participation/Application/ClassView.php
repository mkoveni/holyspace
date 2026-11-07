<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

final readonly class ClassView
{
    public function __construct(
        public string $id,
        public string $programmeId,
        public string $name,
        public int $minAgeMonths,
        public int $maxAgeMonths,
    ) {
    }
}
