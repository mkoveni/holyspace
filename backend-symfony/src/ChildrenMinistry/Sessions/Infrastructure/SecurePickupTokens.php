<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Infrastructure;

use App\ChildrenMinistry\Sessions\Application\Contract\PickupTokens;

/** 128 random bits as URL-safe base64; the database keeps only the SHA-256 hash. */
final readonly class SecurePickupTokens implements PickupTokens
{
    public function issue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function matches(?string $value, ?string $hash): bool
    {
        return null !== $value && null !== $hash && hash_equals($hash, $this->hash($value));
    }
}
