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
            self::rebuildSchema($this->connection);
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
