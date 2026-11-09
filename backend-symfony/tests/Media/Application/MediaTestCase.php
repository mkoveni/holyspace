<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\ImageAccess;
use App\Media\Application\ImageOwners;
use App\Media\Domain\ImageKind;
use App\Media\Domain\ImageSlot;
use App\Media\Domain\MediaFile;
use App\Media\Domain\SlotKey;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use App\Shared\Domain\Revision;
use App\Tests\People\Application\SequentialIds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** The in-memory world every Media handler test runs in: one event owner, empty storage, a fixed clock. */
abstract class MediaTestCase extends TestCase
{
    protected const string ACTOR = '99999999-9999-4999-8999-999999999999';
    protected const string OWNER = 'e0000000-0000-4000-8000-000000000001';

    protected FakeOwner $owner;
    protected InMemoryImageSlots $slots;
    protected MemoryFileStore $files;
    protected RecordingDeletions $deletions;
    protected RecordingMediaAudit $audit;
    protected FakeImageProcessor $processor;
    protected SequentialIds $ids;
    protected MockClock $clock;
    protected ImageAccess $access;

    protected function setUp(): void
    {
        $this->owner = new FakeOwner('event');
        $this->slots = new InMemoryImageSlots();
        $this->files = new MemoryFileStore();
        $this->deletions = new RecordingDeletions();
        $this->audit = new RecordingMediaAudit();
        $this->processor = new FakeImageProcessor();
        $this->ids = new SequentialIds();
        $this->clock = new MockClock('2026-11-09 10:00:00');
        $this->access = new ImageAccess(new ImageOwners([$this->owner]));
    }

    protected function key(ImageKind $kind = ImageKind::Event): SlotKey
    {
        return SlotKey::of($kind, self::OWNER);
    }

    /** Puts a picture in the slot at the given version, as an earlier upload would have left it. */
    protected function seedFile(string $fileId, int $version, ImageKind $kind = ImageKind::Event): MediaFile
    {
        $key = $this->key($kind);
        $file = MediaFile::create($key, $fileId, 1280, 720, 100, str_repeat('a', 64), new DateTimeImmutable('2026-11-01'));
        $this->slots->seed(ImageSlot::reconstitute($key, $file, Revision::reconstitute($version, new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01'))));
        $this->files->files[$file->path] = 'old-main';
        $this->files->files[$file->variantPath(640)] = 'old-variant';

        return $file;
    }

    /** @param callable(): mixed $action */
    protected function assertFailsWith(ErrorKind $kind, callable $action): void
    {
        try {
            $action();
        } catch (ContextException $e) {
            self::assertSame($kind, $e->kind(), $e->getMessage());

            return;
        }
        self::fail('Expected a '.$kind->name.' failure.');
    }

    protected function assertNothingHappened(): void
    {
        self::assertSame([], $this->files->files, 'no file written');
        self::assertSame(0, $this->slots->saves, 'no slot saved');
        self::assertSame([], $this->audit->rows, 'no audit row');
        self::assertSame([], $this->deletions->queued, 'no deletion queued');
    }
}
