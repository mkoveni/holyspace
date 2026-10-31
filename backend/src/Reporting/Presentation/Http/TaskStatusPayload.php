<?php

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskStatusPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion, #[Assert\Choice(choices: ['open', 'in_progress', 'completed', 'cancelled'])] public string $status, #[Assert\Length(max: 1000)] public string $note = '')
    {
    }
}
