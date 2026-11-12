<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetVisitLink;

final readonly class GetVisitLinkQuery
{
    public function __construct(public string $occurrenceId)
    {
    }
}
