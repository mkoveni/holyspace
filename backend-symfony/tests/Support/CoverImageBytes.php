<?php

declare(strict_types=1);

namespace App\Tests\Support;

/** Minimal image bytes that PHP's image readers accept, for cover-image tests. */
final class CoverImageBytes
{
    private function __construct()
    {
    }

    /** A PNG header that image readers accept, with the given size. */
    public static function png(int $width = 1200, int $height = 300): string
    {
        $ihdr = 'IHDR'.pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1A\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    public static function jpeg(): string
    {
        return "\xFF\xD8\xFF\xC0\x00\x11\x08\x00\x10\x00\x20\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xD9";
    }
}
