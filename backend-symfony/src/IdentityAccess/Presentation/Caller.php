<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation;

use App\IdentityAccess\Application\Contract\Actor;
use App\IdentityAccess\Application\Contract\CurrentActor;
use App\IdentityAccess\Domain\IdentityException;

/** The authenticated caller as controllers see it, resolved through the application's CurrentActor contract. */
final readonly class Caller
{
    public function __construct(private CurrentActor $currentActor)
    {
    }

    public function actor(): Actor
    {
        return $this->currentActor->current() ?? throw IdentityException::unauthenticated('A valid authenticated session is required.');
    }

    public function accountId(): string
    {
        return $this->actor()->accountId;
    }
}
