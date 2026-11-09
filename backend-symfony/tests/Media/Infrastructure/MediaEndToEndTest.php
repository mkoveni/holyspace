<?php

declare(strict_types=1);

namespace App\Tests\Media\Infrastructure;

use App\Media\Application\Command\ChangeImage\ChangeImageCommand;
use App\Media\Application\Command\ChangeImage\ChangeImageHandler;
use App\Media\Application\Command\RemoveImage\RemoveImageCommand;
use App\Media\Application\Command\RemoveImage\RemoveImageHandler;
use App\Media\Application\DeletionDrain;
use App\Media\Application\ImageAccess;
use App\Media\Application\ImageOwners;
use App\Media\Application\OrphanSweep;
use App\Media\Application\Query\GetPublicImage\GetPublicImageHandler;
use App\Media\Application\Query\GetPublicImage\GetPublicImageQuery;
use App\Media\Domain\ImageKind;
use App\Media\Infrastructure\InterventionImageProcessor;
use App\Media\Infrastructure\LocalFileStore;
use App\Media\Infrastructure\Persistence\DbalImageSlotStore;
use App\Media\Infrastructure\Persistence\DbalMediaAuditWriter;
use App\Media\Infrastructure\Persistence\DbalMediaFileCatalog;
use App\Media\Infrastructure\Persistence\DbalPendingDeletions;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use App\Tests\Media\Application\FakeOwner;
use App\Tests\Support\DatabaseTestCase;
use Closure;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/** Every real adapter together: upload, replace, remove, a rolled-back upload and the cleanup that follows. */
final class MediaEndToEndTest extends DatabaseTestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string OWNER = 'e0000000-0000-4000-8000-000000000001';

    private string $root;
    private LocalFileStore $files;
    private MockClock $clock;
    private ChangeImageHandler $change;
    private RemoveImageHandler $remove;
    private DbalPendingDeletions $pending;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/media-e2e-'.bin2hex(random_bytes(6));
        $this->files = new LocalFileStore($this->root, '/unused');
        $this->clock = new MockClock('2026-11-09 10:00:00');
        $slots = new DbalImageSlotStore($this->connection, new AdvisoryLock($this->connection));
        $this->pending = new DbalPendingDeletions($this->connection);
        $audit = new DbalMediaAuditWriter($this->connection);
        $access = new ImageAccess(new ImageOwners([new FakeOwner('event')]));
        $this->change = new ChangeImageHandler($access, $slots, new InterventionImageProcessor(), $this->files, $this->pending, $audit, new SymfonyIdGenerator(), $this->clock);
        $this->remove = new RemoveImageHandler($access, $slots, $this->pending, $audit, $this->clock);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    public function testUploadReplaceAndRemoveLeaveExactlyTheFilesTheDatabaseRecords(): void
    {
        $this->upload(0);
        $first = $this->currentFile();
        self::assertCount(2, $this->onDisk(), 'the picture and its smaller copy');
        self::assertSame(1, $this->version());

        $this->upload(1);
        $second = $this->currentFile();
        self::assertNotSame($first, $second);
        self::assertCount(4, $this->onDisk(), 'the replaced picture waits for the scheduled task');

        self::assertSame(1, new DeletionDrain($this->pending, $this->files)->run());
        self::assertSame(2, count($this->onDisk()));
        self::assertSame([$second, $second], array_map(static fn (string $path): string => substr(basename($path), 0, 36), $this->onDisk()));

        $this->in(fn () => $this->remove->handle(new RemoveImageCommand(self::ACTOR, ImageKind::Event, self::OWNER, 2)));
        new DeletionDrain($this->pending, $this->files)->run();

        self::assertSame([], $this->onDisk());
        self::assertSame(3, $this->version());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM media_files'));
        $actions = $this->connection->fetchFirstColumn('SELECT action FROM media_audit');
        sort($actions);
        self::assertSame(['IMAGE_CHANGED', 'IMAGE_CHANGED', 'IMAGE_REMOVED'], $actions);
    }

    public function testAPublicPictureIsServedThroughTheRealStoreAsWebp(): void
    {
        $this->upload(0);
        $id = $this->currentFile();
        $public = new GetPublicImageHandler(new DbalImageSlotStore($this->connection, new AdvisoryLock($this->connection)), $this->files);

        $main = $public->handle(new GetPublicImageQuery($id));
        $small = $public->handle(new GetPublicImageQuery($id, 640));

        self::assertSame('image/webp', $main->mime);
        self::assertSame([1280, 720], array_slice(getimagesizefromstring($main->bytes) ?: [], 0, 2));
        self::assertSame([640, 360], array_slice(getimagesizefromstring($small->bytes) ?: [], 0, 2));
    }

    public function testAStaleUploadWritesNothingToDisk(): void
    {
        $this->upload(0);
        $before = $this->onDisk();

        try {
            $this->upload(0);
            self::fail('Expected a stale version.');
        } catch (ContextException $e) {
            self::assertSame(ErrorKind::Conflict, $e->kind());
        }

        self::assertSame($before, $this->onDisk());
    }

    public function testARolledBackUploadLeavesTheDatabaseUntouchedAndTheNightlySweepRemovesItsFiles(): void
    {
        $this->upload(0);
        $kept = $this->onDisk();

        try {
            $this->in(function (): void {
                $this->change->handle(new ChangeImageCommand(self::ACTOR, ImageKind::Event, self::OWNER, ImageFixtures::png(), 1));
                throw new RuntimeException('the transaction fails after the files were written');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(1, $this->version(), 'the slot was not advanced');
        self::assertSame([], $this->pending->next(10), 'nothing was queued for deletion, so the live picture survives');
        self::assertCount(4, $this->onDisk(), 'the new files are orphans');
        self::assertSame($kept, array_values(array_intersect($this->onDisk(), $kept)));

        $later = new MockClock('2026-11-12 10:00:00');
        $result = new OrphanSweep($this->files, new DbalMediaFileCatalog($this->connection), $later)->run();

        self::assertSame(2, $result['orphans']);
        self::assertSame($kept, $this->onDisk());
    }

    private function upload(int $version): void
    {
        $this->in(fn () => $this->change->handle(new ChangeImageCommand(self::ACTOR, ImageKind::Event, self::OWNER, ImageFixtures::png(), $version)));
    }

    /** @param Closure(): void $work */
    private function in(Closure $work): void
    {
        $this->connection->transactional(static function () use ($work): void {
            $work();
        });
    }

    private function version(): int
    {
        return (int) $this->connection->fetchOne('SELECT version FROM media_slots');
    }

    private function currentFile(): string
    {
        return (string) $this->connection->fetchOne('SELECT file_id FROM media_slots');
    }

    /** @return list<string> */
    private function onDisk(): array
    {
        $paths = array_keys(iterator_to_array($this->files->all()));
        sort($paths);

        return $paths;
    }
}
