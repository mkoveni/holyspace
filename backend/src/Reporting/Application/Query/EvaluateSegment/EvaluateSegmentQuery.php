<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\EvaluateSegment;

use App\Shared\Application\CQRS\Query;

final readonly class EvaluateSegmentQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}
