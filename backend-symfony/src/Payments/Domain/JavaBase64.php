<?php

declare(strict_types=1);

namespace App\Payments\Domain;

/**
 * `java.util.Base64.getDecoder()`: the basic alphabet only, padding optional but exact when present, and no stray
 * characters (PHP's own strict mode skips whitespace and accepts partial padding). Stored ciphertexts, vault keys and
 * Yoco signing secrets are decoded the way Java decodes them.
 */
final class JavaBase64
{
    private function __construct()
    {
    }

    /** @return string|null the decoded bytes, or null when Java would throw IllegalArgumentException */
    public static function decode(string $value): ?string
    {
        if (1 !== preg_match('/\A([A-Za-z0-9+\/]*)(={0,2})\z/', $value, $parts)) {
            return null;
        }
        $remainder = \strlen($parts[1]) % 4;
        $padding = \strlen($parts[2]);
        if (1 === $remainder || ($padding > 0 && !((2 === $remainder && 2 === $padding) || (3 === $remainder && 1 === $padding)))) {
            return null;
        }
        $decoded = base64_decode($parts[1], true);

        return false === $decoded ? null : $decoded;
    }
}
