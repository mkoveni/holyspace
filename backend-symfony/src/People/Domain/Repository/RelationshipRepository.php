<?php

declare(strict_types=1);

namespace App\People\Domain\Repository;

use App\People\Domain\PersonRelationship;
use DateTimeImmutable;

interface RelationshipRepository
{
    /** Serialises writers of the relationship graph until the surrounding transaction ends. */
    public function lockGraph(): void;

    public function find(string $id): ?PersonRelationship;

    /** @return list<PersonRelationship> parent-of edges current at the given instant */
    public function currentParentage(DateTimeImmutable $at): array;

    public function insert(PersonRelationship $relationship): void;

    public function update(PersonRelationship $relationship): void;
}
