<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\Shared\Domain\CalendarDate;

/** An age group within a programme. The age range is inclusive of the minimum and exclusive of the maximum. */
final readonly class MinistryClass
{
    private const int MAX_AGE_MONTHS = 216;

    public string $name;

    private function __construct(
        public string $id,
        public string $programmeId,
        string $name,
        public int $minAgeMonths,
        public int $maxAgeMonths,
    ) {
        $this->name = ChildrenRules::text($name, 120);
        if ($minAgeMonths < 0 || $maxAgeMonths <= $minAgeMonths || $maxAgeMonths > self::MAX_AGE_MONTHS) {
            throw ChildrenException::invalid('Class age range must be within 0–216 months, with an exclusive upper bound.');
        }
    }

    public static function create(string $id, string $programmeId, string $name, int $minAgeMonths, int $maxAgeMonths): self
    {
        return new self($id, $programmeId, $name, $minAgeMonths, $maxAgeMonths);
    }

    public static function reconstitute(string $id, string $programmeId, string $name, int $minAgeMonths, int $maxAgeMonths): self
    {
        return new self($id, $programmeId, $name, $minAgeMonths, $maxAgeMonths);
    }

    /** The child must be under 18 and, in whole months, within this class's range on the given day. */
    public function requireAge(?CalendarDate $birth, CalendarDate $on): void
    {
        $birth = ChildrenRules::child($birth, $on);
        if ($birth->plusMonths($this->minAgeMonths)->isAfter($on) || !$birth->plusMonths($this->maxAgeMonths)->isAfter($on)) {
            throw ChildrenException::invalid("Child is outside this class's age range.");
        }
    }
}
