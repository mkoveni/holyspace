<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Application\IdGenerator;
use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Domain\Credential;
use App\Volunteering\Domain\CredentialType;
use App\Volunteering\Domain\Repository\VolunteerRepository;
use App\Volunteering\Domain\Volunteer;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Java `JooqVolunteerStore` writes: profiles, the credential catalogue, credentials and availability revisions. */
final readonly class DbalVolunteerRepository implements VolunteerRepository
{
    public function __construct(private Connection $connection, private IdGenerator $ids)
    {
    }

    public function lock(string $id): ?Volunteer
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'person_id', 'account_id', 'status', 'availability_revision', 'version', 'created_at', 'updated_at')
            ->from('serving_volunteers')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : Volunteer::reconstitute(
            (string) $row['id'],
            (string) $row['person_id'],
            null === $row['account_id'] ? null : (string) $row['account_id'],
            (string) $row['status'],
            (int) $row['availability_revision'],
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['created_at']),
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }

    public function insert(Volunteer $volunteer): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_volunteers', [
            'id' => $volunteer->id,
            ...$this->columns($volunteer),
        ]));
    }

    public function save(Volunteer $volunteer): void
    {
        ServingWrites::saved(ServingWrites::guard(fn (): int|string => $this->connection->update(
            'serving_volunteers',
            $this->columns($volunteer),
            ['id' => $volunteer->id, 'version' => $volunteer->version - 1],
            ['version' => ParameterType::INTEGER],
        )));
    }

    public function addCredentialType(CredentialType $type): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_credential_types', ['id' => $type->id, 'code' => $type->code, 'name' => $type->name]));
    }

    public function addCredential(Credential $credential): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_credentials', [
            'id' => $credential->id,
            'volunteer_id' => $credential->volunteerId,
            'type_id' => $credential->typeId,
            'valid_from' => Timestamps::toDatabase($credential->validFrom),
            'valid_until' => Timestamps::toDatabase($credential->validUntil),
            'evidence' => $credential->evidence,
            'verified_by' => $credential->verifiedBy,
            'verified_at' => Timestamps::toDatabase($credential->verifiedAt),
            'revoked_at' => null,
        ]));
    }

    public function revokeCredential(string $id, DateTimeImmutable $at): void
    {
        $this->connection->createQueryBuilder()
            ->update('serving_credentials')
            ->set('revoked_at', ':at')
            ->where('id = :id')
            ->andWhere('revoked_at IS NULL')
            ->setParameter('at', Timestamps::toDatabase($at))
            ->setParameter('id', $id)
            ->executeStatement();
    }

    public function addAvailability(string $volunteerId, int $revision, array $weekly, array $exceptions): void
    {
        foreach ($weekly as $rule) {
            $this->connection->insert('serving_availability_rules', [
                'id' => $this->ids->uuid(),
                'volunteer_id' => $volunteerId,
                'revision' => $revision,
                'day_of_week' => $rule->dayOfWeek,
                'starts_at' => $rule->startsAt,
                'ends_at' => $rule->endsAt,
                'time_zone' => $rule->timeZone,
                'valid_from' => $rule->validFrom,
                'valid_until' => $rule->validUntil,
            ], ['revision' => ParameterType::INTEGER, 'day_of_week' => ParameterType::INTEGER]);
        }
        foreach ($exceptions as $exception) {
            $this->connection->insert('serving_availability_exceptions', [
                'id' => $this->ids->uuid(),
                'volunteer_id' => $volunteerId,
                'revision' => $revision,
                'starts_at' => Timestamps::toDatabase($exception->startsAt),
                'ends_at' => Timestamps::toDatabase($exception->endsAt),
                'available' => $exception->available,
            ], ['revision' => ParameterType::INTEGER, 'available' => ParameterType::BOOLEAN]);
        }
    }

    /** @return array<string, mixed> */
    private function columns(Volunteer $volunteer): array
    {
        return [
            'person_id' => $volunteer->personId,
            'account_id' => $volunteer->accountId,
            'status' => $volunteer->status,
            'availability_revision' => $volunteer->availabilityRevision,
            'version' => $volunteer->version,
            'created_at' => Timestamps::toDatabase($volunteer->createdAt),
            'updated_at' => Timestamps::toDatabase($volunteer->updatedAt),
        ];
    }
}
