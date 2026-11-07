<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** One thing a coordinator should chase: a missing or lapsing guardian, consent or pickup permission, or a care review. */
final readonly class FollowUpIssue
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $state,
        public string $childId,
        public string $childName,
        public ?string $programmeId,
        public string $programme,
        public ?DateTimeImmutable $expiresAt,
        public ?CalendarDate $reviewOn,
        public string $action,
        public string $message,
    ) {
    }
}
