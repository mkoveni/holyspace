<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\VerifyGuardianEmail;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> the verification id */
final readonly class VerifyGuardianEmailCommand implements Command
{
    public function __construct(public string $actorId, public string $communicationId, public string $contactId, public ?string $evidence, public DateTimeImmutable $validUntil)
    {
    }
}
