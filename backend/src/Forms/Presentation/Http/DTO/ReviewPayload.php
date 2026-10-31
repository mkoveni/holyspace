<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReviewPayload
{
    public function __construct(#[Assert\Choice(choices: ['approved', 'rejected'])] public string $status, #[Assert\NotBlank, Assert\Length(max: 1000)] public string $note, #[Assert\Positive] public int $expectedVersion, #[Assert\Uuid] public ?string $personId = null)
    {
    }
}
