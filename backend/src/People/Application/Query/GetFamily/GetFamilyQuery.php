<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetFamily;

use App\Shared\Application\CQRS\Query;

final readonly class GetFamilyQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
