<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence\DBAL;

use App\People\Domain\Model\PersonRelationship;
use App\People\Domain\Repository\RelationshipRepository;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\RelationshipId;
use Doctrine\DBAL\Connection;

final readonly class DbalRelationshipRepository implements RelationshipRepository
{
    public function __construct(private Connection $connection, private RelationshipMapper $mapper) {}
    public function save(PersonRelationship $r): void
    {
        $d = $this->mapper->toRow($r);
        $exists = $this->connection->fetchOne('SELECT 1 FROM people_relationships WHERE id=:id', ['id' => $r->id()->toString()]);
        if ($exists) {
            unset($d['id']);
            $this->connection->update('people_relationships', $d, ['id' => $r->id()->toString()]);
        } else {
            $this->connection->insert('people_relationships', $d);
        }
    }
    public function findById(RelationshipId $id): ?PersonRelationship
    {
        $r = $this->connection->fetchAssociative('SELECT * FROM people_relationships WHERE id=:id', ['id' => $id->toString()]);
        return $r === false ? null : $this->mapper->toDomain($r);
    }
    public function findForPerson(PersonId $id): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM people_relationships WHERE person_id=:id ORDER BY created_at', ['id' => $id->toString()]);
        return array_map(fn(array $r): PersonRelationship => $this->mapper->toDomain($r), $rows);
    }
}
