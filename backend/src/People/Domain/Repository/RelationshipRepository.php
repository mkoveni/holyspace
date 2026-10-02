<?php

declare(strict_types=1);

namespace App\People\Domain\Repository;

use App\People\Domain\Model\PersonRelationship;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\RelationshipId;

interface RelationshipRepository
{
    public function save(PersonRelationship $relationship): void;
    public function findById(RelationshipId $id): ?PersonRelationship;
    /** @return list<PersonRelationship> */ public function findForPerson(PersonId $personId): array;
}
