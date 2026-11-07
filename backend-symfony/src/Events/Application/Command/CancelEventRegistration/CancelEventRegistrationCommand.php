<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelEventRegistration;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CancelEventRegistrationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $registrationId,
        public int $expectedVersion,
        public string $reason,
    ) {
    }
}
