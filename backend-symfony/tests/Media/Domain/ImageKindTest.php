<?php

declare(strict_types=1);

namespace App\Tests\Media\Domain;

use App\Media\Domain\ImageKind;
use App\Media\Domain\OwnerType;
use PHPUnit\Framework\TestCase;

final class ImageKindTest extends TestCase
{
    public function testEachKindHasItsOwnerTypeAndSize(): void
    {
        $expected = [
            'profile' => [OwnerType::Account, 250, 250],
            'person' => [OwnerType::Person, 250, 250],
            'family' => [OwnerType::Household, 1200, 800],
            'family_banner' => [OwnerType::Household, 1200, 300],
            'event' => [OwnerType::Event, 1280, 720],
            'event_banner' => [OwnerType::Event, 1280, 720],
            'community' => [OwnerType::Community, 1280, 720],
        ];
        foreach (ImageKind::cases() as $kind) {
            self::assertSame($expected[$kind->value], [$kind->ownerType(), $kind->policy()->width, $kind->policy()->height], $kind->value);
        }
    }

    public function testOnlyEventAndCommunityPicturesArePublic(): void
    {
        $public = array_map(static fn (ImageKind $kind): string => $kind->value, array_filter(ImageKind::cases(), static fn (ImageKind $kind): bool => $kind->isPublic()));

        self::assertEqualsCanonicalizing(['event', 'event_banner', 'community'], array_values($public));
    }

    public function testVariantsAreSmallerThanTheStoredPicture(): void
    {
        foreach (ImageKind::cases() as $kind) {
            self::assertNotSame([], $kind->policy()->variantWidths, $kind->value);
            self::assertTrue($kind->policy()->hasVariant($kind->policy()->variantWidths[0]));
            self::assertFalse($kind->policy()->hasVariant($kind->policy()->width));
        }
    }
}
