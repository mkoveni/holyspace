<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\QueueGuardianEmail;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<string> the delivery id */
final readonly class QueueGuardianEmailCommand implements Command
{
    public function __construct(public string $actorId, public string $communicationId, public int $expectedVersion, public string $verificationId, public string $reviewHash, public ?DateTimeImmutable $scheduledAt)
    {
    }
}
