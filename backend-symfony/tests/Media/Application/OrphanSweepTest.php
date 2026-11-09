<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\OrphanSweep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OrphanSweepTest extends TestCase
{
    private const string KNOWN = 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567.webp';
    private const string KNOWN_VARIANT = 'event/ab/cd/abcdef01-2345-4678-89ab-cdef01234567@640.webp';
    private const string ORPHAN = 'event/00/00/00000000-0000-4000-8000-000000000001.webp';
    private const string ORPHAN_VARIANT = 'event/00/00/00000000-0000-4000-8000-000000000001@640.webp';

    public function testOnlyOldFilesNoRowRecordsAreRemovedAndVariantsFollowTheirMainFile(): void
    {
        $clock = new MockClock('2026-11-09 12:00:00');
        $old = $clock->now()->getTimestamp() - 2 * OrphanSweep::GRACE_SECONDS;
        $files = new MemoryFileStore();
        $files->files = [self::KNOWN => 'k', self::KNOWN_VARIANT => 'k', self::ORPHAN => 'o', self::ORPHAN_VARIANT => 'o', 'event/11/11/11111111-1111-4111-8111-111111111111.webp' => 'young orphan'];
        $files->modified = [self::KNOWN => $old, self::KNOWN_VARIANT => $old, self::ORPHAN => $old, self::ORPHAN_VARIANT => $old];
        $files->temporaryPurged = 3;

        $result = new OrphanSweep($files, new MemoryCatalog([self::KNOWN]), $clock)->run();

        self::assertSame(['orphans' => 2, 'temporary' => 3], $result);
        self::assertEqualsCanonicalizing([self::KNOWN, self::KNOWN_VARIANT, 'event/11/11/11111111-1111-4111-8111-111111111111.webp'], array_keys($files->files), 'a file under a day old may still be committing');
        self::assertSame($clock->now()->getTimestamp() - OrphanSweep::TEMPORARY_GRACE_SECONDS, $files->purgedBefore);
    }
}
