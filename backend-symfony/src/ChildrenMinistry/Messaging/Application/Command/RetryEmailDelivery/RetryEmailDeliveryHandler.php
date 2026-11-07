<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RetryEmailDelivery;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\EmailDeliveries;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

/** Needs both sending and safeguarding permission, for Children Ministry deliveries only. Communications applies the lifecycle rules. */
final readonly class RetryEmailDeliveryHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EmailDeliveries $deliveries, private DeliveryReader $reader)
    {
    }

    public function handle(RetryEmailDeliveryCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.communications.send');
        $this->authorization->requirePermission('children.safeguarding');
        if (!$this->reader->belongsTo($command->deliveryId, 'children-ministry')) {
            throw ChildrenException::missing('Delivery');
        }
        $this->deliveries->retry($command->actorId, $command->deliveryId, $command->expectedVersion, (string) $command->reason);

        return null;
    }
}
