<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListTemplateRevisions;

final readonly class ListTemplateRevisionsQuery
{
    public function __construct(public string $id, public int $limit, public int $offset)
    {
    }
}
