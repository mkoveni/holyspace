<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RecordGuardianContact;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RecordGuardianContactCommand implements Command
{
    public function __construct(public string $actorId, public string $communicationId, public int $expectedVersion, public ?string $status, public ?string $reference)
    {
    }
}
