<?php

declare(strict_types=1);

namespace App\ServicePlanning\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlanPayload
{
    public function __construct(#[Assert\Uuid] public string $occurrenceId, #[Assert\NotBlank, Assert\Length(max: 200)] public string $title, #[Assert\Count(min: 1, max: 50)] public array $items, #[Assert\Positive] public ?int $expectedVersion = null)
    {
    }
}
