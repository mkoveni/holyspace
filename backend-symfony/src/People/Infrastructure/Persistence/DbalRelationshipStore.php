<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\Contract\RelationshipReader;
use App\People\Application\RelationshipView;
use App\People\Domain\PeopleException;
use App\People\Domain\PersonRelationship;
use App\People\Domain\Repository\RelationshipRepository;
use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalRelationshipStore implements RelationshipRepository, RelationshipReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function lockGraph(): void
    {
        $this->connection->createQueryBuilder()
            ->select('id')
            ->from('people_relationship_guard')
            ->where('id = 1')
            ->forUpdate()
            ->fetchOne();
    }

    public function find(string $id): ?PersonRelationship
    {
        $row = $this->select()->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : PeopleRecordMapper::relationship($row);
    }

    public function forPerson(string $personId): array
    {
        $rows = $this->select()
            ->where('source_id = :person OR target_id = :person')
            ->setParameter('person', $personId)
            ->orderBy('valid_from')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): RelationshipView => PeopleRecordMapper::relationshipView($row, $personId), $rows));
    }

    public function currentParentage(DateTimeImmutable $at): array
    {
        $rows = $this->select()
            ->where('type = :type')
            ->andWhere('valid_from <= :at')
            ->andWhere('(valid_to IS NULL OR valid_to > :at)')
            ->setParameter('type', RelationshipType::ParentOf->value)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->fetchAllAssociative();

        return array_values(array_map(PeopleRecordMapper::relationship(...), $rows));
    }

    public function insert(PersonRelationship $relationship): void
    {
        $this->connection->insert('people_relationships', [
            'id' => $relationship->id,
            'source_id' => $relationship->sourceId,
            'target_id' => $relationship->targetId,
            'type' => $relationship->type->value,
            'valid_from' => Timestamps::toDatabase($relationship->validFrom),
            'version' => $relationship->version,
        ]);
    }

    public function update(PersonRelationship $relationship): void
    {
        $changed = $this->connection->update(
            'people_relationships',
            [
                'valid_to' => PeopleRecordMapper::databaseTime($relationship->validTo),
                'version' => $relationship->version,
            ],
            ['id' => $relationship->id, 'version' => $relationship->version - 1],
        );
        if (1 !== $changed) {
            throw PeopleException::conflict('Relationship has changed. Reload and try again.');
        }
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('people_relationships');
    }
}
