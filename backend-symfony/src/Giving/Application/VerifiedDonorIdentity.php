<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\Giving\Application\Contract\DonorIdentity;
use App\Giving\Application\Contract\DonorIdentityGuard;
use App\Giving\Application\Contract\DonorLinkReader;
use App\Giving\Application\Contract\VerifiedDonor;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\DonorLinkRepository;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\People\Application\Contract\PersonDirectory;

/**
 * Financial identity comes only from an active, staff-verified donor link of the live caller. Email matches, households
 * and People relationships never grant it.
 */
final readonly class VerifiedDonorIdentity implements DonorIdentity, DonorIdentityGuard
{
    public function __construct(
        private AuthenticatedAccount $account,
        private DonorLinkReader $links,
        private PersonDirectory $people,
        private DonorLinkRepository $repository,
    ) {
    }

    public function lockOwn(string $actorId, string $donorId): void
    {
        $this->requireCaller($actorId);
        $link = $this->repository->lock($actorId);
        if (null === $link || !$link->active || $link->personId !== $donorId) {
            throw GivingException::forbidden();
        }
        $this->requireOwn($actorId, $donorId);
    }

    public function current(): ?VerifiedDonor
    {
        $link = $this->links->link($this->account->requireAccount());
        if (null === $link || !$link->active) {
            return null;
        }
        $person = $this->people->get($link->personId);

        return null === $person || !$person->available ? null : new VerifiedDonor($person->id, $person->name);
    }

    public function requireOwn(string $actorId, ?string $donorId): string
    {
        $this->requireCaller($actorId);
        $donor = $this->current() ?? throw GivingException::forbidden();
        if (null !== $donorId && $donor->id !== $donorId) {
            throw GivingException::forbidden();
        }

        return $donor->id;
    }

    private function requireCaller(string $actorId): void
    {
        if ($this->account->requireAccount() !== $actorId) {
            throw GivingException::forbidden();
        }
    }
}
