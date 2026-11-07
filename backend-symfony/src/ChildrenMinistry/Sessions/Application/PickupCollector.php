<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** A person who may collect the child now, named without any guardian evidence. */
final readonly class PickupCollector
{
    public function __construct(public string $authorizationId, public string $collectorId, public string $name, public DateTimeImmutable $validUntil)
    {
    }
}
