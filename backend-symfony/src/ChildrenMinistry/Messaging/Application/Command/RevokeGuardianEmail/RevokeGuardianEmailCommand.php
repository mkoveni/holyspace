<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RevokeGuardianEmail;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokeGuardianEmailCommand implements Command
{
    public function __construct(public string $actorId, public string $verificationId, public ?string $reason)
    {
    }
}
