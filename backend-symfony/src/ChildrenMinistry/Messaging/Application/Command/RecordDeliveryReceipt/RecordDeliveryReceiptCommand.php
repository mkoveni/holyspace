<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RecordDeliveryReceipt;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RecordDeliveryReceiptCommand implements Command
{
    public function __construct(public string $actorId, public string $deliveryId, public string $messageId, public string $eventKey, public string $status, public string $reference)
    {
    }
}
