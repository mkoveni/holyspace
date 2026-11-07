<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReviewReconciliation;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class ReviewReconciliationCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $batchId,
        public int $expectedVersion,
        public ?string $outcome,
        public ?string $note,
    ) {
    }
}
