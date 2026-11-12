<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface VisitLinkReader
{
    /** @return array{enabled: bool, generation: int}|null */
    public function state(string $occurrenceId): ?array;
}
