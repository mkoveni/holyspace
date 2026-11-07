<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckOutChildRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $attendanceId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $requestId = null,
        #[Assert\NotNull, Assert\Uuid]
        public ?string $collectorId = null,
        public bool $identityVerified = false,
        #[Assert\Length(max: 2000)]
        public ?string $pickupCode = null,
    ) {
    }

    /** Pickup codes never reach logs or dumps. */
    public function __debugInfo(): array
    {
        return ['attendanceId' => $this->attendanceId, 'pickupCode' => '[REDACTED]'];
    }
}
