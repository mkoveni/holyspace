<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancelMeetingPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion, #[Assert\NotBlank, Assert\Length(max: 1000)] public string $reason)
    {
    }
}
