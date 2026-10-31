<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence\DBAL;

use App\People\Domain\Enum\FamilyRole;
use App\People\Domain\Model\Family;
use App\People\Domain\Model\FamilyMember;
use App\People\Domain\ValueObject\FamilyId;
use App\People\Domain\ValueObject\PersonId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;

final class FamilyMapper
{
    public function toDomain(array $row, array $members = []): Family
    {
        $mapped = array_map(static fn (array $member): FamilyMember => FamilyMember::reconstitute(
            PersonId::fromString((string) $member['person_id']),
            FamilyRole::from((string) $member['role']),
            new DateTimeImmutable((string) $member['created_at']),
        ), $members);

        return Family::reconstitute(
            FamilyId::fromString((string) $row['id']),
            (string) $row['name'],
            new AuditTimestamps(new DateTimeImmutable((string) $row['created_at']), new DateTimeImmutable((string) $row['updated_at'])),
            $mapped,
            isset($row['primary_contact_person_id']) ? PersonId::fromString($row['primary_contact_person_id']) : null,
        );
    }

    public function toRow(Family $family): array
    {
        return [
            'id' => $family->id()->toString(), 'name' => $family->name(),
            'primary_contact_person_id' => $family->primaryContact()?->toString(),
            'created_at' => $family->createdAt()->format('Y-m-d H:i:s'), 'updated_at' => $family->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
