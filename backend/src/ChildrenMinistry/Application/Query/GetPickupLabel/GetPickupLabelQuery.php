<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetPickupLabel;

use App\Shared\Application\CQRS\Query;

final readonly class GetPickupLabelQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
