<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CorrectionPayload
{
    public function __construct(#[Assert\Choice(choices: ['present', 'absent', 'excused'])] public string $status, #[Assert\NotBlank] public string $reason)
    {
    }
}
