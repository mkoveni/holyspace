<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelEventRegistration;

use App\Events\Application\EventRegistrations;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CancelEventRegistrationHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventRegistrations $registrations)
    {
    }

    public function handle(CancelEventRegistrationCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.registrations.manage');
        $this->registrations->cancel($command->actorId, $command->registrationId, $command->expectedVersion, $command->reason);
    }
}
