<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\ImageProcessor;
use App\Media\Application\Port\ProcessedImage;
use App\Media\Domain\ImagePolicy;
use App\Media\Domain\MediaException;

/** Accepts anything except the bytes `not-an-image`, and "crops" to the policy's size. */
final class FakeImageProcessor implements ImageProcessor
{
    public int $calls = 0;

    public function process(string $bytes, ImagePolicy $policy): ProcessedImage
    {
        ++$this->calls;
        if ('not-an-image' === $bytes) {
            throw MediaException::invalid('Choose a JPEG, PNG or WebP image.');
        }
        $variants = [];
        foreach ($policy->variantWidths as $width) {
            $variants[$width] = 'variant-'.$width.':'.$bytes;
        }

        return new ProcessedImage('webp:'.$bytes, $policy->width, $policy->height, $variants);
    }
}
