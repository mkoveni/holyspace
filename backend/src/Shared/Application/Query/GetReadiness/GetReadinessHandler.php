<?php

declare(strict_types=1);

namespace App\Shared\Application\Query\GetReadiness;

use App\Shared\Application\ReadModel\ReadinessChecks;

final readonly class GetReadinessHandler
{
    public function __construct(private ReadinessChecks $reader)
    {
    }

    public function __invoke(GetReadinessQuery $message): array
    {
        return $this->reader->checks();
    }
}
