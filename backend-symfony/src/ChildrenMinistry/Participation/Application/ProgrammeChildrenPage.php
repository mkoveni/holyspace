<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

final readonly class ProgrammeChildrenPage
{
    /** @param list<ProgrammeChild> $children */
    public function __construct(public array $children, public int $total)
    {
    }
}
