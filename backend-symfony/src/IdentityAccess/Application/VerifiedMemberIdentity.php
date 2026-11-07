<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\IdentityAccess\Application\Contract\MemberLinkReader;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\Repository\MemberLinkRepository;
use App\People\Application\Contract\PersonDirectory;

/**
 * Resolves the person behind a member's account. The person must be linked, the link active and the person still
 * available; nothing here grants financial, ministry or guardian authority.
 */
final readonly class VerifiedMemberIdentity implements MemberIdentity, MemberIdentityGuard
{
    public function __construct(
        private Authorization $authorization,
        private MemberLinkReader $links,
        private MemberLinkRepository $repository,
        private PersonDirectory $people,
    ) {
    }

    public function currentPerson(): ?string
    {
        return $this->activePerson($this->authorization->requireAccount());
    }

    public function activePerson(string $accountId): ?string
    {
        $link = $this->links->memberLink($accountId);
        if (null === $link || !$link->active) {
            return null;
        }
        $person = $this->people->get($link->personId);

        return null !== $person && $person->available ? $person->id : null;
    }

    public function boundPerson(string $accountId): ?string
    {
        return $this->links->memberLink($accountId)?->personId;
    }

    public function requirePerson(string $actorId): string
    {
        $this->authorization->requireSelf($actorId);

        return $this->currentPerson() ?? throw IdentityException::forbidden('Ask the church office to verify your member account.');
    }

    public function lockPerson(string $actorId): string
    {
        $this->authorization->requireSelf($actorId);
        $link = $this->repository->lock($actorId);
        if (null === $link || !$link->active) {
            throw IdentityException::forbidden('Member access is not active.');
        }

        return $this->requirePerson($actorId);
    }
}
