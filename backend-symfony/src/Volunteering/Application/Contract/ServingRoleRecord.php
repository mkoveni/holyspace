<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java's `ServingRole` record as the team views return it. */
final readonly class ServingRoleRecord
{
    /** @param list<string> $requiredCredentials credential type ids */
    public function __construct(
        public string $id,
        public string $teamId,
        public string $name,
        public string $childrenRole,
        public bool $active,
        public array $requiredCredentials,
    ) {
    }
}
