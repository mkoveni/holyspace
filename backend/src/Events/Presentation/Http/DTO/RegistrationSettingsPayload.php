<?php

declare(strict_types=1);

namespace App\Events\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegistrationSettingsPayload
{
    public function __construct(public bool $open, #[Assert\Range(min: 0)] public int $expectedVersion, #[Assert\Range(min: 1, max: 1000)] public ?int $capacity = null, public bool $waitlistEnabled = true)
    {
    }
}
