<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPeopleMetadata;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\TitleReader;
use App\People\Application\PeopleMetadata;
use App\People\Domain\AgePolicy;
use App\People\Domain\ValueObject\Gender;
use App\People\Domain\ValueObject\LifeStatus;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Application\ChurchCalendar;

final readonly class GetPeopleMetadataHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private TitleReader $titles,
        private ChurchCalendar $calendar,
    ) {
    }

    public function handle(GetPeopleMetadataQuery $query): PeopleMetadata
    {
        $this->authorization->requirePermission('people.read');

        return new PeopleMetadata(
            MembershipType::cases(),
            $this->titles->titles(),
            array_map(static fn (Gender $gender): string => $gender->value, Gender::cases()),
            array_map(static fn (LifeStatus $status): string => $status->value, LifeStatus::cases()),
            array_map(static fn (RelationshipType $type): string => $type->value, RelationshipType::cases()),
            AgePolicy::ADULT_AGE,
            $this->calendar->timeZone(),
        );
    }
}
