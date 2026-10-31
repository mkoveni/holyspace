<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RulePayload
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 200)] public string $name, #[Assert\Uuid] public string $segmentId, #[Assert\Positive] public int $segmentVersion, #[Assert\Uuid] public string $assigneeId, #[Assert\NotBlank] #[Assert\Length(max: 200)] public string $taskTitle, #[Assert\Range(min: 0, max: 90)] public int $dueInDays = 7, public bool $active = false, #[Assert\Positive] public ?int $expectedVersion = null)
    {
    }
}
