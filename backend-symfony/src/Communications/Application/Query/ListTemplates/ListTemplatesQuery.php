<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListTemplates;

final readonly class ListTemplatesQuery
{
    public function __construct(public string $search, public int $limit, public int $offset)
    {
    }
}
