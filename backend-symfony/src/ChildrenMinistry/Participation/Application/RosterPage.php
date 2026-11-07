<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application;

final readonly class RosterPage
{
    /** @param list<EnrolmentView> $enrolments */
    public function __construct(public array $enrolments, public int $total)
    {
    }
}
