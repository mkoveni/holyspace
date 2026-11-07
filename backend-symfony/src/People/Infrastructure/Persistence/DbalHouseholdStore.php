<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\Contract\HouseholdReader;
use App\People\Application\HouseholdView;
use App\People\Domain\Household;
use App\People\Domain\HouseholdMembership;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\HouseholdRepository;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalHouseholdStore implements HouseholdRepository, HouseholdReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $id): ?HouseholdView
    {
        $row = $this->households()->where('id = :id')->setParameter('id', $id)->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $members = array_map(PeopleRecordMapper::membershipView(...), $this->membershipRows('household_id', $id));

        return new HouseholdView(PeopleRecordMapper::householdSummary($row), $members);
    }

    public function findForUpdate(string $id): ?Household
    {
        return $this->one($this->households()->where('id = :id')->setParameter('id', $id)->forUpdate());
    }

    public function search(string $search, int $limit, int $offset): array
    {
        $rows = $this->households()
            ->where('name ILIKE :pattern')
            ->setParameter('pattern', LikePattern::contains($search))
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(PeopleRecordMapper::householdSummary(...), $rows));
    }

    public function memberships(string $personId): array
    {
        return array_map(PeopleRecordMapper::membershipView(...), $this->membershipRows('person_id', $personId));
    }

    public function member(string $householdId, string $membershipId): ?HouseholdMembership
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('people_household_memberships')
            ->where('household_id = :household')
            ->andWhere('id = :id')
            ->setParameter('household', $householdId)
            ->setParameter('id', $membershipId)
            ->fetchAssociative();

        return false === $row ? null : PeopleRecordMapper::membership($row);
    }

    public function insert(Household $household): void
    {
        $this->connection->insert('people_households', PeopleRecordMapper::householdRow($household));
    }

    public function update(Household $household): void
    {
        $row = PeopleRecordMapper::householdRow($household);
        unset($row['id'], $row['created_at']);

        $changed = $this->connection->update('people_households', $row, ['id' => $household->id, 'version' => $household->revision->previousVersion()]);
        if (1 !== $changed) {
            throw PeopleException::conflict('Household has changed. Reload and try again.');
        }
    }

    public function add(HouseholdMembership $membership): void
    {
        $this->connection->insert('people_household_memberships', [
            'id' => $membership->id,
            'household_id' => $membership->householdId,
            'person_id' => $membership->personId,
            'is_primary' => $membership->primary,
            'valid_from' => Timestamps::toDatabase($membership->validFrom),
        ], ['is_primary' => ParameterType::BOOLEAN]);
    }

    public function end(HouseholdMembership $membership): void
    {
        $validTo = $membership->validTo;
        if (null === $validTo) {
            throw PeopleException::conflict('Membership has already ended.');
        }

        $changed = $this->connection->executeStatement(
            'UPDATE people_household_memberships SET valid_to = ? WHERE id = ? AND valid_to IS NULL',
            [Timestamps::toDatabase($validTo), $membership->id],
        );
        if (1 !== $changed) {
            throw PeopleException::conflict('Membership has already ended.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function membershipRows(string $column, string $value): array
    {
        return array_values($this->connection->createQueryBuilder()
            ->select('*')
            ->from('people_household_memberships')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->orderBy('valid_from')
            ->addOrderBy('id')
            ->fetchAllAssociative());
    }

    private function households(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('people_households');
    }

    private function one(QueryBuilder $query): ?Household
    {
        $row = $query->fetchAssociative();

        return false === $row ? null : PeopleRecordMapper::household($row);
    }
}
