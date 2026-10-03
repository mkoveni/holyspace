<?php

declare(strict_types=1);

namespace App\People\Application\ReadModel;

interface PeopleReadRepository
{
    public function person(string $personId): ?array;
    public function people(): array;
    public function family(string $familyId): ?array;
    public function families(): array;
    public function peopleInFamily(string $familyId): array;
    public function familiesForPerson(string $personId): array;
    public function relationshipsForPerson(string $personId): array;
    public function profile(string $personId): array;
}
