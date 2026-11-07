<?php

declare(strict_types=1);

namespace App\People\Domain\Repository;

use App\People\Domain\ContactMethod;
use App\People\Domain\Person;
use App\People\Domain\PersonAddress;
use DateTimeImmutable;

/** Persists the person aggregate: details, contact methods and addresses. */
interface PersonRepository
{
    /** Loads the person holding a row lock until the surrounding transaction ends. */
    public function findForUpdate(string $id): ?Person;

    public function insert(Person $person): void;

    /** Saves a new revision. Throws a conflict when the stored version is not the previous one. */
    public function update(Person $person): void;

    public function addContact(ContactMethod $contact): void;

    public function endContact(string $personId, string $contactId, DateTimeImmutable $now): void;

    public function addAddress(PersonAddress $address): void;

    public function endAddress(string $personId, string $addressId, DateTimeImmutable $now): void;
}
