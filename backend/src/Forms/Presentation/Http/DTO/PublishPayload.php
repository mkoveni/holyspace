<?php

declare(strict_types=1);

namespace App\Forms\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishPayload
{
    public function __construct(public bool $published, #[Assert\Positive] public int $expectedVersion)
    {
    }
}
