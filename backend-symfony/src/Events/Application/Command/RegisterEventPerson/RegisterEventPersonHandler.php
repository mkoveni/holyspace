<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterEventPerson;

use App\Events\Application\EventRegistrations;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class RegisterEventPersonHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventRegistrations $registrations)
    {
    }

    /** @return string the registration id */
    public function handle(RegisterEventPersonCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.registrations.manage');

        return $this->registrations->register($command->actorId, $command->requestId, $command->occurrenceId, $command->personId, $command->expectedVersion, false);
    }
}
