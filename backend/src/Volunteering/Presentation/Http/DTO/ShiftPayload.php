<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShiftPayload
{
    public function __construct(#[Assert\Uuid] public string $occurrenceId, #[Assert\Uuid] public string $roleId, public string $startsAt, public string $endsAt, #[Assert\Range(min: 1, max: 100)] public int $positions, #[Assert\Choice(choices: ['open', 'needs_review', 'cancelled'])] public string $status = 'open')
    {
    }
}
