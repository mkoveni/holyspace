<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RevokePickupAuthorization;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokePickupAuthorizationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $id,
        public int $expectedVersion,
        public ?string $reason,
    ) {
    }
}
