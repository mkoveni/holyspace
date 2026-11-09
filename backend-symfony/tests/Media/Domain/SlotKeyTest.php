<?php

declare(strict_types=1);

namespace App\Tests\Media\Domain;

use App\Media\Domain\ImageKind;
use App\Media\Domain\MediaException;
use App\Media\Domain\OwnerType;
use App\Media\Domain\SlotKey;
use PHPUnit\Framework\TestCase;

final class SlotKeyTest extends TestCase
{
    public function testTheOwnerTypeFollowsTheKindAndTheIdIsNormalised(): void
    {
        $key = SlotKey::of(ImageKind::FamilyBanner, 'ABCDEF01-2345-4678-89AB-CDEF01234567');

        self::assertSame(OwnerType::Household, $key->ownerType);
        self::assertSame('abcdef01-2345-4678-89ab-cdef01234567', $key->ownerId);
        self::assertTrue($key->equals(SlotKey::of(ImageKind::FamilyBanner, 'abcdef01-2345-4678-89ab-cdef01234567')));
        self::assertFalse($key->equals(SlotKey::of(ImageKind::Family, 'abcdef01-2345-4678-89ab-cdef01234567')));
    }

    public function testRejectsAnOwnerThatIsNotAUuid(): void
    {
        $this->expectException(MediaException::class);
        SlotKey::of(ImageKind::Event, '../etc');
    }
}
