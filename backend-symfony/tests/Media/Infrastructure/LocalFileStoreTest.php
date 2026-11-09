<?php

declare(strict_types=1);

namespace App\Tests\Media\Infrastructure;

use App\Media\Domain\MediaException;
use App\Media\Infrastructure\Health\StorageHealthCheck;
use App\Media\Infrastructure\LocalFileStore;
use PHPUnit\Framework\TestCase;

final class LocalFileStoreTest extends TestCase
{
    private const string PATH = 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.webp';

    private string $root;
    private LocalFileStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/media-store-'.bin2hex(random_bytes(6));
        $this->store = new LocalFileStore($this->root, '/unused');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
    }

    public function testAFileRoundTripsAndLeavesNoTemporaryFileBehind(): void
    {
        $this->store->put(self::PATH, 'bytes');

        self::assertSame('bytes', $this->store->read(self::PATH));
        self::assertSame('bytes', file_get_contents($this->root.'/'.self::PATH));
        self::assertSame([], glob($this->root.'/tmp/*'));
        self::assertSame('0640', substr(sprintf('%o', fileperms($this->root.'/'.self::PATH)), -4));
    }

    public function testARelativeRootIsResolvedAgainstTheProjectDirectory(): void
    {
        $store = new LocalFileStore('media', $this->root);
        $store->put(self::PATH, 'x');

        self::assertFileExists($this->root.'/media/'.self::PATH);
    }

    public function testReplacingAPathOverwritesItAtomically(): void
    {
        $this->store->put(self::PATH, 'one');
        $this->store->put(self::PATH, 'two');

        self::assertSame('two', $this->store->read(self::PATH));
    }

    public function testMissingFilesAreNotFoundAndDeletingThemIsHarmless(): void
    {
        try {
            $this->store->read(self::PATH);
            self::fail('Expected a not-found failure.');
        } catch (MediaException $e) {
            self::assertSame('The requested image was not found.', $e->getMessage());
        }
        $this->store->delete(self::PATH);
        $this->store->put(self::PATH, 'x');
        $this->store->delete(self::PATH);
        self::assertFileDoesNotExist($this->root.'/'.self::PATH);
    }

    public function testOnlyPathsOfTheExactShapeAreEverTouched(): void
    {
        file_put_contents($this->root.'-secret', 'secret');
        $unsafe = ['../'.basename($this->root).'-secret', '/etc/passwd', 'event/../../x.webp', 'event/ab/cd/not-a-uuid.webp', self::PATH.'/..', 'EVENT/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.webp', 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.php', "event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.webp\n"];
        foreach ($unsafe as $path) {
            foreach ([fn () => $this->store->read($path), fn () => $this->store->put($path, 'x'), fn () => $this->store->delete($path)] as $operation) {
                try {
                    $operation();
                    self::fail('Expected '.$path.' to be refused.');
                } catch (MediaException) {
                    self::addToAssertionCount(1);
                }
            }
        }
        self::assertSame('secret', file_get_contents($this->root.'-secret'));
        unlink($this->root.'-secret');
    }

    public function testVariantPathsAreAccepted(): void
    {
        $this->store->put('event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567@640.webp', 'v');

        self::assertSame('v', $this->store->read('event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567@640.webp'));
    }

    public function testAllListsPicturesWithTheirAgeAndIgnoresTemporaryFiles(): void
    {
        $this->store->put(self::PATH, 'x');
        mkdir($this->root.'/tmp', 0750, true);
        file_put_contents($this->root.'/tmp/half-written', 'x');
        touch($this->root.'/'.self::PATH, 1_700_000_000);

        self::assertSame([self::PATH => 1_700_000_000], iterator_to_array($this->store->all()));
    }

    public function testAllOfAnEmptyOrMissingRootIsEmpty(): void
    {
        self::assertSame([], iterator_to_array($this->store->all()));
    }

    public function testOldTemporaryFilesArePurgedAndNewOnesKept(): void
    {
        mkdir($this->root.'/tmp', 0750, true);
        file_put_contents($this->root.'/tmp/old', 'x');
        file_put_contents($this->root.'/tmp/new', 'x');
        touch($this->root.'/tmp/old', 1_000);

        self::assertSame(1, $this->store->purgeTemporary(2_000));
        self::assertFileDoesNotExist($this->root.'/tmp/old');
        self::assertFileExists($this->root.'/tmp/new');
    }

    public function testAnUnwritableRootFailsTheWriteAndTheHealthCheck(): void
    {
        file_put_contents($this->root, 'a file where the directory should be');
        $store = new LocalFileStore($this->root, '/unused');

        self::assertFalse(new StorageHealthCheck($store)->healthy());
        $this->expectException(MediaException::class);
        $store->put(self::PATH, 'x');
    }

    public function testAHealthyRootIsCreatedOnFirstCheck(): void
    {
        self::assertTrue(new StorageHealthCheck($this->store)->healthy());
        self::assertDirectoryExists($this->root);
    }
}
