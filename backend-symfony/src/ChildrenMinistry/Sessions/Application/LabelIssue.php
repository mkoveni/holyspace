<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

/** A freshly issued pickup code. This is the only time it exists in clear. */
final readonly class LabelIssue
{
    public function __construct(
        public string $attendanceId,
        public int $labelVersion,
        public string $pickupCode,
        public DateTimeImmutable $expiresAt,
        public int $attendanceVersion,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['attendanceId' => $this->attendanceId, 'pickupCode' => '[REDACTED]'];
    }
}
