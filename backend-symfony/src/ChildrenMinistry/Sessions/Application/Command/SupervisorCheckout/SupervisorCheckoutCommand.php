<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\SupervisorCheckout;

use App\ChildrenMinistry\Sessions\Application\HandoverOutcome;
use App\Shared\Application\Command;

/** @implements Command<HandoverOutcome> */
final readonly class SupervisorCheckoutCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $attendanceId,
        public int $expectedVersion,
        public string $requestId,
        public string $collectorId,
        public bool $identityVerified,
        public ?string $reason,
    ) {
    }
}
