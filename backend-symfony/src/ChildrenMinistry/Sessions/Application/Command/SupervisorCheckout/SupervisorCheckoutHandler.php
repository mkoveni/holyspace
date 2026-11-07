<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SupervisorCheckout;

use App\ChildrenMinistry\Sessions\Application\Handover;
use App\ChildrenMinistry\Sessions\Application\HandoverOutcome;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

/** A supervisor releases a child without a pickup code, which needs a recorded reason. */
final readonly class SupervisorCheckoutHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private Handover $handover)
    {
    }

    public function handle(SupervisorCheckoutCommand $command): HandoverOutcome
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.supervise');

        return $this->handover->collect($command->actorId, $command->attendanceId, $command->expectedVersion, $command->requestId, $command->collectorId, $command->identityVerified, null, ChildrenRules::text($command->reason, 500));
    }
}
