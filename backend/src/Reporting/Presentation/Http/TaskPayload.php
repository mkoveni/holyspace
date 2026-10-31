<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskPayload
{
    public function __construct(#[Assert\Uuid] public string $personId, #[Assert\Uuid] public string $assigneeId, #[Assert\NotBlank] #[Assert\Length(max: 200)] public string $title, #[Assert\Date] public string $dueOn, #[Assert\Uuid] public ?string $segmentId = null)
    {
    }
}
