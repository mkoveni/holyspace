<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\UpdateServingRole;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateServingRoleCommand implements Command
{
    /** @param list<string|null>|null $requiredCredentials */
    public function __construct(
        public string $actorId,
        public string $teamId,
        public string $roleId,
        public int $expectedVersion,
        public ?string $name,
        public ?string $childrenRole,
        public bool $active,
        public ?array $requiredCredentials,
    ) {
    }
}
