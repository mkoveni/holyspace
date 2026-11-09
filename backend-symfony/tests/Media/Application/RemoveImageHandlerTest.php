<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Command\RemoveImage\RemoveImageCommand;
use App\Media\Application\Command\RemoveImage\RemoveImageHandler;
use App\Media\Domain\ImageKind;
use App\Shared\Domain\ErrorKind;

final class RemoveImageHandlerTest extends MediaTestCase
{
    private const string OLD_FILE = 'f0000000-0000-4000-8000-0000000000aa';

    public function testRemovingClearsTheSlotKeepsItsVersionAndQueuesTheFile(): void
    {
        $old = $this->seedFile(self::OLD_FILE, 2);

        $this->remove(2);

        $slot = $this->slots->current($this->key());
        self::assertNotNull($slot);
        self::assertNull($slot->file);
        self::assertSame(3, $slot->version(), 'a tombstone: a stale request cannot bring the old picture back');
        self::assertSame([$old], $this->deletions->queued);
        self::assertSame('IMAGE_REMOVED', $this->audit->rows[0]['action']);
    }

    public function testAStaleRemovalChangesNothing(): void
    {
        $this->seedFile(self::OLD_FILE, 2);

        $this->assertFailsWith(ErrorKind::Conflict, fn () => $this->remove(1));

        self::assertSame(2, $this->slots->current($this->key())?->version());
        self::assertSame([], $this->deletions->queued);
        self::assertSame([], $this->audit->rows);
    }

    public function testWithoutPermissionNothingIsLocked(): void
    {
        $this->owner->manage = false;

        $this->assertFailsWith(ErrorKind::Forbidden, fn () => $this->remove(0));

        self::assertSame(0, $this->slots->locks);
        $this->assertNothingHappened();
    }

    public function testRemovingAnEmptySlotQueuesNothing(): void
    {
        $this->remove(0);

        self::assertSame(1, $this->slots->current($this->key())?->version());
        self::assertSame([], $this->deletions->queued);
    }

    private function remove(int $version): void
    {
        (new RemoveImageHandler($this->access, $this->slots, $this->deletions, $this->audit, $this->clock))
            ->handle(new RemoveImageCommand(self::ACTOR, ImageKind::Event, self::OWNER, $version));
    }
}
