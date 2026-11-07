<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\People\Domain\ContactMethod;
use App\People\Domain\Person;
use App\People\Domain\PersonAddress;
use App\People\Domain\Repository\PersonRepository;
use DateTimeImmutable;

/** In-memory test double: no database, no identity context. */
final class InMemoryPersonRepository implements PersonRepository
{
    /** @var array<string, Person> */
    public array $people = [];

    public function findForUpdate(string $id): ?Person
    {
        return $this->people[$id] ?? null;
    }

    public function insert(Person $person): void
    {
        $this->people[$person->id] = $person;
    }

    public function update(Person $person): void
    {
        $this->people[$person->id] = $person;
    }

    /** @var list<ContactMethod> */
    public array $contactsAdded = [];
    /** @var list<string> */
    public array $contactsEnded = [];

    public function addContact(ContactMethod $contact): void
    {
        $this->contactsAdded[] = $contact;
    }

    public function endContact(string $personId, string $contactId, DateTimeImmutable $now): void
    {
        $this->contactsEnded[] = $contactId;
    }

    public function addAddress(PersonAddress $address): void
    {
    }

    public function endAddress(string $personId, string $addressId, DateTimeImmutable $now): void
    {
    }
}
