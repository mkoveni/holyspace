<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Read model of a person in a list. */
final readonly class PersonSummary
{
    public function __construct(
        public string $id,
        public PersonDetails $details,
        public MembershipType $membershipType,
        public AgeCategory $ageCategory,
        public CalendarDate $ageAsOf,
        public ?DateTimeImmutable $archivedAt,
        public int $version,
    ) {
    }
}
