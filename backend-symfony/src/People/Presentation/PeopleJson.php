<?php

declare(strict_types=1);

namespace App\People\Presentation;

use App\People\Application\AddressView;
use App\People\Application\ContactView;
use App\People\Application\Contract\Title;
use App\People\Application\HouseholdSummary;
use App\People\Application\HouseholdView;
use App\People\Application\MembershipView;
use App\People\Application\MyProfileResult;
use App\People\Application\PeopleMetadata;
use App\People\Application\PersonSummary;
use App\People\Application\PersonView;
use App\People\Application\RelationshipView;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Presentation\Json;
use DateTimeImmutable;

/** Wire format of People resources, matching the Java API's JSON field names. */
final class PeopleJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function summary(PersonSummary $person): array
    {
        return [
            'id' => $person->id,
            'details' => self::details($person->details),
            'membershipType' => $person->membershipType->value,
            'ageCategory' => $person->ageCategory->value,
            'ageAsOf' => $person->ageAsOf->iso(),
            'archivedAt' => self::instant($person->archivedAt),
            'version' => $person->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function person(PersonView $person): array
    {
        return [
            'id' => $person->id,
            'details' => self::details($person->details),
            'membershipType' => $person->membershipType->value,
            'ageCategory' => $person->ageCategory->value,
            'ageAsOf' => $person->ageAsOf->iso(),
            'archivedAt' => self::instant($person->archivedAt),
            'version' => $person->version,
            'contacts' => array_map(self::contact(...), $person->contacts),
            'addresses' => array_map(self::address(...), $person->addresses),
        ];
    }

    /** @return array<string, mixed> */
    public static function household(HouseholdSummary $household): array
    {
        return [
            'id' => $household->id,
            'name' => $household->name,
            'primaryContactMembershipId' => $household->primaryContactMembershipId,
            'version' => $household->version,
            'createdAt' => Json::instant($household->createdAt),
            'updatedAt' => Json::instant($household->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function householdView(HouseholdView $view): array
    {
        return [
            'household' => self::household($view->household),
            'memberships' => array_map(self::membership(...), $view->memberships),
        ];
    }

    /** @return array<string, mixed> */
    public static function membership(MembershipView $membership): array
    {
        return [
            'id' => $membership->id,
            'householdId' => $membership->householdId,
            'personId' => $membership->personId,
            'primary' => $membership->primary,
            'validFrom' => Json::instant($membership->validFrom),
            'validTo' => self::instant($membership->validTo),
        ];
    }

    /** @return array<string, mixed> */
    public static function relationship(RelationshipView $relationship): array
    {
        return [
            'id' => $relationship->id,
            'relatedPersonId' => $relationship->relatedPersonId,
            'type' => $relationship->type,
            'validFrom' => Json::instant($relationship->validFrom),
            'validTo' => self::instant($relationship->validTo),
            'version' => $relationship->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function myProfile(MyProfileResult $result): array
    {
        $profile = $result->profile;

        return [
            'profile' => null === $profile ? null : [
                'id' => $profile->id,
                'details' => self::details($profile->details),
                'membershipType' => $profile->membershipType,
                'version' => $profile->version,
                'contacts' => array_map(self::contact(...), $profile->contacts),
            ],
            'titles' => array_map(static fn (Title $title): array => ['code' => $title->code, 'label' => $title->label], $result->titles),
        ];
    }

    /** @return array<string, mixed> */
    public static function metadata(PeopleMetadata $metadata): array
    {
        return [
            'membershipTypes' => array_map(
                static fn (MembershipType $type): array => ['code' => $type->value, 'label' => $type->label()],
                $metadata->membershipTypes,
            ),
            'titles' => array_map(static fn (Title $title): array => ['code' => $title->code, 'label' => $title->label], $metadata->titles),
            'genders' => $metadata->genders,
            'lifeStatuses' => $metadata->lifeStatuses,
            'relationshipTypes' => $metadata->relationshipTypes,
            'adultAge' => $metadata->adultAge,
            'timeZone' => $metadata->timeZone,
        ];
    }

    /** @return array<string, mixed> */
    public static function details(PersonDetails $details): array
    {
        return [
            'titleCode' => $details->titleCode,
            'firstName' => $details->firstName,
            'lastName' => $details->lastName,
            'dateOfBirth' => $details->dateOfBirth?->iso(),
            'gender' => $details->gender->value,
            'lifeStatus' => $details->lifeStatus->value,
            'dateOfDeath' => $details->dateOfDeath?->iso(),
        ];
    }

    /** @return array<string, mixed> */
    public static function contact(ContactView $contact): array
    {
        return [
            'id' => $contact->id,
            'personId' => $contact->personId,
            'kind' => $contact->kind->value,
            'value' => $contact->value,
            'primary' => $contact->primary,
            'createdAt' => Json::instant($contact->createdAt),
            'endedAt' => self::instant($contact->endedAt),
        ];
    }

    /** @return array<string, mixed> */
    private static function address(AddressView $address): array
    {
        return [
            'id' => $address->id,
            'personId' => $address->personId,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postalCode' => $address->postalCode,
            'countryCode' => $address->countryCode,
            'createdAt' => Json::instant($address->createdAt),
            'endedAt' => self::instant($address->endedAt),
        ];
    }

    private static function instant(?DateTimeImmutable $time): ?string
    {
        return null === $time ? null : Json::instant($time);
    }
}
