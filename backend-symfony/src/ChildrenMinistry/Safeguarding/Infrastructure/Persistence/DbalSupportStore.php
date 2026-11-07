<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Infrastructure\Persistence;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportReader;
use App\ChildrenMinistry\Safeguarding\Application\SupportRecordView;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SupportRepository;
use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalSupportStore implements SupportRepository, SupportReader
{
    private const string TABLE = 'children_support_records';

    public function __construct(private Connection $connection)
    {
    }

    public function supportRecordForUpdate(string $childId): ?SupportRecord
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::TABLE)->where('child_id = :child')->setParameter('child', $childId)->forUpdate()->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $v = self::view($row);

        return SupportRecord::reconstitute($v->childId, $v->allergies, $v->careInstructions, $v->restrictedNotes, $v->reviewOn, $v->retainUntil, $v->version, $v->updatedAt);
    }

    public function insertSupportRecord(SupportRecord $record): void
    {
        $this->connection->insert(self::TABLE, ['child_id' => $record->childId] + self::columns($record));
    }

    public function updateSupportRecord(SupportRecord $record): void
    {
        $changed = $this->connection->update(self::TABLE, self::columns($record), ['child_id' => $record->childId, 'version' => $record->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function purge(string $childId, CalendarDate $before): void
    {
        $this->connection->executeStatement('DELETE FROM '.self::TABLE.' WHERE child_id = ? AND retain_until < ?', [$childId, $before->iso()]);
    }

    public function supportRecord(string $childId): ?SupportRecordView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::TABLE)->where('child_id = :child')->setParameter('child', $childId)->fetchAssociative();

        return false === $row ? null : self::view($row);
    }

    public function expiredSupport(CalendarDate $date, int $limit): array
    {
        $ids = $this->connection->createQueryBuilder()->select('child_id')->from(self::TABLE)
            ->where('retain_until < :date')->setParameter('date', $date->iso())
            ->orderBy('child_id')->setMaxResults($limit)->fetchFirstColumn();

        return array_values(array_map(static fn (mixed $id): string => (string) $id, $ids));
    }

    /** @return array<string, mixed> */
    private static function columns(SupportRecord $record): array
    {
        return [
            'allergies' => $record->allergies,
            'care_instructions' => $record->careInstructions,
            'restricted_notes' => $record->restrictedNotes,
            'review_on' => $record->reviewOn->iso(),
            'retain_until' => $record->retainUntil->iso(),
            'version' => $record->version,
            'updated_at' => Timestamps::toDatabase($record->updatedAt),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function view(array $row): SupportRecordView
    {
        return new SupportRecordView(
            (string) $row['child_id'],
            (string) $row['allergies'],
            (string) $row['care_instructions'],
            (string) $row['restricted_notes'],
            CalendarDate::parse(substr((string) $row['review_on'], 0, 10)),
            CalendarDate::parse(substr((string) $row['retain_until'], 0, 10)),
            (int) $row['version'],
            Timestamps::fromDatabase((string) $row['updated_at']),
        );
    }
}
