<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Read model of one person with their contact history and addresses. */
final readonly class PersonView
{
    /**
     * @param list<ContactView> $contacts
     * @param list<AddressView> $addresses
     */
    public function __construct(
        public string $id,
        public PersonDetails $details,
        public MembershipType $membershipType,
        public AgeCategory $ageCategory,
        public CalendarDate $ageAsOf,
        public ?DateTimeImmutable $archivedAt,
        public int $version,
        public array $contacts,
        public array $addresses,
    ) {
    }
}
