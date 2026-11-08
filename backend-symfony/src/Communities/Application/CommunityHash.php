<?php

declare(strict_types=1);

namespace App\Communities\Application;

/**
 * Java `CommunityHash`: SHA-256 over each value's `String.valueOf` text in UTF-8, each prefixed by its byte length
 * as a 4-byte big-endian integer. `null` hashes as the text "null", booleans as "true"/"false", UUIDs lower-case.
 * Request ids stored by Java must replay here, so do not change this without updating `CommunityHashTest`.
 */
final class CommunityHash
{
    private function __construct()
    {
    }

    public static function of(string|int|bool|null ...$values): string
    {
        $context = hash_init('sha256');
        foreach ($values as $value) {
            $text = match (true) {
                null === $value => 'null',
                true === $value => 'true',
                false === $value => 'false',
                default => (string) $value,
            };
            hash_update($context, pack('N', strlen($text)).$text);
        }

        return hash_final($context);
    }
}
