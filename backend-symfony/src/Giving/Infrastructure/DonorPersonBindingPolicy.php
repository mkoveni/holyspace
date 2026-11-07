<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure;

use App\Giving\Application\Contract\DonorLinkReader;
use App\IdentityAccess\Application\Contract\PersonBindingPolicy;

/**
 * Vetoes linking an account to a member identity that contradicts its verified donor link (active or revoked), so
 * general and financial identity always agree. Collected by IdentityAccess through the `identity.person_binding_policy` tag.
 */
final readonly class DonorPersonBindingPolicy implements PersonBindingPolicy
{
    public function __construct(private DonorLinkReader $links)
    {
    }

    public function permits(string $accountId, string $personId): bool
    {
        $link = $this->links->link($accountId);

        return null === $link || $link->personId === $personId;
    }
}
