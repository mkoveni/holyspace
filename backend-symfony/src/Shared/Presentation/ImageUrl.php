<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

/** The same-origin path of a public stored picture (Media): a random file id, no credentials, no storage path. */
final class ImageUrl
{
    public const string PUBLIC_PATH = '/api/public/media/';

    private function __construct()
    {
    }

    public static function public(?string $fileId): ?string
    {
        return null === $fileId ? null : self::PUBLIC_PATH.$fileId;
    }
}
