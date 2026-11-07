<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Contract\Actor;
use App\IdentityAccess\Application\Contract\CurrentActor;
use Symfony\Bundle\SecurityBundle\Security;

/** Reads the verified bearer authentication; the application layer never sees Symfony Security. */
final readonly class SecurityCurrentActor implements CurrentActor
{
    public function __construct(private Security $security)
    {
    }

    public function current(): ?Actor
    {
        $user = $this->security->getUser();

        return $user instanceof SessionUser ? new Actor($user->accountId, $user->sessionId) : null;
    }
}
