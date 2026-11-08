<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * An uploaded cover picture for an event or community, checked from its bytes rather than its declared type or name:
 * a JPEG, PNG or WebP of at most 5 MiB and 8000 pixels a side. Anything else is a 400.
 */
final readonly class CoverImage
{
    public const int MAX_BYTES = 5 * 1024 * 1024;
    public const int MAX_SIDE = 8000;

    private const array SIGNATURES = [
        'image/jpeg' => \IMAGETYPE_JPEG,
        'image/png' => \IMAGETYPE_PNG,
        'image/webp' => \IMAGETYPE_WEBP,
    ];

    private function __construct(public string $contentType, public int $width, public int $height, public string $content)
    {
    }

    public static function fromBytes(string $content): self
    {
        $size = \strlen($content);
        if (0 === $size) {
            throw InvalidInputException::because('Choose an image file.');
        }
        if ($size > self::MAX_BYTES) {
            throw InvalidInputException::because('Choose an image of at most 5 MB.');
        }
        $type = self::sniff($content) ?? throw InvalidInputException::because('Choose a JPEG, PNG or WebP image.');
        $info = @getimagesizefromstring($content);
        if (false === $info || self::SIGNATURES[$type] !== $info[2] || $info[0] < 1 || $info[1] < 1) {
            throw InvalidInputException::because('This image could not be read.');
        }
        [$width, $height] = $info;
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw InvalidInputException::because('Choose an image of at most 8000 pixels on each side.');
        }

        return new self($type, $width, $height, $content);
    }

    public function byteSize(): int
    {
        return \strlen($this->content);
    }

    /** Magic bytes only; a renamed or mislabelled file is judged by what it contains. */
    private static function sniff(string $content): ?string
    {
        if (str_starts_with($content, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($content, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($content, 'RIFF') && 'WEBP' === substr($content, 8, 4)) {
            return 'image/webp';
        }

        return null;
    }

    /** @return array<string, mixed> never the bytes */
    public function __debugInfo(): array
    {
        return ['contentType' => $this->contentType, 'width' => $this->width, 'height' => $this->height, 'bytes' => $this->byteSize()];
    }
}
