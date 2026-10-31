<?php

declare(strict_types=1);

namespace App\Media\Infrastructure;

use App\Media\Application\ImageUpload;
use App\Media\Application\Port\ImageProcessor;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ImageProcessor::class)]
final class RasterImageProcessor implements ImageProcessor
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function process(ImageUpload $file): array
    {
        if (!$file->valid || $file->size > self::MAX_BYTES || 0 === $file->size) {
            throw new InvalidArgumentException('Choose a JPEG, PNG or WebP image of at most 5 MB.');
        }
        $bytes = file_get_contents($file->path);
        $size = @getimagesizefromstring($bytes);
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Only JPEG, PNG and WebP images are supported.');
        }
        [$width, $height] = $size;
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || $width * $height > 16000000) {
            throw new InvalidArgumentException('Images must contain at most 16 million pixels and be at most 12,000 pixels on either side.');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            throw new InvalidArgumentException('This image could not be decoded. Try exporting it again.');
        }
        // Apply camera orientation before discarding all metadata.
        if (IMAGETYPE_JPEG === $size[2] && function_exists('exif_read_data')) {
            $metadata = @exif_read_data($file->path);
            $orientation = (int) ($metadata['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {
                3, 4 => 180,
                5, 6 => -90,
                7, 8 => 90,
                default => 0,
            };
            if (0 !== $angle) {
                $rotated = imagerotate($source, $angle, 0);
                imagedestroy($source);
                $source = $rotated;
            }
            $width = imagesx($source);
            $height = imagesy($source);
        }
        $scale = min(1, 1920 / max($width, $height));
        $outputWidth = max(1, (int) round($width * $scale));
        $outputHeight = max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($outputWidth, $outputHeight);
        try {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
            imagecopyresampled($output, $source, 0, 0, 0, 0, $outputWidth, $outputHeight, $width, $height);
            ob_start();
            try {
                if (!imagepng($output, null, 6)) {
                    throw new InvalidArgumentException('Image encoding failed.');
                }
                $clean = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (strlen($clean) > self::MAX_BYTES) {
                throw new InvalidArgumentException('Processed image exceeds 5 MB. Choose a smaller image.');
            }

            return ['mime' => 'image/png', 'width' => $outputWidth, 'height' => $outputHeight, 'content' => $clean];
        } finally {
            imagedestroy($source);
            imagedestroy($output);
        }
    }
}
