<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\AvailabilityExceptionRecord;
use App\Volunteering\Application\Contract\AvailabilityRuleRecord;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\CredentialView;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/** Java `JooqVolunteerStore` reads. */
final readonly class DbalVolunteerReader implements VolunteerDirectory
{
    public function __construct(private Connection $connection)
    {
    }

    private const array VOLUNTEER_COLUMNS = ['id', 'person_id', 'account_id', 'status', 'availability_revision', 'version', 'created_at', 'updated_at'];

    public function volunteer(string $id): ?VolunteerView
    {
        return $this->one('id', $id);
    }

    public function byPerson(string $personId): ?VolunteerView
    {
        return $this->one('person_id', $personId);
    }

    public function byAccount(string $accountId): ?VolunteerView
    {
        return $this->one('account_id', $accountId);
    }

    public function volunteers(?array $personIds, int $limit, int $offset): array
    {
        if ([] === $personIds) {
            return [];
        }
        $rows = $this->matching($this->connection->createQueryBuilder()->select(...self::VOLUNTEER_COLUMNS), $personIds)
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::volunteerView(...), $rows));
    }

    public function volunteerCount(?array $personIds): int
    {
        return [] === $personIds ? 0 : (int) $this->matching($this->connection->createQueryBuilder()->select('COUNT(*)'), $personIds)->fetchOne();
    }

    public function weeklyRules(string $volunteerId, int $revision): array
    {
        return array_map(static fn (array $row): AvailabilityRuleRecord => new AvailabilityRuleRecord(
            (string) $row['id'],
            (string) $row['volunteer_id'],
            (int) $row['revision'],
            self::rule($row),
        ), $this->ruleRows($volunteerId, $revision));
    }

    public function exceptionWindows(string $volunteerId, int $revision): array
    {
        return array_map(static fn (array $row): AvailabilityExceptionRecord => new AvailabilityExceptionRecord(
            (string) $row['id'],
            (string) $row['volunteer_id'],
            (int) $row['revision'],
            self::exception($row),
        ), $this->exceptionRows($volunteerId, $revision));
    }

    public function credentialTypes(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'code', 'name')
            ->from('serving_credential_types')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults(500)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): CredentialTypeView => new CredentialTypeView((string) $row['id'], (string) $row['name'], (string) $row['code']), $rows));
    }

    public function credentials(string $volunteerId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'type_id', 'valid_from', 'valid_until', 'revoked_at')
            ->from('serving_credentials')
            ->where('volunteer_id = :volunteer')
            ->setParameter('volunteer', $volunteerId)
            ->orderBy('verified_at', 'DESC')
            ->setMaxResults(500)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): CredentialView => new CredentialView((string) $row['type_id'], ValidityWindows::fromRow($row), (string) $row['id']), $rows));
    }

    public function availability(string $volunteerId, int $revision): array
    {
        return array_map(self::rule(...), $this->ruleRows($volunteerId, $revision));
    }

    public function exceptions(string $volunteerId, int $revision): array
    {
        return array_map(self::exception(...), $this->exceptionRows($volunteerId, $revision));
    }

    /** @return list<array<string, mixed>> */
    private function ruleRows(string $volunteerId, int $revision): array
    {
        return array_values($this->connection->createQueryBuilder()
            ->select('id', 'volunteer_id', 'revision', 'day_of_week', 'starts_at', 'ends_at', 'time_zone', 'valid_from', 'valid_until')
            ->from('serving_availability_rules')
            ->where('volunteer_id = :volunteer')
            ->andWhere('revision = :revision')
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('revision', $revision)
            ->orderBy('day_of_week')
            ->addOrderBy('starts_at')
            ->setMaxResults(100)
            ->fetchAllAssociative());
    }

    /** @return list<array<string, mixed>> */
    private function exceptionRows(string $volunteerId, int $revision): array
    {
        return array_values($this->connection->createQueryBuilder()
            ->select('id', 'volunteer_id', 'revision', 'starts_at', 'ends_at', 'available')
            ->from('serving_availability_exceptions')
            ->where('volunteer_id = :volunteer')
            ->andWhere('revision = :revision')
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('revision', $revision)
            ->orderBy('starts_at')
            ->setMaxResults(100)
            ->fetchAllAssociative());
    }

    /** @param array<string, mixed> $row */
    private static function rule(array $row): AvailabilityRule
    {
        return new AvailabilityRule(
            (int) $row['day_of_week'],
            self::time((string) $row['starts_at']),
            self::time((string) $row['ends_at']),
            (string) $row['time_zone'],
            (string) $row['valid_from'],
            (string) $row['valid_until'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function exception(array $row): AvailabilityException
    {
        return new AvailabilityException(
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (bool) $row['available'],
        );
    }

    private function one(string $column, string $value): ?VolunteerView
    {
        $row = $this->connection->createQueryBuilder()
            ->select(...self::VOLUNTEER_COLUMNS)
            ->from('serving_volunteers')
            ->where($column.' = :value')
            ->setParameter('value', $value)
            ->fetchAssociative();

        return false === $row ? null : self::volunteerView($row);
    }

    /** @param list<string>|null $personIds */
    private function matching(QueryBuilder $query, ?array $personIds): QueryBuilder
    {
        $query->from('serving_volunteers');
        if (null !== $personIds) {
            $query->where('person_id IN (:people)')->setParameter('people', $personIds, ArrayParameterType::STRING);
        }

        return $query;
    }

    /** @param array<string, mixed> $row */
    private static function volunteerView(array $row): VolunteerView
    {
        return new VolunteerView(
            (string) $row['id'],
            (string) $row['person_id'],
            (string) $row['status'],
            (int) $row['availability_revision'],
            null === $row['account_id'] ? null : (string) $row['account_id'],
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    /** TIME columns may carry fractional seconds; rules work to the second. */
    private static function time(string $value): string
    {
        return substr($value, 0, 8);
    }
}
