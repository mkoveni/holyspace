<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use App\IdentityAccess\Application\Contract\MemberIdentityGuard;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\ChildrenMinistry\Shared\CallLog;

/** One account bound to one person; the guard logs its lock. */
final readonly class FakeMemberIdentity implements MemberIdentity, MemberIdentityGuard
{
    public function __construct(private CallLog $log, private string $account, private ?string $person)
    {
    }

    public function currentPerson(): ?string
    {
        return $this->person;
    }

    public function activePerson(string $accountId): ?string
    {
        return $accountId === $this->account ? $this->person : null;
    }

    public function boundPerson(string $accountId): ?string
    {
        return $this->activePerson($accountId);
    }

    public function requirePerson(string $actorId): string
    {
        return $actorId === $this->account && null !== $this->person ? $this->person : throw IdentityException::forbidden('Ask the church office to verify your member account.');
    }

    public function lockPerson(string $actorId): string
    {
        $this->log->entries[] = 'lock:member';

        return $this->requirePerson($actorId);
    }
}
