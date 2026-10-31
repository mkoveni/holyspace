<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** Keeps stable association rows so audit history describes actual business changes. */
final class AssociationSynchronizer
{
    public function sync(Connection $db, string $table, array $owner, string $keyColumn, array $rows, bool $generateId = false): void
    {
        // Table/column names are constants owned by repository adapters, never request input.
        $query = $db->createQueryBuilder()->select('*')->from($table);
        foreach ($owner as $column => $value) {
            $query->andWhere($column.' = :'.$column)->setParameter($column, $value);
        }
        $existing = array_column($query->fetchAllAssociative(), null, $keyColumn);
        foreach ($rows as $row) {
            $key = $row[$keyColumn];
            $previous = $existing[$key] ?? null;
            if (null === $previous) {
                $db->insert($table, $owner + $row + ($generateId ? ['id' => Uuid::v7()->toRfc4122()] : []));
            } else {
                $changes = array_filter($row, static fn ($value, $column) => (string) $value !== (string) $previous[$column], ARRAY_FILTER_USE_BOTH);
                if ([] !== $changes) {
                    $db->update($table, $changes, $owner + [$keyColumn => $key]);
                }
            }
            unset($existing[$key]);
        }
        foreach ($existing as $key => $_) {
            $db->delete($table, $owner + [$keyColumn => $key]);
        }
    }
}
