<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckOutPayload
{
    public function __construct(#[Assert\Uuid] public string $collectorId, #[Assert\Uuid] public string $staffPersonId, #[Assert\Length(max: 20)] public string $pickupCode = '', public bool $identityVerified = false, #[Assert\PositiveOrZero] public ?int $expectedVersion = null)
    {
    }
}
