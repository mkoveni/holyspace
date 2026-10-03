<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListFamilyPeople;

use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class ListFamilyPeopleHandler
{
    public function __construct(private PeopleReadRepository $read) {}
    public function __invoke(ListFamilyPeopleQuery $q): array
    {
        return array_map(static fn(array $r): array => [
            'id' => $r['id'],
            'firstName' => $r['first_name'],
            'lastName' => $r['last_name'],
            'dateOfBirth' => $r['date_of_birth'],
            'gender' => $r['gender'],
            'email' => $r['email'],
            'phone' => $r['phone'],
            'role' => $r['role'],
            'membershipCreatedAt' => $r['membership_created_at']
        ], $this->read->peopleInFamily($q->familyId));
    }
}
