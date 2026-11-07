<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RecordDeliveryReceipt;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Communications\Application\Contract\DeliveryReader;
use App\Communications\Application\Contract\EmailDeliveries;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;

/** A trusted provider relay (a dedicated permission, a live session) reports the outcome of a Children Ministry delivery. */
final readonly class RecordDeliveryReceiptHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private EmailDeliveries $deliveries, private DeliveryReader $reader)
    {
    }

    public function handle(RecordDeliveryReceiptCommand $command): null
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.communications.receipts');
        if (!$this->reader->belongsTo($command->deliveryId, 'children-ministry')) {
            throw ChildrenException::missing('Delivery');
        }
        $this->deliveries->receipt($command->actorId, $command->deliveryId, $command->messageId, $command->eventKey, $command->status, $command->reference);

        return null;
    }
}
