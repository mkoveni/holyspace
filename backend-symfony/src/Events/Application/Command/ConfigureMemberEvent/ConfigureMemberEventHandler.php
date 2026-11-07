<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureMemberEvent;

use App\Events\Application\MemberEventActions;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class ConfigureMemberEventHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private MemberEventActions $actions)
    {
    }

    public function handle(ConfigureMemberEventCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.manage');
        $this->actions->configure($command->actorId, $command->eventId, $command->enabled, $command->expectedVersion);
    }
}
