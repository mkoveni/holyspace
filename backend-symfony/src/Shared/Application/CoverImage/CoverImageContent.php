<?php

declare(strict_types=1);

namespace App\Shared\Application\CoverImage;

/** A stored cover image's bytes with the type they were validated as. */
final readonly class CoverImageContent
{
    public function __construct(public string $contentType, public string $content)
    {
    }

    /** @return array<string, mixed> never the bytes */
    public function __debugInfo(): array
    {
        return ['contentType' => $this->contentType, 'bytes' => \strlen($this->content)];
    }
}
