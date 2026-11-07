<?php

declare(strict_types=1);

namespace App\People\Application;

use App\People\Application\Contract\Title;
use App\People\Domain\ValueObject\MembershipType;

final readonly class PeopleMetadata
{
    /**
     * @param list<MembershipType> $membershipTypes
     * @param list<Title>          $titles
     * @param list<string>         $genders
     * @param list<string>         $lifeStatuses
     * @param list<string>         $relationshipTypes
     */
    public function __construct(
        public array $membershipTypes,
        public array $titles,
        public array $genders,
        public array $lifeStatuses,
        public array $relationshipTypes,
        public int $adultAge,
        public string $timeZone,
    ) {
    }
}
