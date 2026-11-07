<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\Attendance;
use DateTimeImmutable;

/** What stations see of a visit: never the label hash, the failed-attempt state or the exception reason. */
final readonly class AttendanceView
{
    public function __construct(
        public string $id,
        public string $childId,
        public string $sessionId,
        public DateTimeImmutable $arrivedAt,
        public ?DateTimeImmutable $checkedOutAt,
        public ?string $collectorId,
        public ?string $authorizationId,
        public bool $identityVerified,
        public bool $exceptionUsed,
        public int $labelVersion,
        public ?DateTimeImmutable $labelExpiresAt,
        public bool $labelIssued,
        public int $version,
    ) {
    }

    public static function from(Attendance $value): self
    {
        return new self($value->id, $value->childId, $value->sessionId, $value->arrivedAt, $value->checkedOutAt, $value->collectorId, $value->authorizationId, $value->identityVerified, null !== $value->exceptionReason && '' !== trim($value->exceptionReason), $value->labelVersion, $value->labelExpiresAt, null !== $value->labelHash, $value->version);
    }
}
