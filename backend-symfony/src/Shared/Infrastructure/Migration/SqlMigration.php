<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Migration;

final readonly class SqlMigration
{
    public function __construct(
        public int $version,
        public string $description,
        public string $sql,
        public string $checksum,
    ) {
    }
}
