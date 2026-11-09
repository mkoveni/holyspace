<?php

declare(strict_types=1);

namespace App\Media\Infrastructure;

use App\Media\Application\Port\ImageProcessor;
use App\Media\Application\Port\ProcessedImage;
use App\Media\Domain\ImagePolicy;
use App\Media\Domain\MediaException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

/**
 * Judges an upload by its bytes, then re-encodes it: cropped to the kind's exact size, rotated upright from its EXIF
 * orientation, and written as a fresh WebP, so no camera metadata, GPS position or hidden payload survives.
 * The size checks run before decoding, because decoding is what a hostile file attacks.
 */
final readonly class InterventionImageProcessor implements ImageProcessor
{
    private const int QUALITY = 82;
    private const array TYPES = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP];

    private ImageManagerInterface $images;

    public function __construct()
    {
        $this->images = ImageManager::usingDriver(Driver::class);
    }

    public function process(string $bytes, ImagePolicy $policy): ProcessedImage
    {
        $this->check($bytes);

        try {
            $main = $this->images->decodeBinary($bytes)->cover($policy->width, $policy->height)->encode(new WebpEncoder(self::QUALITY, true))->toString();
            $variants = [];
            foreach ($policy->variantWidths as $width) {
                $variants[$width] = $this->images->decodeBinary($main)->scale($width)->encode(new WebpEncoder(self::QUALITY, true))->toString();
            }
        } catch (Throwable) {
            throw MediaException::invalid('This image could not be read.');
        }

        return new ProcessedImage($main, $policy->width, $policy->height, $variants);
    }

    private function check(string $bytes): void
    {
        if ('' === $bytes) {
            throw MediaException::invalid('Choose an image file.');
        }
        if (\strlen($bytes) > ImagePolicy::MAX_SOURCE_BYTES) {
            throw MediaException::invalid('Choose an image of at most 5 MB.');
        }
        $info = @getimagesizefromstring($bytes);
        if (false === $info || !in_array($info[2], self::TYPES, true) || $info[0] < 1 || $info[1] < 1) {
            throw MediaException::invalid('Choose a JPEG, PNG or WebP image.');
        }
        if ($info[0] > ImagePolicy::MAX_SOURCE_SIDE || $info[1] > ImagePolicy::MAX_SOURCE_SIDE) {
            throw MediaException::invalid('Choose an image of at most 8000 pixels on each side.');
        }
        if ($info[0] * $info[1] > ImagePolicy::MAX_SOURCE_PIXELS) {
            throw MediaException::invalid('Choose an image of at most 25 million pixels.');
        }
    }
}
