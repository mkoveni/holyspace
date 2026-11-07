<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

final readonly class VolunteerView
{
    public function __construct(public string $id, public string $personId, public string $status, public int $availabilityRevision)
    {
    }
}
