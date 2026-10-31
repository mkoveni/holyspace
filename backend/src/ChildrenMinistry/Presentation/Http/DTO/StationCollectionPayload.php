<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Presentation\Http\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StationCollectionPayload
{
    public function __construct(#[Assert\Uuid] public string $collectorId, #[Assert\NotBlank] #[Assert\Length(max: 20)] public string $pickupCode, #[Assert\Positive] public int $expectedVersion, public bool $identityVerified = false)
    {
    }
}
