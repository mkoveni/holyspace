<?php

declare(strict_types=1);

namespace App\Media\Application\Contract;

/** The bytes of a stored picture with the type they were stored as. */
final readonly class ImageContent
{
    public function __construct(public string $mime, public string $bytes)
    {
    }

    /** @return array<string, mixed> never the bytes */
    public function __debugInfo(): array
    {
        return ['mime' => $this->mime, 'bytes' => \strlen($this->bytes)];
    }
}
