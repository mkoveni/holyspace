<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetTemplate;

final readonly class GetTemplateQuery
{
    public function __construct(public string $id)
    {
    }
}
