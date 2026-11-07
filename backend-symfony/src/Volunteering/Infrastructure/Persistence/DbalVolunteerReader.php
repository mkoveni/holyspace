<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\CredentialView;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use Doctrine\DBAL\Connection;

final readonly class DbalVolunteerReader implements VolunteerReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function volunteer(string $id): ?VolunteerView
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'person_id', 'status', 'availability_revision')
            ->from('serving_volunteers')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new VolunteerView((string) $row['id'], (string) $row['person_id'], (string) $row['status'], (int) $row['availability_revision']);
    }

    public function credentialTypes(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name')
            ->from('serving_credential_types')
            ->orderBy('name')
            ->addOrderBy('id')
            ->setMaxResults(500)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): CredentialTypeView => new CredentialTypeView((string) $row['id'], (string) $row['name']), $rows));
    }

    public function credentials(string $volunteerId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('type_id', 'valid_from', 'valid_until', 'revoked_at')
            ->from('serving_credentials')
            ->where('volunteer_id = :volunteer')
            ->setParameter('volunteer', $volunteerId)
            ->orderBy('verified_at', 'DESC')
            ->setMaxResults(500)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): CredentialView => new CredentialView((string) $row['type_id'], ValidityWindows::fromRow($row)), $rows));
    }

    public function availability(string $volunteerId, int $revision): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('day_of_week', 'starts_at', 'ends_at', 'time_zone', 'valid_from', 'valid_until')
            ->from('serving_availability_rules')
            ->where('volunteer_id = :volunteer')
            ->andWhere('revision = :revision')
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('revision', $revision)
            ->orderBy('day_of_week')
            ->addOrderBy('starts_at')
            ->setMaxResults(100)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): AvailabilityRule => new AvailabilityRule(
            (int) $row['day_of_week'],
            self::time((string) $row['starts_at']),
            self::time((string) $row['ends_at']),
            (string) $row['time_zone'],
            (string) $row['valid_from'],
            (string) $row['valid_until'],
        ), $rows));
    }

    public function exceptions(string $volunteerId, int $revision): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('starts_at', 'ends_at', 'available')
            ->from('serving_availability_exceptions')
            ->where('volunteer_id = :volunteer')
            ->andWhere('revision = :revision')
            ->setParameter('volunteer', $volunteerId)
            ->setParameter('revision', $revision)
            ->orderBy('starts_at')
            ->setMaxResults(100)
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): AvailabilityException => new AvailabilityException(
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (bool) $row['available'],
        ), $rows));
    }

    /** TIME columns may carry fractional seconds; rules work to the second. */
    private static function time(string $value): string
    {
        return substr($value, 0, 8);
    }
}
