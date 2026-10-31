<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VisitorsPayload
{
    public function __construct(#[Assert\Uuid] public string $occurrenceId, #[Assert\Range(min: 0, max: 100000)] public int $count)
    {
    }
}
