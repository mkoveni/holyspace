<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureEventRegistration;

use App\Events\Application\EventRegistrations;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class ConfigureEventRegistrationHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventRegistrations $registrations)
    {
    }

    public function handle(ConfigureEventRegistrationCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.registrations.manage');
        $this->registrations->configure($command->actorId, $command->occurrenceId, $command->expectedVersion, $command->open, $command->capacity, $command->waitlistEnabled, $command->reason);
    }
}
