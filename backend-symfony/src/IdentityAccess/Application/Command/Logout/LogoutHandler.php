<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\Logout;

use App\IdentityAccess\Application\Authorization\Authorization;
use App\IdentityAccess\Application\Contract\IdentityAuditWriter;
use App\IdentityAccess\Application\Contract\SessionWriter;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class LogoutHandler implements CommandHandler
{
    public function __construct(
        private Authorization $authorization,
        private SessionWriter $sessions,
        private IdentityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(LogoutCommand $command): void
    {
        $this->authorization->requireSession($command->accountId, $command->sessionId);

        $this->sessions->revoke($command->sessionId, $command->accountId);
        $this->audit->audit($command->accountId, 'session.logged_out', $command->accountId, $this->clock->now());
    }
}
