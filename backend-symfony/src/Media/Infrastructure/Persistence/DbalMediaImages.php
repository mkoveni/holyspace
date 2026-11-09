<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Contract\MediaImages;
use App\Media\Domain\ImageKind;
use App\Media\Domain\MediaException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** The cross-context lookup: one query for a whole page of owners, so a list never costs one query per row. */
final readonly class DbalMediaImages implements MediaImages
{
    public function __construct(private Connection $connection)
    {
    }

    public function fileIds(string $kind, array $ownerIds): array
    {
        $imageKind = ImageKind::tryFrom($kind) ?? throw MediaException::invalid('Unknown image kind.');
        if ([] === $ownerIds) {
            return [];
        }
        $found = [];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT owner_id, file_id FROM media_slots WHERE owner_type = ? AND kind = ? AND file_id IS NOT NULL AND owner_id IN (?)',
            [$imageKind->ownerType()->value, $imageKind->value, array_map('strtolower', $ownerIds)],
            [ParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING],
        );
        foreach ($rows as $row) {
            $found[(string) $row['owner_id']] = (string) $row['file_id'];
        }

        return $found;
    }
}
