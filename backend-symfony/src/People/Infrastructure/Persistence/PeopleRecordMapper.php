<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\AddressView;
use App\People\Application\ContactView;
use App\People\Application\Contract\PersonDirectoryEntry;
use App\People\Application\HouseholdSummary;
use App\People\Application\MembershipView;
use App\People\Application\PersonSummary;
use App\People\Application\PersonView;
use App\People\Application\RelationshipView;
use App\People\Domain\AgePolicy;
use App\People\Domain\ContactMethod;
use App\People\Domain\Household;
use App\People\Domain\HouseholdMembership;
use App\People\Domain\Person;
use App\People\Domain\PersonAddress;
use App\People\Domain\PersonRelationship;
use App\People\Domain\ValueObject\ContactKind;
use App\People\Domain\ValueObject\Gender;
use App\People\Domain\ValueObject\LifeStatus;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\Revision;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;

/** The single place where People rows become domain objects and back. */
final class PeopleRecordMapper
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $row */
    public static function person(array $row): Person
    {
        return Person::reconstitute(
            self::string($row, 'id'),
            self::details($row),
            MembershipType::from(self::string($row, 'membership_type')),
            self::nullableTime($row, 'archived_at'),
            self::revision($row),
        );
    }

    /** @param array<string, mixed> $row */
    public static function summary(array $row, CalendarDate $asOf): PersonSummary
    {
        $details = self::details($row);

        return new PersonSummary(
            self::string($row, 'id'),
            $details,
            MembershipType::from(self::string($row, 'membership_type')),
            AgePolicy::category($details->dateOfBirth, $asOf),
            $asOf,
            self::nullableTime($row, 'archived_at'),
            self::int($row, 'version'),
        );
    }

    /**
     * @param array<string, mixed>       $row
     * @param list<array<string, mixed>> $contacts
     * @param list<array<string, mixed>> $addresses
     */
    public static function personView(array $row, CalendarDate $asOf, array $contacts, array $addresses): PersonView
    {
        $summary = self::summary($row, $asOf);

        return new PersonView(
            $summary->id,
            $summary->details,
            $summary->membershipType,
            $summary->ageCategory,
            $asOf,
            $summary->archivedAt,
            $summary->version,
            array_map(self::contactView(...), $contacts),
            array_map(self::addressView(...), $addresses),
        );
    }

    /** @param array<string, mixed> $row */
    public static function directoryEntry(array $row): PersonDirectoryEntry
    {
        $details = self::details($row);

        return new PersonDirectoryEntry(
            self::string($row, 'id'),
            $details->firstName.' '.$details->lastName,
            $details->dateOfBirth,
            null === $row['archived_at'] && LifeStatus::Deceased !== $details->lifeStatus,
        );
    }

    /** @param array<string, mixed> $row */
    public static function contactView(array $row): ContactView
    {
        return new ContactView(
            self::string($row, 'id'),
            self::string($row, 'person_id'),
            ContactKind::from(self::string($row, 'kind')),
            self::string($row, 'value'),
            self::bool($row, 'is_primary'),
            self::time($row, 'created_at'),
            self::nullableTime($row, 'ended_at'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function addressView(array $row): AddressView
    {
        return new AddressView(
            self::string($row, 'id'),
            self::string($row, 'person_id'),
            self::string($row, 'line1'),
            self::string($row, 'line2'),
            self::string($row, 'city'),
            self::string($row, 'region'),
            self::string($row, 'postal_code'),
            self::string($row, 'country_code'),
            self::time($row, 'created_at'),
            self::nullableTime($row, 'ended_at'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function householdSummary(array $row): HouseholdSummary
    {
        return new HouseholdSummary(
            self::string($row, 'id'),
            self::string($row, 'name'),
            self::nullableString($row, 'primary_contact_membership_id'),
            self::int($row, 'version'),
            self::time($row, 'created_at'),
            self::time($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function membershipView(array $row): MembershipView
    {
        return new MembershipView(
            self::string($row, 'id'),
            self::string($row, 'household_id'),
            self::string($row, 'person_id'),
            self::bool($row, 'is_primary'),
            self::time($row, 'valid_from'),
            self::nullableTime($row, 'valid_to'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function relationshipView(array $row, string $perspective): RelationshipView
    {
        $type = RelationshipType::from(self::string($row, 'type'));
        $outgoing = $perspective === self::string($row, 'source_id');

        return new RelationshipView(
            self::string($row, 'id'),
            self::string($row, $outgoing ? 'target_id' : 'source_id'),
            $outgoing ? $type->value : $type->inverse(),
            self::time($row, 'valid_from'),
            self::nullableTime($row, 'valid_to'),
            self::int($row, 'version'),
        );
    }

    /** @return array<string, mixed> */
    public static function personRow(Person $person): array
    {
        return [
            'id' => $person->id,
            'title_code' => $person->details->titleCode,
            'first_name' => $person->details->firstName,
            'last_name' => $person->details->lastName,
            'date_of_birth' => $person->details->dateOfBirth?->iso(),
            'gender' => $person->details->gender->value,
            'membership_type' => $person->membershipType->value,
            'life_status' => $person->details->lifeStatus->value,
            'date_of_death' => $person->details->dateOfDeath?->iso(),
            'archived_at' => self::databaseTime($person->archivedAt),
            'version' => $person->revision->version,
            'created_at' => Timestamps::toDatabase($person->revision->timeline->createdAt),
            'updated_at' => Timestamps::toDatabase($person->revision->timeline->updatedAt),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function household(array $row): Household
    {
        return Household::reconstitute(
            self::string($row, 'id'),
            self::string($row, 'name'),
            self::nullableString($row, 'primary_contact_membership_id'),
            self::revision($row),
        );
    }

    /** @return array<string, mixed> */
    public static function householdRow(Household $household): array
    {
        return [
            'id' => $household->id,
            'name' => $household->name,
            'primary_contact_membership_id' => $household->primaryContactMembershipId,
            'version' => $household->revision->version,
            'created_at' => Timestamps::toDatabase($household->revision->timeline->createdAt),
            'updated_at' => Timestamps::toDatabase($household->revision->timeline->updatedAt),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function membership(array $row): HouseholdMembership
    {
        return HouseholdMembership::reconstitute(
            self::string($row, 'id'),
            self::string($row, 'household_id'),
            self::string($row, 'person_id'),
            self::bool($row, 'is_primary'),
            self::time($row, 'valid_from'),
            self::nullableTime($row, 'valid_to'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function relationship(array $row): PersonRelationship
    {
        return PersonRelationship::reconstitute(
            self::string($row, 'id'),
            self::string($row, 'source_id'),
            self::string($row, 'target_id'),
            RelationshipType::from(self::string($row, 'type')),
            self::time($row, 'valid_from'),
            self::nullableTime($row, 'valid_to'),
            self::int($row, 'version'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function contact(array $row): ContactMethod
    {
        return ContactMethod::reconstitute(
            self::string($row, 'id'),
            self::string($row, 'person_id'),
            ContactKind::from(self::string($row, 'kind')),
            self::string($row, 'value'),
            self::bool($row, 'is_primary'),
            self::time($row, 'created_at'),
            self::nullableTime($row, 'ended_at'),
        );
    }

    /** @param array<string, mixed> $row */
    public static function address(array $row): PersonAddress
    {
        return PersonAddress::reconstitute(
            self::string($row, 'id'),
            self::string($row, 'person_id'),
            self::string($row, 'line1'),
            self::string($row, 'line2'),
            self::string($row, 'city'),
            self::string($row, 'region'),
            self::string($row, 'postal_code'),
            self::string($row, 'country_code'),
            self::time($row, 'created_at'),
            self::nullableTime($row, 'ended_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private static function revision(array $row): Revision
    {
        return Revision::reconstitute(self::int($row, 'version'), self::time($row, 'created_at'), self::time($row, 'updated_at'));
    }

    public static function databaseTime(?DateTimeImmutable $time): ?string
    {
        return null === $time ? null : Timestamps::toDatabase($time);
    }

    /** @param array<string, mixed> $row */
    private static function details(array $row): PersonDetails
    {
        return new PersonDetails(
            self::nullableString($row, 'title_code'),
            self::string($row, 'first_name'),
            self::string($row, 'last_name'),
            self::date($row, 'date_of_birth'),
            Gender::from(self::string($row, 'gender')),
            LifeStatus::from(self::string($row, 'life_status')),
            self::date($row, 'date_of_death'),
        );
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        return (string) $row[$column];
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        return null === $row[$column] ? null : (string) $row[$column];
    }

    /** @param array<string, mixed> $row */
    private static function int(array $row, string $column): int
    {
        return (int) $row[$column];
    }

    /** @param array<string, mixed> $row */
    private static function bool(array $row, string $column): bool
    {
        return true === $row[$column] || 't' === $row[$column] || '1' === $row[$column] || 1 === $row[$column];
    }

    /** @param array<string, mixed> $row */
    private static function time(array $row, string $column): DateTimeImmutable
    {
        return Timestamps::fromDatabase((string) $row[$column]);
    }

    /** @param array<string, mixed> $row */
    private static function nullableTime(array $row, string $column): ?DateTimeImmutable
    {
        return null === $row[$column] ? null : self::time($row, $column);
    }

    /** @param array<string, mixed> $row */
    private static function date(array $row, string $column): ?CalendarDate
    {
        return null === $row[$column] ? null : CalendarDate::parse(substr((string) $row[$column], 0, 10));
    }
}
