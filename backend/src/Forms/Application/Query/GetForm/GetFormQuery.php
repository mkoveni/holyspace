<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetForm;

use App\Shared\Application\CQRS\Query;

final readonly class GetFormQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
