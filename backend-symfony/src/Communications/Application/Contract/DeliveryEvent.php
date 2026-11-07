<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

use DateTimeImmutable;

final readonly class DeliveryEvent
{
    public function __construct(public string $id, public string $type, public DateTimeImmutable $occurredAt, public string $reference)
    {
    }
}
