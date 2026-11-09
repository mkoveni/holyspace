<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence;

use App\Media\Application\Port\MediaFileCatalog;
use Doctrine\DBAL\Connection;

final readonly class DbalMediaFileCatalog implements MediaFileCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    public function isKnown(string $path): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM media_files WHERE path = ?', [$path]);
    }
}
