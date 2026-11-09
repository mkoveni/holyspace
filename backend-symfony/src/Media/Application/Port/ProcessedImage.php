<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

/** The result of processing an upload: the stored picture and its smaller copies, all WebP. */
final readonly class ProcessedImage
{
    /** @param array<int, string> $variants bytes of each smaller copy, keyed by its width */
    public function __construct(public string $bytes, public int $width, public int $height, public array $variants)
    {
    }

    /** @return array<string, mixed> never the bytes */
    public function __debugInfo(): array
    {
        return ['width' => $this->width, 'height' => $this->height, 'bytes' => \strlen($this->bytes), 'variants' => array_keys($this->variants)];
    }
}
