<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Query\AuthenticateSession\AuthenticateSessionHandler;
use App\IdentityAccess\Application\Query\AuthenticateSession\AuthenticateSessionQuery;
use App\Shared\Domain\ContextException;
use SensitiveParameter;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/** Authenticates opaque bearer tokens with a database lookup on every request. */
final readonly class SessionAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private AuthenticateSessionHandler $authenticate)
    {
    }

    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        try {
            $identity = $this->authenticate->handle(new AuthenticateSessionQuery($accessToken));
        } catch (ContextException) {
            throw new BadCredentialsException('Invalid or expired session.');
        }

        $user = new SessionUser($identity->account->id, $identity->sessionId, $identity->account->permissions);

        return new UserBadge($user->accountId, static fn (): SessionUser => $user);
    }
}
