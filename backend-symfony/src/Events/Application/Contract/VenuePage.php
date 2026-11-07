<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class VenuePage
{
    /** @param list<VenueView> $venues */
    public function __construct(public array $venues, public int $total)
    {
    }
}
