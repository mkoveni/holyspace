<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CheckOutChild;

use App\ChildrenMinistry\Sessions\Application\HandoverOutcome;
use App\Shared\Application\Command;

/** @implements Command<HandoverOutcome> */
final readonly class CheckOutChildCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $attendanceId,
        public int $expectedVersion,
        public string $requestId,
        public string $collectorId,
        public bool $identityVerified,
        public ?string $pickupCode,
    ) {
    }

    /** Pickup codes never reach logs. */
    public function __debugInfo(): array
    {
        return ['attendanceId' => $this->attendanceId, 'pickupCode' => '[REDACTED]'];
    }
}
