<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

/** Builds contains-style ILIKE patterns that treat the search text literally. */
final class LikePattern
{
    private function __construct()
    {
    }

    public static function contains(string $text): string
    {
        return '%'.addcslashes($text, '\\%_').'%';
    }
}
