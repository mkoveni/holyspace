<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ReviewVisitor;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ReviewVisitorCommand implements Command
{
    public const string CONFIRM = 'CONFIRM';
    public const string REMOVE = 'REMOVE';

    public function __construct(
        public string $actorId,
        public string $registrationId,
        public string $decision,
        public ?int $expectedAttendanceVersion,
        public string $reason,
    ) {
    }
}
