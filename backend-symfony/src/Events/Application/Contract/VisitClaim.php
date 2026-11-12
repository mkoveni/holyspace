<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class VisitClaim
{
    public function __construct(public string $occurrenceId, public int $generation)
    {
    }
}
