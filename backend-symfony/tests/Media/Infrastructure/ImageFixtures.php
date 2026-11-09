<?php

declare(strict_types=1);

namespace App\Tests\Media\Infrastructure;

use GdImage;

/** Real image bytes for processor tests, made with GD so the tests need no files on disk. */
final class ImageFixtures
{
    private function __construct()
    {
    }

    /** A picture whose left half is red and right half blue. */
    public static function png(int $width = 60, int $height = 30): string
    {
        return self::encode(self::redBlue($width, $height), 'imagepng');
    }

    public static function jpeg(int $width = 60, int $height = 30): string
    {
        return self::encode(self::redBlue($width, $height), 'imagejpeg');
    }

    public static function webp(int $width = 60, int $height = 30): string
    {
        return self::encode(self::redBlue($width, $height), 'imagewebp');
    }

    public static function gif(): string
    {
        return self::encode(self::redBlue(10, 10), 'imagegif');
    }

    /** A palette picture: tiny as a file, enormous once decoded. */
    public static function hugePng(int $width, int $height): string
    {
        $image = imagecreate(max(1, $width), max(1, $height));
        \assert(false !== $image);
        imagecolorallocate($image, 255, 255, 255);

        return self::encode($image, 'imagepng');
    }

    /** A JPEG carrying an EXIF orientation and a comment a leaking encoder would keep. */
    public static function jpegWithMetadata(int $orientation, string $comment): string
    {
        $jpeg = self::jpeg();
        $tiff = "II\x2A\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $segments = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif."\xFF\xFE".pack('n', \strlen($comment) + 2).$comment;

        return substr($jpeg, 0, 2).$segments.substr($jpeg, 2);
    }

    /** @return array{int, int, int} red, green, blue of the pixel at x, y in decoded image bytes */
    public static function pixel(string $bytes, int $x, int $y): array
    {
        $image = imagecreatefromstring($bytes);
        \assert(false !== $image);
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 255, ($color >> 8) & 255, $color & 255];
    }

    private static function redBlue(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        \assert(false !== $image);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));

        return $image;
    }

    /** @param callable(GdImage): mixed $encoder */
    private static function encode(GdImage $image, callable $encoder): string
    {
        ob_start();
        $encoder($image);

        return (string) ob_get_clean();
    }
}
