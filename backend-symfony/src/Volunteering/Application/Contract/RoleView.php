<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

final readonly class RoleView
{
    /** @param list<string> $requiredCredentials credential type ids */
    public function __construct(
        public string $id,
        public string $teamId,
        public string $childrenRole,
        public bool $active,
        public array $requiredCredentials,
    ) {
    }
}
