<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\ReviewWorker;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<null> */
final readonly class ReviewWorkerCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public DateTimeImmutable $screenedUntil,
        public DateTimeImmutable $trainedUntil,
        public ?string $evidenceReference,
        public bool $revoked,
    ) {
    }
}
