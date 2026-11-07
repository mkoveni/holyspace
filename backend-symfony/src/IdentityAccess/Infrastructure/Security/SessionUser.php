<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/** The authenticated caller for one request: the account, its session, and live permissions. */
final readonly class SessionUser implements UserInterface
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $accountId,
        public string $sessionId,
        public array $permissions,
    ) {
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->accountId);

        return $this->accountId;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
}
