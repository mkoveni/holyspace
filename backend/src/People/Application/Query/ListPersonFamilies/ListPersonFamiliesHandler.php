<?php

declare(strict_types=1);

namespace App\People\Application\Query\ListPersonFamilies;

use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class ListPersonFamiliesHandler
{
    public function __construct(private PeopleReadRepository $read) {}
    public function __invoke(ListPersonFamiliesQuery $q): array
    {
        return array_map(static fn(array $r): array => [
            'id' => $r['id'],
            'name' => $r['name'],
            'role' => $r['role'],
            'membershipCreatedAt' => $r['membership_created_at'],
            'createdAt' => $r['created_at'],
            'updatedAt' => $r['updated_at']
        ], $this->read->familiesForPerson($q->personId));
    }
}
