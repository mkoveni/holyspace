<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

use App\People\Application\RelationshipView;

interface RelationshipReader
{
    /** @return list<RelationshipView> relationships where the person is either end, seen from that person */
    public function forPerson(string $personId): array;
}
