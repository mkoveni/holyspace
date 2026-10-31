<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EntryPayload
{
    public function __construct(#[Assert\Uuid] public string $occurrenceId, #[Assert\Uuid] public string $personId, #[Assert\Choice(choices: ['present', 'absent', 'excused'])] public string $status = 'present')
    {
    }
}
