<?php

declare(strict_types=1);

namespace App\Tests\Media\Infrastructure\Persistence;

use App\Media\Domain\ImageKind;
use App\Media\Domain\ImageSlot;
use App\Media\Domain\MediaFile;
use App\Media\Domain\SlotKey;
use App\Media\Infrastructure\Persistence\DbalImageSlotStore;
use App\Media\Infrastructure\Persistence\DbalMediaAuditWriter;
use App\Media\Infrastructure\Persistence\DbalMediaFileCatalog;
use App\Media\Infrastructure\Persistence\DbalMediaImages;
use App\Media\Infrastructure\Persistence\DbalPendingDeletions;
use App\Shared\Domain\StaleRevisionException;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Tests\Support\DatabaseTestCase;
use Closure;
use DateTimeImmutable;
use LogicException;

/** The real PostgreSQL schema: slots, files, the cross-context lookup, the deletion queue and the audit table. */
final class MediaPersistenceTest extends DatabaseTestCase
{
    private const string OWNER = 'e0000000-0000-4000-8000-000000000001';
    private const string OTHER = 'e0000000-0000-4000-8000-000000000002';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FIRST = 'a0000000-0000-4000-8000-000000000001';
    private const string SECOND = 'a0000000-0000-4000-8000-000000000002';

    private DbalImageSlotStore $slots;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slots = new DbalImageSlotStore($this->connection, new AdvisoryLock($this->connection));
        $this->now = new DateTimeImmutable('2026-11-09 10:00:00+00:00');
    }

    public function testASlotThatWasNeverUsedIsVersionZeroAndSavingItCreatesVersionOne(): void
    {
        $key = $this->key();
        $slot = $this->locked($key);
        self::assertSame(0, $slot->version());
        self::assertSame(0, $this->slots->view($key)->version);

        $this->in(fn () => $this->slots->save($slot->replace($this->file(self::FIRST, $key), 0, $this->now)));

        $view = $this->slots->view($key);
        self::assertSame(1, $view->version);
        self::assertSame(self::FIRST, $view->image?->fileId);
        self::assertSame('event/a0/00/'.self::FIRST.'.webp', $view->image->path);
        self::assertSame(ImageKind::Event, $view->image->kind);
    }

    public function testReplacingAndClearingAdvanceTheVersionAndTheLoadedSlotMatches(): void
    {
        $key = $this->key();
        $this->in(function () use ($key): void {
            $this->slots->save($this->slots->lock($key, $this->now)->replace($this->file(self::FIRST, $key), 0, $this->now));
        });
        $this->in(function () use ($key): void {
            $slot = $this->slots->lock($key, $this->now);
            self::assertSame(self::FIRST, $slot->file?->id);
            $this->slots->save($slot->replace($this->file(self::SECOND, $key), 1, $this->now));
        });
        self::assertSame(self::SECOND, $this->slots->view($key)->image?->fileId);

        $this->in(function () use ($key): void {
            $this->slots->save($this->slots->lock($key, $this->now)->clear(2, $this->now));
        });

        $cleared = $this->slots->view($key);
        self::assertSame(3, $cleared->version, 'a tombstone keeps the version');
        self::assertNull($cleared->image);
    }

    public function testASaveFromAnOutdatedVersionIsRefusedAndChangesNothing(): void
    {
        $key = $this->key();
        $stale = $this->locked($key);
        $this->in(fn () => $this->slots->save($stale->replace($this->file(self::FIRST, $key), 0, $this->now)));

        try {
            $this->in(fn () => $this->slots->save($stale->replace($this->file(self::SECOND, $key), 0, $this->now)));
            self::fail('Expected a stale version.');
        } catch (StaleRevisionException) {
            self::assertSame(self::FIRST, $this->slots->view($key)->image?->fileId);
            self::assertSame(1, $this->slots->view($key)->version);
        }
    }

    public function testSlotsOfOtherOwnersAndKindsAreIndependent(): void
    {
        $this->in(function (): void {
            $this->slots->save($this->slots->lock($this->key(), $this->now)->replace($this->file(self::FIRST, $this->key()), 0, $this->now));
        });

        self::assertSame(0, $this->slots->view(SlotKey::of(ImageKind::EventBanner, self::OWNER))->version);
        self::assertSame(0, $this->slots->view(SlotKey::of(ImageKind::Event, self::OTHER))->version);
    }

    public function testAFileIsFoundByIdWhetherOrNotASlotStillHoldsIt(): void
    {
        $key = $this->key();
        $this->in(function () use ($key): void {
            $this->slots->save($this->slots->lock($key, $this->now)->replace($this->file(self::FIRST, $key), 0, $this->now));
        });

        self::assertSame(ImageKind::Event, $this->slots->file(self::FIRST)?->kind);
        self::assertNull($this->slots->file(self::SECOND));
    }

    public function testTheCrossContextLookupAnswersForAWholePageInOneCall(): void
    {
        $images = new DbalMediaImages($this->connection);
        $other = SlotKey::of(ImageKind::Event, self::OTHER);
        $this->in(function () use ($other): void {
            $this->slots->save($this->slots->lock($this->key(), $this->now)->replace($this->file(self::FIRST, $this->key()), 0, $this->now));
            $this->slots->save($this->slots->lock($other, $this->now)->replace($this->file(self::SECOND, $other), 0, $this->now));
            $this->slots->save($this->slots->lock($other, $this->now)->clear(1, $this->now));
        });

        self::assertSame([self::OWNER => self::FIRST], $images->fileIds('event', [self::OWNER, self::OTHER, 'e0000000-0000-4000-8000-000000000003']));
        self::assertSame([], $images->fileIds('event', []));
        self::assertSame([], $images->fileIds('event_banner', [self::OWNER]));
    }

    public function testTheDeletionQueueDropsAReplacedFileButKeepsOneASlotStillHolds(): void
    {
        $key = $this->key();
        $queue = new DbalPendingDeletions($this->connection);
        $first = $this->file(self::FIRST, $key);
        $this->in(function () use ($key, $first): void {
            $this->slots->save($this->slots->lock($key, $this->now)->replace($first, 0, $this->now));
        });
        $this->in(function () use ($key, $queue, $first): void {
            $slot = $this->slots->lock($key, $this->now);
            $this->slots->save($slot->replace($this->file(self::SECOND, $key), 1, $this->now));
            $queue->queue($first, $this->now);
            $queue->queue($first, $this->now);
        });

        $pending = $queue->next(10);
        self::assertSame([self::FIRST], array_map(static fn ($file): string => $file->fileId, $pending));
        self::assertSame($first->path, $pending[0]->path);

        $queue->finish(self::FIRST);
        self::assertSame([], $queue->next(10));
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM media_files WHERE id = ?', [self::FIRST]));
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM media_files WHERE id = ?', [self::SECOND]));

        $queue->queue($this->file(self::SECOND, $key), $this->now);
        $queue->finish(self::SECOND);
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM media_files WHERE id = ?', [self::SECOND]), 'a file a slot still uses is never forgotten');
    }

    public function testTheCatalogKnowsStoredPathsOnly(): void
    {
        $key = $this->key();
        $file = $this->file(self::FIRST, $key);
        $this->in(fn () => $this->slots->save($this->slots->lock($key, $this->now)->replace($file, 0, $this->now)));
        $catalog = new DbalMediaFileCatalog($this->connection);

        self::assertTrue($catalog->isKnown($file->path));
        self::assertFalse($catalog->isKnown('event/00/00/00000000-0000-4000-8000-000000000009.webp'));
    }

    public function testAuditRowsCarryMetadataOnly(): void
    {
        new DbalMediaAuditWriter($this->connection)->record($this->key(), 'IMAGE_CHANGED', 'event set, 100 bytes, 1280x720', self::ACTOR, $this->now);

        $row = $this->connection->fetchAssociative('SELECT owner_type, target_id, kind, action, reason, actor_id FROM media_audit');
        self::assertSame(['owner_type' => 'event', 'target_id' => self::OWNER, 'kind' => 'event', 'action' => 'IMAGE_CHANGED', 'reason' => 'event set, 100 bytes, 1280x720', 'actor_id' => self::ACTOR], $row);
    }

    public function testTheAdvisoryLockNeedsATransaction(): void
    {
        $this->expectException(LogicException::class);
        $this->slots->lock($this->key(), $this->now);
    }

    private function key(): SlotKey
    {
        return SlotKey::of(ImageKind::Event, self::OWNER);
    }

    private function file(string $id, SlotKey $key): MediaFile
    {
        return MediaFile::create($key, $id, 1280, 720, 1000, hash('sha256', $id), $this->now);
    }

    /** The slot as a command would see it: loaded under its lock, inside a transaction. */
    private function locked(SlotKey $key): ImageSlot
    {
        $slot = null;
        $this->in(function () use ($key, &$slot): void {
            $slot = $this->slots->lock($key, $this->now);
        });
        \assert($slot instanceof ImageSlot);

        return $slot;
    }

    /** @param Closure(): void $work */
    private function in(Closure $work): void
    {
        $this->connection->transactional(static function () use ($work): void {
            $work();
        });
    }
}
