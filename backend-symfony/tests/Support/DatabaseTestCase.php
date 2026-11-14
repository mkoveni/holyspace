<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Infrastructure\Migration\MigrationFiles;
use App\Shared\Infrastructure\Migration\SqlMigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base for tests that need the real PostgreSQL schema. The test database is created on demand and
 * rebuilt from the Flyway SQL files once per run. The rows those files seed (administrator role,
 * permissions, templates) are snapshotted, and every test starts from exactly that state.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    private static bool $schemaReady = false;

    protected Connection $connection;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        if (!self::$schemaReady) {
            self::prepareSchema($this->connection);
            self::$schemaReady = true;
        }
        self::restoreSeedData($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    /**
     * A plain run rebuilds the schema from scratch. A parallel worker (paratest starts a new PHP process per test file, so
     * this would otherwise run hundreds of times, and the simultaneous drops exhaust the server's lock table) rebuilds its
     * own database only when the migration files changed since it was last built; CI always starts from an empty server.
     */
    private static function prepareSchema(Connection $connection): void
    {
        if ('' === (string) getenv('TEST_TOKEN')) {
            self::rebuildSchema($connection);

            return;
        }
        $stamp = 'onechurch-test-schema:'.self::migrationsFingerprint();
        $current = $connection->fetchOne("SELECT obj_description('public'::regnamespace, 'pg_namespace')");
        $seeded = false !== $connection->fetchOne("SELECT 1 FROM pg_namespace WHERE nspname = 'seed'");
        if ($current === $stamp && $seeded) {
            return;
        }
        $lock = fopen(sys_get_temp_dir().'/onechurch-test-schema.lock', 'c');
        if (false !== $lock) {
            flock($lock, \LOCK_EX); // one rebuild at a time across workers keeps the lock table small
        }
        self::rebuildSchema($connection);
        $connection->executeStatement('COMMENT ON SCHEMA public IS '.$connection->quote($stamp));
        if (false !== $lock) {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    private static function migrationsFingerprint(): string
    {
        $files = glob(dirname(__DIR__, 2).'/migrations/*.sql') ?: [];
        sort($files);

        return sha1(implode("\n", array_map(static fn (string $f): string => basename($f).':'.sha1_file($f), $files)));
    }

    /** Drops everything and re-applies every migration, proving V1-V30 build from scratch. */
    public static function rebuildSchema(Connection $connection): void
    {
        $connection->executeStatement('DROP SCHEMA IF EXISTS seed CASCADE');
        $connection->executeStatement('DROP SCHEMA public CASCADE');
        $connection->executeStatement('CREATE SCHEMA public');
        new SqlMigrationRunner($connection, new MigrationFiles(dirname(__DIR__, 2).'/migrations'))->migrate();

        $connection->executeStatement('CREATE SCHEMA seed');
        foreach (self::dataTables($connection) as $table) {
            if ((int) $connection->fetchOne("SELECT count(*) FROM public.$table") > 0) {
                $connection->executeStatement("CREATE TABLE seed.$table AS SELECT * FROM public.$table");
            }
        }
    }

    public static function createDatabaseIfMissing(): void
    {
        $params = new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL'));
        $name = (string) ($params['dbname'] ?? '');
        $params['dbname'] = 'postgres';

        $admin = DriverManager::getConnection($params);
        if (false === $admin->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$name])) {
            $admin->executeStatement('CREATE DATABASE '.$admin->quoteSingleIdentifier($name));
        }
        // Throwaway data: a commit need not wait for the disk. Only this database is affected, never the server's others.
        $admin->executeStatement('ALTER DATABASE '.$admin->quoteSingleIdentifier($name).' SET synchronous_commit = off');
        $admin->close();
    }

    private static function restoreSeedData(Connection $connection): void
    {
        $tables = self::dataTables($connection);
        if ([] === $tables) {
            return;
        }

        $connection->transactional(static function () use ($connection, $tables): void {
            $connection->executeStatement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
            $connection->executeStatement('SET LOCAL session_replication_role = replica');
            foreach ($connection->fetchFirstColumn("SELECT quote_ident(tablename) FROM pg_tables WHERE schemaname = 'seed'") as $table) {
                $connection->executeStatement("INSERT INTO public.$table SELECT * FROM seed.$table");
            }
        });
    }

    /** @return list<string> quoted names of every table except the migration history */
    private static function dataTables(Connection $connection): array
    {
        /** @var list<string> $tables */
        $tables = $connection->fetchFirstColumn(
            "SELECT quote_ident(tablename) FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'schema_migrations'",
        );

        return $tables;
    }
}
