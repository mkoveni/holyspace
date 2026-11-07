<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

use DateTimeImmutable;

final readonly class ProgrammeView
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $currentPolicyId,
        public int $minStaff,
        public int $childrenPerStaff,
        public int $version,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
