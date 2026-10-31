<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GroupAttendancePayload
{
    public function __construct(#[Assert\Uuid] public string $personId, #[Assert\Choice(choices: ['present', 'absent', 'excused'])] public string $status, #[Assert\Positive] public ?int $expectedVersion = null, #[Assert\Length(max: 1000)] public ?string $reason = null)
    {
    }
}
