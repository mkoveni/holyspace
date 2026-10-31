<?php

declare(strict_types=1);

namespace App\MemberPortal\Presentation\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancellationPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion)
    {
    }
}
