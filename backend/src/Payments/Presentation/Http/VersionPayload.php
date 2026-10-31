<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VersionPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion)
    {
    }
}
