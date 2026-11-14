<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Migration;

use App\Shared\Infrastructure\Migration\MigrationException;
use App\Shared\Infrastructure\Migration\MigrationFiles;
use App\Shared\Infrastructure\Migration\SqlMigrationRunner;
use App\Tests\Support\DatabaseTestCase;

final class SqlMigrationRunnerTest extends DatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/runner-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS runner_probe');
        $this->connection->executeStatement('DELETE FROM schema_migrations WHERE version >= 1000');
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testEveryMigrationBuildsTheSchemaFromScratch(): void
    {
        self::rebuildSchema($this->connection);

        $applied = $this->connection->fetchFirstColumn('SELECT version FROM schema_migrations ORDER BY version');

        self::assertSame(range(1, 46), array_map('intval', $applied));
        self::assertNotNull($this->connection->fetchOne("SELECT to_regclass('public.user_accounts')"));
        self::assertNotNull($this->connection->fetchOne("SELECT to_regclass('public.children_audit')"));
        self::assertSame(
            1,
            (int) $this->connection->fetchOne("SELECT count(*) FROM roles WHERE name = 'administrator'"),
            'Seed rows from V1 must survive the build.',
        );
    }

    public function testRunningAgainAppliesNothing(): void
    {
        $runner = new SqlMigrationRunner($this->connection, new MigrationFiles(dirname(__DIR__, 4).'/migrations'));

        self::assertSame([], $runner->migrate());
    }

    public function testFailedMigrationRollsBackCompletely(): void
    {
        $this->copyRealMigrations();
        file_put_contents($this->directory.'/V1000__broken.sql', 'CREATE TABLE runner_probe (id INT); SELECT * FROM table_that_does_not_exist;');
        $files = new MigrationFiles($this->directory);

        try {
            new SqlMigrationRunner($this->connection, $files)->migrate();
            self::fail('Expected the broken migration to fail.');
        } catch (MigrationException $error) {
            self::assertStringContainsString('V1000', $error->getMessage());
        }

        self::assertNull($this->connection->fetchOne("SELECT to_regclass('public.runner_probe')"));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM schema_migrations WHERE version = 1000'));
    }

    public function testChangedAppliedFileIsRejected(): void
    {
        $this->copyRealMigrations();
        file_put_contents($this->directory.'/V1__identity_access.sql', "-- edited\n", FILE_APPEND);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Checksum mismatch for applied migration V1');
        new SqlMigrationRunner($this->connection, new MigrationFiles($this->directory))->migrate();
    }

    public function testMissingAppliedFileIsRejected(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('is missing from the migration directory');
        new SqlMigrationRunner($this->connection, new MigrationFiles($this->directory))->migrate();
    }

    private function copyRealMigrations(): void
    {
        foreach (glob(dirname(__DIR__, 4).'/migrations/V*.sql') ?: [] as $file) {
            copy($file, $this->directory.'/'.basename($file));
        }
    }
}
