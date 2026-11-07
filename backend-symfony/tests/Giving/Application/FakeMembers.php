<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\IdentityAccess\Application\Contract\MemberIdentity;
use LogicException;

/** In-memory test double: the person each account is (or was) bound to as a member. */
final class FakeMembers implements MemberIdentity
{
    /** @var array<string, string> */
    public array $bound = [];

    public function currentPerson(): ?string
    {
        throw new LogicException('not used by Giving');
    }

    public function activePerson(string $accountId): ?string
    {
        throw new LogicException('not used by Giving');
    }

    public function boundPerson(string $accountId): ?string
    {
        return $this->bound[$accountId] ?? null;
    }

    public function requirePerson(string $actorId): string
    {
        throw new LogicException('not used by Giving');
    }
}
