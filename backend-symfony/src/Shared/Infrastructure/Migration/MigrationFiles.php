<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Migration;

/** Reads Flyway-style versioned files (V1__name.sql) in numeric order, so V2 sorts before V10. */
final readonly class MigrationFiles
{
    public function __construct(private string $directory)
    {
    }

    /** @return list<SqlMigration> */
    public function all(): array
    {
        $files = glob(rtrim($this->directory, '/').'/V*.sql');
        if (false === $files) {
            throw new MigrationException('Cannot read migration directory '.$this->directory);
        }

        $migrations = [];
        foreach ($files as $file) {
            if (1 !== preg_match('/^V(\d+)__(.+)\.sql$/', basename($file), $match)) {
                throw new MigrationException('Invalid migration file name: '.basename($file));
            }
            $version = (int) $match[1];
            if (isset($migrations[$version])) {
                throw new MigrationException('Duplicate migration version V'.$version);
            }
            $sql = file_get_contents($file);
            if (false === $sql) {
                throw new MigrationException('Cannot read migration '.basename($file));
            }
            $migrations[$version] = new SqlMigration(
                $version,
                $match[2],
                $sql,
                hash('sha256', str_replace("\r\n", "\n", $sql)),
            );
        }
        ksort($migrations);

        return array_values($migrations);
    }
}
