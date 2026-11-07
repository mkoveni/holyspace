<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure;

use App\Giving\Application\Contract\DonorCheckoutGuard;
use App\Giving\Domain\Repository\DonorLinkRepository;
use App\People\Application\Contract\PersonDirectory;

/** Published for Payments' one-time checkout handoff: the account's donor link is locked, then the person is read. */
final readonly class PublishedDonorCheckoutGuard implements DonorCheckoutGuard
{
    public function __construct(
        private DonorLinkRepository $links,
        private PersonDirectory $people,
    ) {
    }

    public function lockEligible(string $accountId, string $donorId): bool
    {
        $link = $this->links->lock($accountId);
        if (null === $link || !$link->active || $link->personId !== $donorId) {
            return false;
        }

        return true === $this->people->get($donorId)?->available;
    }
}
