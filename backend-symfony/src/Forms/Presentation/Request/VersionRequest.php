<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VersionRequest
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion)
    {
    }
}
