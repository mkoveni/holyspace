<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Infrastructure\Persistence;

use App\ChildrenMinistry\Messaging\Application\Contract\VerifiedEmailReader;
use App\ChildrenMinistry\Messaging\Domain\Repository\VerifiedEmailRepository;
use App\ChildrenMinistry\Messaging\Domain\VerifiedGuardianEmail;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalVerifiedEmailStore implements VerifiedEmailReader, VerifiedEmailRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function emails(string $guardianId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM children_verified_emails WHERE guardian_id = :guardian ORDER BY verified_at DESC LIMIT 100', ['guardian' => $guardianId]);

        return array_map(self::email(...), $rows);
    }

    public function verification(string $id): ?VerifiedGuardianEmail
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_verified_emails WHERE id = :id', ['id' => $id]);

        return false === $row ? null : self::email($row);
    }

    public function verify(VerifiedGuardianEmail $email): void
    {
        $this->connection->insert('children_verified_emails', [
            'id' => $email->id,
            'guardian_id' => $email->guardianId,
            'contact_id' => $email->contactId,
            'email' => $email->email,
            'verified_by' => $email->verifiedBy,
            'verified_at' => Timestamps::toDatabase($email->verifiedAt),
            'valid_until' => Timestamps::toDatabase($email->validUntil),
            'evidence' => $email->evidence,
        ]);
    }

    public function revoke(string $id, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement('UPDATE children_verified_emails SET revoked_at = :at WHERE id = :id AND revoked_at IS NULL', ['at' => Timestamps::toDatabase($at), 'id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private static function email(array $row): VerifiedGuardianEmail
    {
        return new VerifiedGuardianEmail(
            (string) $row['id'],
            (string) $row['guardian_id'],
            (string) $row['contact_id'],
            (string) $row['email'],
            (string) $row['verified_by'],
            Timestamps::fromDatabase((string) $row['verified_at']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            (string) $row['evidence'],
            null === $row['revoked_at'] ? null : Timestamps::fromDatabase((string) $row['revoked_at']),
        );
    }
}
