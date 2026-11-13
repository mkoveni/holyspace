<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetForm;

final readonly class GetFormQuery
{
    public function __construct(public string $id)
    {
    }
}
