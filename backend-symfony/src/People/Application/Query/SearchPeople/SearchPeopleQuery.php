<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchPeople;

use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\Shared\Domain\CalendarDate;

final readonly class SearchPeopleQuery
{
    public function __construct(
        public string $search,
        public bool $includeArchived,
        public ?MembershipType $membershipType,
        public ?AgeCategory $ageCategory,
        public ?CalendarDate $asOf,
        public int $limit,
        public int $offset,
    ) {
    }
}
