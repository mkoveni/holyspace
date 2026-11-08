<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/** Java `ServingViews.Shifts`. */
final readonly class ShiftPage
{
    /** @param list<ShiftDetails> $shifts */
    public function __construct(public array $shifts, public int $total)
    {
    }
}
