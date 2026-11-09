<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Query\GetImage\GetImageHandler;
use App\Media\Application\Query\GetImage\GetImageQuery;
use App\Media\Application\Query\GetImageContent\GetImageContentHandler;
use App\Media\Application\Query\GetImageContent\GetImageContentQuery;
use App\Media\Application\Query\GetPublicImage\GetPublicImageHandler;
use App\Media\Application\Query\GetPublicImage\GetPublicImageQuery;
use App\Media\Domain\ImageKind;
use App\Shared\Domain\ErrorKind;

/** Reading pictures: staff through the owner's permissions, anyone by random id only for public kinds. */
final class ImageQueriesTest extends MediaTestCase
{
    private const string FILE = 'f0000000-0000-4000-8000-0000000000aa';

    public function testStaffSeeTheVersionAndPictureOfAnUnusedAndAUsedSlot(): void
    {
        $get = new GetImageHandler($this->access, $this->slots);
        $empty = $get->handle(new GetImageQuery(self::ACTOR, ImageKind::Event, self::OWNER));
        self::assertSame(0, $empty->version);
        self::assertNull($empty->image);

        $this->seedFile(self::FILE, 4);
        $used = $get->handle(new GetImageQuery(self::ACTOR, ImageKind::Event, self::OWNER));
        self::assertSame(4, $used->version);
        self::assertSame(self::FILE, $used->image?->fileId);
    }

    public function testViewingNeedsViewOrManageAccess(): void
    {
        $get = new GetImageHandler($this->access, $this->slots);
        $this->owner->view = false;
        self::assertSame(0, $get->handle(new GetImageQuery(self::ACTOR, ImageKind::Event, self::OWNER))->version, 'manage implies view');

        $this->owner->manage = false;
        $this->assertFailsWith(ErrorKind::Forbidden, fn () => $get->handle(new GetImageQuery(self::ACTOR, ImageKind::Event, self::OWNER)));
    }

    public function testContentIsServedWithItsVariantsAndRefusesUnknownOnes(): void
    {
        $this->seedFile(self::FILE, 1);
        $content = new GetImageContentHandler($this->access, $this->slots, $this->files);

        self::assertSame('old-main', $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER))->bytes);
        self::assertSame('old-variant', $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER, 640))->bytes);
        self::assertSame('image/webp', $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER))->mime);
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER, 999)));
    }

    public function testContentOfAnEmptySlotIsNotFoundAndWithoutAccessForbidden(): void
    {
        $content = new GetImageContentHandler($this->access, $this->slots, $this->files);
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER)));

        $this->owner->view = false;
        $this->owner->manage = false;
        $this->assertFailsWith(ErrorKind::Forbidden, fn () => $content->handle(new GetImageContentQuery(self::ACTOR, ImageKind::Event, self::OWNER)));
    }

    public function testAPublicKindIsServedByFileIdWithoutAnyOwnerCheck(): void
    {
        $this->seedFile(self::FILE, 1);
        $public = new GetPublicImageHandler($this->slots, $this->files);

        self::assertSame('old-main', $public->handle(new GetPublicImageQuery(self::FILE))->bytes);
        self::assertSame('old-variant', $public->handle(new GetPublicImageQuery(self::FILE, 640))->bytes);
        self::assertSame([], $this->owner->calls);
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $public->handle(new GetPublicImageQuery(self::FILE, 64)));
    }

    public function testPersonalKindsAreNeverServedPublicly(): void
    {
        $public = new GetPublicImageHandler($this->slots, $this->files);
        foreach ([ImageKind::Profile, ImageKind::Person, ImageKind::Family, ImageKind::FamilyBanner] as $kind) {
            $this->seedFile(self::FILE, 1, $kind);
            $this->assertFailsWith(ErrorKind::NotFound, fn () => $public->handle(new GetPublicImageQuery(self::FILE)));
        }
    }

    public function testUnknownMalformedAndGoneFilesAreNotFound(): void
    {
        $public = new GetPublicImageHandler($this->slots, $this->files);
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $public->handle(new GetPublicImageQuery(self::FILE)));
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $public->handle(new GetPublicImageQuery('../../etc/passwd')));

        $file = $this->seedFile(self::FILE, 1);
        unset($this->files->files[$file->path]);
        $this->assertFailsWith(ErrorKind::NotFound, fn () => $public->handle(new GetPublicImageQuery(self::FILE)));
    }
}
