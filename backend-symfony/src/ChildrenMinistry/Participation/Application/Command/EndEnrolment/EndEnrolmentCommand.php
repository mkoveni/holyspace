<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Application\Command\EndEnrolment;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class EndEnrolmentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $enrolmentId,
        public int $expectedVersion,
    ) {
    }
}
