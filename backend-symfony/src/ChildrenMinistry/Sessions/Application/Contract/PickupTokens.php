<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

/** Pickup codes: issued once, stored only as a hash, compared in constant time. */
interface PickupTokens
{
    public function issue(): string;

    public function hash(string $value): string;

    public function matches(?string $value, ?string $hash): bool;
}
