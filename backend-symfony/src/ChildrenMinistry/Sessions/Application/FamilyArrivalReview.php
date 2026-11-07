<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class FamilyArrivalReview
{
    /** @param list<string> $issues */
    public function __construct(public FamilyChild $child, public array $issues)
    {
    }
}
