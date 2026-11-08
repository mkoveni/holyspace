<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateServingRole;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateServingRoleCommand implements Command
{
    /** @param list<string|null>|null $requiredCredentials */
    public function __construct(
        public string $actorId,
        public string $teamId,
        public int $expectedVersion,
        public ?string $name,
        public ?string $childrenRole,
        public ?array $requiredCredentials,
    ) {
    }
}
