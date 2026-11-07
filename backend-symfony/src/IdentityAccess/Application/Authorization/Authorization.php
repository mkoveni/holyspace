<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Authorization;

use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\Actor;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use Psr\Clock\ClockInterface;

/**
 * Protected handlers call this before touching protected data. It re-checks the live session and
 * account status and loads effective permissions, so revocation and permission changes apply
 * immediately even when an authentication context is reused.
 */
final readonly class Authorization implements PermissionChecker, AuthenticatedAccount
{
    public function __construct(
        private CurrentActor $currentActor,
        private AccountReader $accounts,
        private AccessReader $access,
        private SessionReader $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function hasPermission(string $permission): bool
    {
        return $this->holds($this->authenticatedActor(), $permission);
    }

    public function requirePermission(string $permission): void
    {
        $this->requireHeld($this->authenticatedActor(), $permission);
    }

    public function requireActorPermission(string $claimedActorId, string $permission): void
    {
        $actor = $this->authenticatedActor();
        $this->requireMatchingAccount($actor, $claimedActorId);
        $this->requireHeld($actor, $permission);
    }

    public function requireAccount(): string
    {
        return $this->authenticatedActor()->accountId;
    }

    public function requireSelf(string $accountId): void
    {
        $this->requireMatchingAccount($this->authenticatedActor(), $accountId);
    }

    public function requireSelfOrPermission(string $accountId, string $permission): void
    {
        $actor = $this->authenticatedActor();
        if ($actor->accountId !== $accountId) {
            $this->requireHeld($actor, $permission);
        }
    }

    /** Restricts an action to the caller's own current session. */
    public function requireSession(string $accountId, string $sessionId): void
    {
        $actor = $this->authenticatedActor();
        $this->requireMatchingAccount($actor, $accountId);
        if ($actor->sessionId !== $sessionId) {
            throw self::forbidden();
        }
    }

    private function authenticatedActor(): Actor
    {
        $actor = $this->currentActor->current() ?? throw self::unauthenticated();

        $session = $this->sessions->findById($actor->sessionId);
        if (null === $session || $session->accountId !== $actor->accountId || $session->expiresAt <= $this->clock->now()) {
            throw self::unauthenticated();
        }

        $account = $this->accounts->find($actor->accountId);
        if (null === $account || !$account->isActive()) {
            throw self::unauthenticated();
        }

        return $actor;
    }

    private function holds(Actor $actor, string $permission): bool
    {
        return in_array(
            $permission,
            IdentityRules::effectivePermissions($this->access->permissionCodes($actor->accountId)),
            true,
        );
    }

    private function requireHeld(Actor $actor, string $permission): void
    {
        if (!$this->holds($actor, $permission)) {
            throw self::forbidden();
        }
    }

    private function requireMatchingAccount(Actor $actor, string $accountId): void
    {
        if ($actor->accountId !== $accountId) {
            throw self::forbidden();
        }
    }

    private static function unauthenticated(): IdentityException
    {
        return IdentityException::unauthenticated('A valid authenticated session is required.');
    }

    private static function forbidden(): IdentityException
    {
        return IdentityException::forbidden('Permission denied.');
    }
}
