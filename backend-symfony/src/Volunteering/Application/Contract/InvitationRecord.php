<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java's `Invitation` record. */
final readonly class InvitationRecord
{
    public function __construct(
        public string $id,
        public string $assignmentId,
        public int $assignmentVersion,
        public int $shiftVersion,
        public DateTimeImmutable $expiresAt,
        public string $status,
        public int $version,
        public ?DateTimeImmutable $respondedAt,
    ) {
    }
}
