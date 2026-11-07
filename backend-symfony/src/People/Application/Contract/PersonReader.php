<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

use App\People\Application\ContactView;
use App\People\Application\PersonSummary;
use App\People\Application\PersonView;
use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\Shared\Domain\CalendarDate;

/** Read side of People: returns read models, never aggregates. */
interface PersonReader
{
    public function exists(string $id): bool;

    /** The person with their contacts and addresses; the age category is evaluated on the given day. */
    public function find(string $id, CalendarDate $asOf): ?PersonView;

    /** One of the person's contact methods that has not been ended, or null (also when it belongs to someone else). */
    public function activeContact(string $personId, string $contactId): ?ContactView;

    /** @return list<PersonSummary> */
    public function search(
        string $search,
        bool $includeArchived,
        ?MembershipType $membershipType,
        ?AgeCategory $ageCategory,
        CalendarDate $asOf,
        int $limit,
        int $offset,
    ): array;

    /**
     * @param list<string> $ids
     *
     * @return list<PersonDirectoryEntry>
     */
    public function entries(array $ids): array;

    /** @return list<string> ids of people whose full name contains the text, ignoring case */
    public function matchingIds(string $search): array;

    /** @return list<PersonEmail> email addresses that have not been ended */
    public function activeEmails(string $personId): array;
}
