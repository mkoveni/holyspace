<?php

declare(strict_types=1);

namespace App\Attendance\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VisitorCorrectionPayload
{
    public function __construct(public int $count, #[Assert\NotBlank] public string $reason)
    {
    }
}
