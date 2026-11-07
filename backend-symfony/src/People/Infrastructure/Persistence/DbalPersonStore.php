<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\ContactView;
use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonReader;
use App\People\Application\PersonSummary;
use App\People\Application\PersonView;
use App\People\Domain\AgePolicy;
use App\People\Domain\ContactMethod;
use App\People\Domain\PeopleException;
use App\People\Domain\Person;
use App\People\Domain\PersonAddress;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\ContactKind;
use App\People\Domain\ValueObject\MembershipType;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\LikePattern;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DbalPersonStore implements PersonRepository, PersonReader
{
    private const string NAME = "(first_name || ' ' || last_name) ILIKE :pattern";

    public function __construct(private Connection $connection)
    {
    }

    public function exists(string $id): bool
    {
        return false !== $this->connection->createQueryBuilder()->select('1')->from('people_persons')->where('id = :id')->setParameter('id', $id)->fetchOne();
    }

    public function find(string $id, CalendarDate $asOf): ?PersonView
    {
        $row = $this->select()->where('id = :id')->setParameter('id', $id)->fetchAssociative();
        if (false === $row) {
            return null;
        }

        return PeopleRecordMapper::personView($row, $asOf, $this->childRows('people_contact_methods', $id), $this->childRows('people_addresses', $id));
    }

    public function activeContact(string $personId, string $contactId): ?ContactView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('people_contact_methods')
            ->where('id = :id')
            ->andWhere('person_id = :person')
            ->andWhere('ended_at IS NULL')
            ->setParameter('id', $contactId)
            ->setParameter('person', $personId)
            ->fetchAssociative();

        return false === $row ? null : PeopleRecordMapper::contactView($row);
    }

    public function entries(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->select()
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->fetchAllAssociative();

        return array_values(array_map(PeopleRecordMapper::directoryEntry(...), $rows));
    }

    public function matchingIds(string $search): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('people_persons')
            ->where(self::NAME)
            ->setParameter('pattern', LikePattern::contains($search))
            ->fetchFirstColumn();

        return $ids;
    }

    public function activeEmails(string $personId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'value')
            ->from('people_contact_methods')
            ->where('person_id = :person')
            ->andWhere('kind = :kind')
            ->andWhere('ended_at IS NULL')
            ->setParameter('person', $personId)
            ->setParameter('kind', ContactKind::Email->value)
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): PersonEmail => new PersonEmail((string) $row['id'], (string) $row['value']), $rows));
    }

    public function findForUpdate(string $id): ?Person
    {
        return $this->one($this->select()->where('id = :id')->setParameter('id', $id)->forUpdate());
    }

    public function search(
        string $search,
        bool $includeArchived,
        ?MembershipType $membershipType,
        ?AgeCategory $ageCategory,
        CalendarDate $asOf,
        int $limit,
        int $offset,
    ): array {
        $query = $this->select()
            ->where(self::NAME)
            ->setParameter('pattern', LikePattern::contains($search));
        if (!$includeArchived) {
            $query->andWhere('archived_at IS NULL');
        }
        if (null !== $membershipType) {
            $query->andWhere('membership_type = :membership')->setParameter('membership', $membershipType->value);
        }
        if (null !== $ageCategory) {
            $this->restrictToAge($query, $ageCategory, $asOf);
        }
        $rows = $query
            ->orderBy('last_name')
            ->addOrderBy('first_name')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): PersonSummary => PeopleRecordMapper::summary($row, $asOf), $rows));
    }

    public function insert(Person $person): void
    {
        $this->connection->insert('people_persons', PeopleRecordMapper::personRow($person));
    }

    public function update(Person $person): void
    {
        $row = PeopleRecordMapper::personRow($person);
        unset($row['id'], $row['created_at']);

        $changed = $this->connection->update('people_persons', $row, ['id' => $person->id, 'version' => $person->revision->previousVersion()]);
        if (1 !== $changed) {
            throw PeopleException::conflict('Person has changed. Reload and try again.');
        }
    }

    public function addContact(ContactMethod $contact): void
    {
        if ($contact->primary) {
            $this->connection->executeStatement(
                'UPDATE people_contact_methods SET is_primary = FALSE WHERE person_id = ? AND kind = ? AND ended_at IS NULL',
                [$contact->personId, $contact->kind->value],
            );
        }
        $this->connection->insert('people_contact_methods', [
            'id' => $contact->id,
            'person_id' => $contact->personId,
            'kind' => $contact->kind->value,
            'value' => $contact->value,
            'is_primary' => $contact->primary,
            'created_at' => Timestamps::toDatabase($contact->createdAt),
        ], ['is_primary' => ParameterType::BOOLEAN]);
    }

    public function endContact(string $personId, string $contactId, DateTimeImmutable $now): void
    {
        $changed = $this->connection->executeStatement(
            'UPDATE people_contact_methods SET ended_at = ?, is_primary = FALSE WHERE id = ? AND person_id = ? AND ended_at IS NULL',
            [Timestamps::toDatabase($now), $contactId, $personId],
        );
        if (1 !== $changed) {
            throw PeopleException::conflict('Contact method is missing or already ended.');
        }
    }

    public function addAddress(PersonAddress $address): void
    {
        $this->connection->insert('people_addresses', [
            'id' => $address->id,
            'person_id' => $address->personId,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
            'created_at' => Timestamps::toDatabase($address->createdAt),
        ]);
    }

    public function endAddress(string $personId, string $addressId, DateTimeImmutable $now): void
    {
        $changed = $this->connection->executeStatement(
            'UPDATE people_addresses SET ended_at = ? WHERE id = ? AND person_id = ? AND ended_at IS NULL',
            [Timestamps::toDatabase($now), $addressId, $personId],
        );
        if (1 !== $changed) {
            throw PeopleException::conflict('Address is missing or already ended.');
        }
    }

    private function restrictToAge(QueryBuilder $query, AgeCategory $category, CalendarDate $asOf): void
    {
        if (AgeCategory::Unknown === $category) {
            $query->andWhere('(date_of_birth IS NULL OR date_of_birth > :as_of)')->setParameter('as_of', $asOf->iso());

            return;
        }

        $query->setParameter('cutoff', AgePolicy::adultBirthDateCutoff($asOf)->iso());
        if (AgeCategory::Adult === $category) {
            $query->andWhere('date_of_birth <= :cutoff');

            return;
        }
        $query->andWhere('date_of_birth > :cutoff AND date_of_birth <= :as_of')->setParameter('as_of', $asOf->iso());
    }

    /** @return list<array<string, mixed>> */
    private function childRows(string $table, string $personId): array
    {
        return array_values($this->connection->createQueryBuilder()
            ->select('*')
            ->from($table)
            ->where('person_id = :person')
            ->setParameter('person', $personId)
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->fetchAllAssociative());
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from('people_persons');
    }

    private function one(QueryBuilder $query): ?Person
    {
        $row = $query->fetchAssociative();

        return false === $row ? null : PeopleRecordMapper::person($row);
    }
}
