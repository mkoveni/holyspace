<?php

declare(strict_types=1);

namespace App\Media\Domain;

use DomainException;

/**
 * How one kind of picture is stored: the exact size it is cropped to and the smaller widths kept beside it. The limits
 * on what may be uploaded are the same for every kind.
 */
final readonly class ImagePolicy
{
    public const int MAX_SOURCE_BYTES = 5 * 1024 * 1024;
    public const int MAX_SOURCE_SIDE = 8000;
    /** Decoded size, not file size: a small file can expand far past the PHP memory limit. */
    public const int MAX_SOURCE_PIXELS = 25_000_000;

    /** @param list<int> $variantWidths ascending widths below the stored width; each keeps the aspect ratio */
    public function __construct(public int $width, public int $height, public array $variantWidths)
    {
        if ($width < 1 || $height < 1) {
            throw new DomainException('An image size must be positive.');
        }
        $previous = 0;
        foreach ($variantWidths as $variant) {
            if ($variant <= $previous || $variant >= $width) {
                throw new DomainException('Variant widths must ascend and stay below the stored width.');
            }
            $previous = $variant;
        }
    }

    public function hasVariant(int $width): bool
    {
        return in_array($width, $this->variantWidths, true);
    }
}
