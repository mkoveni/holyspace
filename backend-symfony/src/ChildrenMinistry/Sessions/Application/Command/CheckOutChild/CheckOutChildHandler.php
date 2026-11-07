<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CheckOutChild;

use App\ChildrenMinistry\Sessions\Application\Handover;
use App\ChildrenMinistry\Sessions\Application\HandoverOutcome;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class CheckOutChildHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private Handover $handover)
    {
    }

    public function handle(CheckOutChildCommand $command): HandoverOutcome
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.station');

        return $this->handover->collect($command->actorId, $command->attendanceId, $command->expectedVersion, $command->requestId, $command->collectorId, $command->identityVerified, $command->pickupCode, null);
    }
}
