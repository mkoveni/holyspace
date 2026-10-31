<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FormPayload
{
    public function __construct(#[Assert\NotBlank, Assert\Length(max: 200)] public string $title, #[Assert\Count(min: 1, max: 20)] public array $fields, #[Assert\Length(max: 2000)] public string $description = '', #[Assert\Uuid] public ?string $eventId = null, #[Assert\Uuid] public ?string $occurrenceId = null, #[Assert\Positive] public ?int $expectedVersion = null)
    {
    }
}
