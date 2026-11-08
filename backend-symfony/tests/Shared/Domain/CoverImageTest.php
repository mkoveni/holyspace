<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\CoverImage;
use App\Shared\Domain\InvalidInputException;
use App\Tests\Support\CoverImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoverImageTest extends TestCase
{
    public function testPngJpegAndWebpAreReadFromTheirBytes(): void
    {
        $png = CoverImage::fromBytes(CoverImageBytes::png(1200, 300));
        self::assertSame(['image/png', 1200, 300], [$png->contentType, $png->width, $png->height]);
        $jpeg = CoverImage::fromBytes(CoverImageBytes::jpeg());
        self::assertSame(['image/jpeg', 32, 16], [$jpeg->contentType, $jpeg->width, $jpeg->height]);
        $webp = CoverImage::fromBytes('RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10)."\x00\x00\x00\x00".substr(pack('V', 31), 0, 3).substr(pack('V', 15), 0, 3));
        self::assertSame(['image/webp', 32, 16], [$webp->contentType, $webp->width, $webp->height]);
    }

    public function testDebugOutputNeverContainsTheBytes(): void
    {
        self::assertStringNotContainsString('IHDR', print_r(CoverImage::fromBytes(CoverImageBytes::png()), true));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rejected(): iterable
    {
        yield 'empty' => ['', 'Choose an image file.'];
        yield 'over 5 MiB' => [CoverImageBytes::png().str_repeat("\0", CoverImage::MAX_BYTES), 'Choose an image of at most 5 MB.'];
        yield 'gif' => ["GIF89a\x01\x00\x01\x00\x00\x00\x00", 'Choose a JPEG, PNG or WebP image.'];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'Choose a JPEG, PNG or WebP image.'];
        yield 'html named png' => ['<html><script>alert(1)</script></html>', 'Choose a JPEG, PNG or WebP image.'];
        yield 'truncated png' => ["\x89PNG\r\n\x1A\n", 'This image could not be read.'];
        yield 'too wide' => [CoverImageBytes::png(8001, 10), 'Choose an image of at most 8000 pixels on each side.'];
        yield 'zero height' => [CoverImageBytes::png(10, 0), 'This image could not be read.'];
    }

    #[DataProvider('rejected')]
    public function testAnythingButASensibleRasterImageIsA400(string $bytes, string $message): void
    {
        try {
            CoverImage::fromBytes($bytes);
            self::fail('accepted');
        } catch (InvalidInputException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }
}
