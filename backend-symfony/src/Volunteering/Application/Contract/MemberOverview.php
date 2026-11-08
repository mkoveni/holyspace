<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;

/** Java `MemberVolunteerViews.Overview`: my next 90 days of duties and my current availability. */
final readonly class MemberOverview
{
    /**
     * @param list<AvailabilityRule>      $weekly
     * @param list<AvailabilityException> $exceptions
     * @param list<MemberDuty>            $items
     */
    public function __construct(
        public bool $permitted,
        public bool $linked,
        public ?string $volunteerId,
        public ?string $status,
        public ?int $version,
        public array $weekly,
        public array $exceptions,
        public array $items,
        public bool $hasMore,
    ) {
    }

    public static function empty(bool $permitted): self
    {
        return new self($permitted, false, null, null, null, [], [], [], false);
    }
}
