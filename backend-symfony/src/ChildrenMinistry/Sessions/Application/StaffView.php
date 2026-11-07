<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\StaffRole;

final readonly class StaffView
{
    /** @param list<string> $issues reasons this worker cannot be counted towards capacity */
    public function __construct(
        public string $personId,
        public string $name,
        public StaffRole $role,
        public bool $present,
        public array $issues,
    ) {
    }

    public function counts(): bool
    {
        return $this->present && [] === $this->issues;
    }
}
