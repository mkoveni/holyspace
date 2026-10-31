<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\PreviewSegment;

use App\Shared\Application\CQRS\Query;

final readonly class PreviewSegmentQuery implements Query
{
    public function __construct(public array $filters)
    {
    }
}
