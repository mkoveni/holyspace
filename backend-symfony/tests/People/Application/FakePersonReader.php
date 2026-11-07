<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\People\Application\ContactView;
use App\People\Application\Contract\PersonReader;
use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\MembershipType;
use App\Shared\Domain\CalendarDate;

/** Reader fake that only knows about active contacts, which is all the self-service handlers ask it. */
final class FakePersonReader implements PersonReader
{
    /** @param list<ContactView> $contacts */
    public function __construct(private readonly array $contacts = [])
    {
    }

    public function exists(string $id): bool
    {
        return false;
    }

    public function find(string $id, CalendarDate $asOf): null
    {
        return null;
    }

    public function activeContact(string $personId, string $contactId): ?ContactView
    {
        foreach ($this->contacts as $contact) {
            if ($contact->id === $contactId && $contact->personId === $personId && null === $contact->endedAt) {
                return $contact;
            }
        }

        return null;
    }

    public function search(string $search, bool $includeArchived, ?MembershipType $membershipType, ?AgeCategory $ageCategory, CalendarDate $asOf, int $limit, int $offset): array
    {
        return [];
    }

    public function entries(array $ids): array
    {
        return [];
    }

    public function matchingIds(string $search): array
    {
        return [];
    }

    public function activeEmails(string $personId): array
    {
        return [];
    }
}
