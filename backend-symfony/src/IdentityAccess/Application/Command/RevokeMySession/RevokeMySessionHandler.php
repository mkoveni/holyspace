<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RevokeMySession;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\AccountIdentityGuard;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\SessionReader;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\IdentityAccess\Domain\IdentityException;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** A signed-in account ends one of its own sessions, for example a lost phone. Never someone else's. */
final readonly class RevokeMySessionHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private AccountIdentityGuard $guard,
        private SessionReader $reader,
        private SessionWriter $writer,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RevokeMySessionCommand $command): void
    {
        $this->authorization->requireSelf($command->actorId);
        $this->guard->lock($command->actorId);
        $this->authorization->requireSelf($command->actorId);

        $session = $this->reader->findById($command->sessionId);
        if (null === $session || $session->accountId !== $command->actorId) {
            throw IdentityException::notFound('Session was not found.');
        }
        $this->writer->revoke($command->sessionId, $command->actorId);
        $this->audit->audit($command->actorId, 'session.revoked', $command->sessionId, $this->clock->now());
    }
}
