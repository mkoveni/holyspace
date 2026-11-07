<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Publish or archive: only the version the caller saw. */
final readonly class ChangeTemplateRequest
{
    public function __construct(#[Assert\PositiveOrZero] public int $expectedVersion)
    {
    }
}
