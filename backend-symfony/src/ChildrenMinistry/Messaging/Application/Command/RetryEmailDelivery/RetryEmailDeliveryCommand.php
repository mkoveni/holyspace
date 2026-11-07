<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\RetryEmailDelivery;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RetryEmailDeliveryCommand implements Command
{
    public function __construct(public string $actorId, public string $deliveryId, public int $expectedVersion, public ?string $reason)
    {
    }
}
