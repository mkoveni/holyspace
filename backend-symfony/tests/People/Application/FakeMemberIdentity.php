<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\IdentityAccess\Domain\IdentityException;

/** A member identity for one signed-in account and person; records the order of identity checks and locks. */
final class FakeMemberIdentity implements MemberIdentity, MemberIdentityGuard
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private readonly string $accountId,
        private readonly ?string $personId,
    ) {
    }

    public function currentPerson(): ?string
    {
        return $this->personId;
    }

    public function activePerson(string $accountId): ?string
    {
        return $accountId === $this->accountId ? $this->personId : null;
    }

    public function boundPerson(string $accountId): ?string
    {
        return $this->activePerson($accountId);
    }

    public function requirePerson(string $actorId): string
    {
        $this->calls[] = 'require';
        if ($actorId !== $this->accountId) {
            throw IdentityException::forbidden('Permission denied.');
        }

        return $this->personId ?? throw IdentityException::forbidden('Ask the church office to verify your member account.');
    }

    public function lockPerson(string $actorId): string
    {
        $this->calls[] = 'lock';

        return $this->requirePerson($actorId);
    }
}
