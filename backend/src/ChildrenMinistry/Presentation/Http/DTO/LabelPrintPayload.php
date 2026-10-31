<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LabelPrintPayload
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion, #[Assert\Length(max: 500)] public string $replacementReason = '')
    {
    }
}
