<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\ListForms;

final readonly class ListFormsQuery
{
    public function __construct(public int $limit, public int $offset)
    {
    }
}
