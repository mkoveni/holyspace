<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\SetAccountStatus;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetAccountStatusCommand implements Command
{
    public function __construct(public string $actorId, public string $id, public bool $enabled, public ?int $expectedVersion = null)
    {
    }
}
