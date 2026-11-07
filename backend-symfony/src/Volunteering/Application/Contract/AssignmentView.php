<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

final readonly class AssignmentView
{
    public function __construct(public string $id, public string $shiftId, public string $volunteerId, public string $status)
    {
    }
}
