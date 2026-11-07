<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ApproveEventNotification;

use App\Events\Application\EventMessageApproval;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

final readonly class ApproveEventNotificationHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EventMessageApproval $approval)
    {
    }

    /** @return string the delivery id */
    public function handle(ApproveEventNotificationCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.communications.send');

        return $this->approval->approve($command->actorId, $command->requestId, $command->registrationId, $command->contactId, $command->purpose, $command->message, $command->reviewHash, $command->verificationEvidence);
    }
}
