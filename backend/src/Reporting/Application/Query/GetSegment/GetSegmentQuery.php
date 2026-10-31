<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetSegment;

use App\Shared\Application\CQRS\Query;

final readonly class GetSegmentQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
