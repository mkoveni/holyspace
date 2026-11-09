<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Command\ChangeImage\ChangeImageCommand;
use App\Media\Application\Command\ChangeImage\ChangeImageHandler;
use App\Media\Application\ImageAccess;
use App\Media\Application\ImageOwners;
use App\Media\Domain\ImageKind;
use App\Media\Domain\ImagePolicy;
use App\Shared\Domain\ErrorKind;

/**
 * Changing a picture in isolation: the owner decides access first, a stale version is refused before any work is done,
 * and nothing is saved, queued or audited unless every step succeeds.
 */
final class ChangeImageHandlerTest extends MediaTestCase
{
    private const string FIRST_FILE = '00000000-0000-4000-8000-000000000001';
    private const string OLD_FILE = 'f0000000-0000-4000-8000-0000000000aa';

    public function testFirstUploadStoresTheFileAndItsVariantsAndAdvancesTheSlot(): void
    {
        $this->change('photo', 0);

        $path = 'event/00/00/'.self::FIRST_FILE.'.webp';
        self::assertSame('webp:photo', $this->files->files[$path]);
        self::assertSame('variant-640:photo', $this->files->files['event/00/00/'.self::FIRST_FILE.'@640.webp']);
        $slot = $this->slots->current($this->key());
        self::assertSame(1, $slot?->version());
        self::assertSame(self::FIRST_FILE, $slot->file?->id);
        self::assertSame(hash('sha256', 'webp:photo'), $slot->file->sha256);
        self::assertSame([], $this->deletions->queued);
        self::assertCount(1, $this->audit->rows);
        self::assertSame('IMAGE_CHANGED', $this->audit->rows[0]['action']);
        self::assertSame(self::ACTOR, $this->audit->rows[0]['actor']);
    }

    public function testReplacingQueuesTheOldFileForDeletionAndLeavesItOnDisk(): void
    {
        $old = $this->seedFile(self::OLD_FILE, 3);

        $this->change('photo', 3);

        self::assertSame(4, $this->slots->current($this->key())?->version());
        self::assertSame([$old], $this->deletions->queued);
        self::assertArrayHasKey($old->path, $this->files->files, 'deleted only after commit, by the scheduled task');
    }

    public function testAuditKeepsMetadataOnly(): void
    {
        $this->change('secret-bytes', 0);

        self::assertStringNotContainsString('secret-bytes', $this->audit->rows[0]['detail']);
        self::assertStringContainsString('1280x720', $this->audit->rows[0]['detail']);
    }

    public function testWithoutPermissionNothingIsLockedProcessedOrWritten(): void
    {
        $this->owner->manage = false;

        $this->assertFailsWith(ErrorKind::Forbidden, fn () => $this->change('photo', 0));

        self::assertSame(['canManage'], $this->owner->calls, 'permission is asked first and existence is not revealed');
        self::assertSame(0, $this->slots->locks);
        self::assertSame(0, $this->processor->calls);
        $this->assertNothingHappened();
    }

    public function testAMissingOwnerIsNotFound(): void
    {
        $this->owner->exists = false;

        $this->assertFailsWith(ErrorKind::NotFound, fn () => $this->change('photo', 0));

        $this->assertNothingHappened();
    }

    public function testAnOwnerTypeNobodyRegisteredIsNotFound(): void
    {
        $handler = $this->handler(new ImageAccess(new ImageOwners([])));

        $this->assertFailsWith(ErrorKind::NotFound, fn () => $handler->handle(new ChangeImageCommand(self::ACTOR, ImageKind::Person, self::OWNER, 'photo', 0)));

        $this->assertNothingHappened();
    }

    public function testAStaleVersionIsRefusedBeforeTheUploadIsProcessed(): void
    {
        $this->seedFile(self::OLD_FILE, 3);
        $before = $this->files->files;

        $this->assertFailsWith(ErrorKind::Conflict, fn () => $this->change('photo', 2));

        self::assertSame(0, $this->processor->calls);
        self::assertSame($before, $this->files->files);
        self::assertSame(0, $this->slots->saves);
        self::assertSame([], $this->audit->rows);
        self::assertSame([], $this->deletions->queued);
    }

    public function testANeverUsedSlotRefusesAnyVersionButZero(): void
    {
        $this->assertFailsWith(ErrorKind::Conflict, fn () => $this->change('photo', 1));

        $this->assertNothingHappened();
    }

    public function testAnUnreadableUploadChangesNothing(): void
    {
        $this->assertFailsWith(ErrorKind::Invalid, fn () => $this->change('not-an-image', 0));

        $this->assertNothingHappened();
    }

    public function testEmptyAndOversizedUploadsAreRefusedBeforeLockingTheSlot(): void
    {
        $this->assertFailsWith(ErrorKind::Invalid, fn () => $this->change('', 0));
        $this->assertFailsWith(ErrorKind::Invalid, fn () => $this->change(str_repeat('x', ImagePolicy::MAX_SOURCE_BYTES + 1), 0));

        self::assertSame(0, $this->slots->locks);
        self::assertSame(0, $this->processor->calls);
        $this->assertNothingHappened();
    }

    public function testAFailedWriteLeavesTheSlotAndAuditUntouched(): void
    {
        $this->files->failWrites = true;

        $this->assertFailsWith(ErrorKind::Unavailable, fn () => $this->change('photo', 0));

        $this->assertNothingHappened();
    }

    public function testEveryKindIsStoredAtItsOwnSize(): void
    {
        $handler = $this->handler(new ImageAccess(new ImageOwners([$this->owner, new FakeOwner('person'), new FakeOwner('household'), new FakeOwner('account'), new FakeOwner('community')])));
        foreach (ImageKind::cases() as $kind) {
            $handler->handle(new ChangeImageCommand(self::ACTOR, $kind, self::OWNER, 'photo', 0));
            $file = $this->slots->current($this->key($kind))?->file;
            self::assertNotNull($file, $kind->value);
            self::assertSame([$kind->policy()->width, $kind->policy()->height], [$file->width, $file->height], $kind->value);
        }
    }

    private function change(string $bytes, int $version): void
    {
        $this->handler($this->access)->handle(new ChangeImageCommand(self::ACTOR, ImageKind::Event, self::OWNER, $bytes, $version));
    }

    private function handler(ImageAccess $access): ChangeImageHandler
    {
        return new ChangeImageHandler($access, $this->slots, $this->processor, $this->files, $this->deletions, $this->audit, $this->ids, $this->clock);
    }
}
