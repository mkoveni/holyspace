<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Migration;

use Doctrine\DBAL\Connection;
use PDO;
use Throwable;

/**
 * Applies the Flyway-format SQL files, which stay the schema source of truth. Each file runs
 * in its own transaction. Applied files are checksum-validated and never re-run.
 */
final readonly class SqlMigrationRunner
{
    private const int LOCK_KEY = 7821940201;

    public function __construct(private Connection $connection, private MigrationFiles $files)
    {
    }

    /** @return list<int> versions applied by this run */
    public function migrate(): array
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            .'version INTEGER PRIMARY KEY, description VARCHAR(200) NOT NULL, '
            .'checksum CHAR(64) NOT NULL, applied_at TIMESTAMPTZ NOT NULL DEFAULT now())',
        );

        // Session lock so concurrent deploys cannot apply the same file twice.
        $this->connection->fetchOne('SELECT pg_advisory_lock(?)', [self::LOCK_KEY]);
        try {
            return $this->applyPending();
        } finally {
            $this->connection->fetchOne('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);
        }
    }

    /** @return list<int> */
    private function applyPending(): array
    {
        $available = [];
        foreach ($this->files->all() as $migration) {
            $available[$migration->version] = $migration;
        }

        /** @var array<int, string> $applied */
        $applied = [];
        foreach ($this->connection->fetchAllAssociative('SELECT version, checksum FROM schema_migrations') as $row) {
            $applied[(int) $row['version']] = (string) $row['checksum'];
        }

        foreach ($applied as $version => $checksum) {
            if (!isset($available[$version])) {
                throw new MigrationException(sprintf('Applied migration V%d is missing from the migration directory.', $version));
            }
            if ($available[$version]->checksum !== trim($checksum)) {
                throw new MigrationException(sprintf('Checksum mismatch for applied migration V%d; applied files must not change.', $version));
            }
        }

        $highest = [] === $applied ? 0 : max(array_keys($applied));
        $done = [];
        foreach ($available as $version => $migration) {
            if (isset($applied[$version])) {
                continue;
            }
            if ($version < $highest) {
                throw new MigrationException(sprintf('Migration V%d is older than the latest applied migration V%d.', $version, $highest));
            }
            $this->apply($migration);
            $done[] = $version;
        }

        return $done;
    }

    private function apply(SqlMigration $migration): void
    {
        $pdo = $this->connection->getNativeConnection();
        if (!$pdo instanceof PDO) {
            throw new MigrationException('SQL migrations require the pdo_pgsql driver.');
        }

        $this->connection->beginTransaction();
        try {
            // PDO::exec runs multi-statement files, which prepared statements cannot.
            $pdo->exec($migration->sql);
            $this->connection->insert('schema_migrations', [
                'version' => $migration->version,
                'description' => $migration->description,
                'checksum' => $migration->checksum,
            ]);
            $this->connection->commit();
        } catch (Throwable $error) {
            $this->connection->rollBack();

            throw new MigrationException(sprintf('Migration V%d failed: %s', $migration->version, $error->getMessage()), 0, $error);
        }
    }
}
