<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateSession;

use App\IdentityAccess\Application\AccountView;
use App\IdentityAccess\Application\Contract\AccessReader;
use App\IdentityAccess\Application\Contract\AccountReader;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\TokenCodec;
use App\IdentityAccess\Domain\IdentityException;
use App\IdentityAccess\Domain\IdentityRules;
use Psr\Clock\ClockInterface;

final readonly class AuthenticateSessionHandler
{
    public function __construct(
        private SessionReader $sessions,
        private AccountReader $accounts,
        private AccessReader $access,
        private TokenCodec $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AuthenticateSessionQuery $query): AuthenticatedIdentity
    {
        if (null === $query->token || 1 !== preg_match('/\A[A-Za-z0-9_-]{43}\z/', $query->token)) {
            throw self::unauthorized();
        }

        $session = $this->sessions->find($this->tokens->hash($query->token));
        if (null === $session || $session->expiresAt <= $this->clock->now()) {
            throw self::unauthorized();
        }

        $account = $this->accounts->find($session->accountId);
        if (null === $account || !$account->isActive()) {
            throw self::unauthorized();
        }

        return new AuthenticatedIdentity($session->id, AccountView::from(
            $account,
            $this->access->roleNames($account->id),
            IdentityRules::effectivePermissions($this->access->permissionCodes($account->id)),
        ));
    }

    private static function unauthorized(): IdentityException
    {
        return IdentityException::unauthenticated('Invalid or expired session.');
    }
}
