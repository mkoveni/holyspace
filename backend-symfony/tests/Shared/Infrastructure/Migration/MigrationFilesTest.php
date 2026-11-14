<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Migration;

use App\Shared\Infrastructure\Migration\MigrationException;
use App\Shared\Infrastructure\Migration\MigrationFiles;
use PHPUnit\Framework\TestCase;

final class MigrationFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/migrations-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testOrdersVersionsNumerically(): void
    {
        foreach ([10, 2, 1] as $version) {
            file_put_contents(sprintf('%s/V%d__thing.sql', $this->directory, $version), 'SELECT 1;');
        }

        $versions = array_map(static fn ($m): int => $m->version, new MigrationFiles($this->directory)->all());

        self::assertSame([1, 2, 10], $versions);
    }

    public function testChecksumIgnoresLineEndingStyle(): void
    {
        file_put_contents($this->directory.'/V1__a.sql', "SELECT 1;\r\nSELECT 2;\r\n");
        $crlf = new MigrationFiles($this->directory)->all()[0]->checksum;
        file_put_contents($this->directory.'/V1__a.sql', "SELECT 1;\nSELECT 2;\n");

        self::assertSame($crlf, new MigrationFiles($this->directory)->all()[0]->checksum);
    }

    public function testRejectsMalformedFileNames(): void
    {
        file_put_contents($this->directory.'/V1_wrong.sql', 'SELECT 1;');

        $this->expectException(MigrationException::class);
        new MigrationFiles($this->directory)->all();
    }

    public function testRealMigrationDirectoryHasContiguousVersions(): void
    {
        $versions = array_map(static fn ($m): int => $m->version, new MigrationFiles(dirname(__DIR__, 4).'/migrations')->all());

        self::assertSame(range(1, 45), $versions);
    }
}
