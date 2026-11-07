<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\RegisterWorker;

use App\Shared\Application\Command;
use DateTimeImmutable;

/** @implements Command<null> */
final readonly class RegisterWorkerCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public string $accountId,
        public DateTimeImmutable $screenedUntil,
        public DateTimeImmutable $trainedUntil,
        public ?string $evidenceReference,
    ) {
    }
}
