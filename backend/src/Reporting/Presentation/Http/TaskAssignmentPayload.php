<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskAssignmentPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion, #[Assert\Uuid] public string $assigneeId, #[Assert\Date] public string $dueOn, #[Assert\NotBlank] #[Assert\Length(max: 1000)] public string $reason)
    {
    }
}
