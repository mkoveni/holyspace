<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SupportNotePayload
{
    public function __construct(#[Assert\Length(max: 2000)] public string $note, #[Assert\PositiveOrZero] public int $expectedVersion)
    {
    }
}
