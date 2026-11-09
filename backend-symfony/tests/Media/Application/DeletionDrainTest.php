<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\DeletionDrain;
use App\Media\Application\Port\PendingFile;
use PHPUnit\Framework\TestCase;

final class DeletionDrainTest extends TestCase
{
    private const string MAIN = 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.webp';
    private const string VARIANT = 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567@640.webp';

    public function testARemovedPictureLosesItsFileAndItsSmallerCopiesThenLeavesTheQueue(): void
    {
        $files = new MemoryFileStore();
        $other = 'event/00/00/00000000-0000-4000-8000-000000000001.webp';
        foreach ([self::MAIN, self::VARIANT, $other] as $path) {
            $files->put($path, 'bytes');
        }
        $queue = new MemoryDeletionQueue([new PendingFile('abcdef01-2345-4678-89ab-cdef01234567', self::MAIN)]);

        self::assertSame(1, new DeletionDrain($queue, $files)->run());

        self::assertSame([$other], array_keys($files->files));
        self::assertSame(['abcdef01-2345-4678-89ab-cdef01234567'], $queue->finished);
    }

    public function testFilesAlreadyGoneAreNotAnError(): void
    {
        $queue = new MemoryDeletionQueue([new PendingFile('abcdef01-2345-4678-89ab-cdef01234567', self::MAIN)]);

        self::assertSame(1, new DeletionDrain($queue, new MemoryFileStore())->run());
        self::assertCount(1, $queue->finished);
    }

    public function testAnUnrecognisedPathStillLeavesTheQueueWithoutTouchingOtherFiles(): void
    {
        $files = new MemoryFileStore();
        $files->files = [self::MAIN => 'm'];
        $queue = new MemoryDeletionQueue([new PendingFile('f', 'nonsense/x.webp')]);

        new DeletionDrain($queue, $files)->run();

        self::assertSame(['f'], $queue->finished);
        self::assertArrayHasKey(self::MAIN, $files->files);
    }

    public function testTheBatchSizeIsRespected(): void
    {
        $queue = new MemoryDeletionQueue([new PendingFile('a', self::MAIN), new PendingFile('b', self::MAIN), new PendingFile('c', self::MAIN)]);

        self::assertSame(2, new DeletionDrain($queue, new MemoryFileStore())->run(2));
    }
}
