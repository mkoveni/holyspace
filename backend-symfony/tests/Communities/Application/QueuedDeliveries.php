<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use LogicException;

final class QueuedDeliveries implements EmailDeliveries
{
    /** @var list<DeliverySnapshot> */
    public array $queued = [];

    public function queue(string $sourceContext, DeliverySnapshot $approved): void
    {
        $this->queued[] = $approved;
    }

    public function cancel(string $actor, string $id, int $expected, string $reason): void
    {
        throw new LogicException('not used');
    }

    public function retry(string $actor, string $id, int $expected, string $reason): void
    {
        throw new LogicException('not used');
    }

    public function receipt(string $actor, string $id, string $messageId, string $key, string $status, string $reference): void
    {
        throw new LogicException('not used');
    }
}
