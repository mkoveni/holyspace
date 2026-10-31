<?php

declare(strict_types=1);

namespace App\ServicePlanning\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishPayload
{
    public function __construct(#[Assert\Positive] public int $expectedVersion, public bool $published)
    {
    }
}
