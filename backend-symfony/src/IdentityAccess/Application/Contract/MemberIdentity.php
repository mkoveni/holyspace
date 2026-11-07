<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Contract;

/** General member identity. Financial, ministry and guardian capabilities remain independently authorized. */
interface MemberIdentity
{
    /** The person linked to the authenticated account, if the link is active and the person is available. */
    public function currentPerson(): ?string;

    public function activePerson(string $accountId): ?string;

    /** The person ever linked to the account, even when the link is now revoked. */
    public function boundPerson(string $accountId): ?string;

    /** The caller's own verified person. The claimed actor must be the trusted caller. */
    public function requirePerson(string $actorId): string;
}
